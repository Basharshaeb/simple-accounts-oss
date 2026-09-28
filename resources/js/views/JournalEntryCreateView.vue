<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">إنشاء قيد يومي جديد</h3>
        <p class="text-muted small m-0">تطبيق القيد المزدوج، تحديد الفرع، وتصفية العملات المرتبطة بكل حساب</p>
      </div>

      <router-link to="/journal-entries" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-right me-1"></i> العودة للقائمة
      </router-link>
    </div>

    <div class="card p-4 bg-white shadow-sm border-0">
      <div v-if="errorMessage" class="alert alert-danger py-2 mb-3">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ errorMessage }}
      </div>

      <form @submit.prevent="submitJournal">
        <!-- Header Info -->
        <div class="row g-3 mb-4 bg-light p-3 rounded border">
          <div class="col-md-3">
            <label class="form-label font-weight-bold text-primary">رقم القيد (تسلسل تلقائي)</label>
            <input type="text" :value="entryNumber" class="form-control bg-white font-monospace fw-bold text-primary border-primary" readonly disabled />
          </div>

          <div class="col-md-3">
            <label class="form-label font-weight-bold">الفرع <span class="text-danger">*</span></label>
            <select v-model="form.branch_id" class="form-select" required>
              <option value="" disabled>اختر الفرع...</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} - {{ b.name }}</option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label font-weight-bold">تاريخ القيد <span class="text-danger">*</span></label>
            <input v-model="form.entry_date" type="date" class="form-control" @change="fetchNextNumber" required />
          </div>

          <div class="col-md-3">
            <label class="form-label font-weight-bold">المرجع / الرقم الدفتري</label>
            <input v-model="form.reference" type="text" class="form-control" placeholder="مثال: REF-1092" />
          </div>

          <div class="col-12">
            <label class="form-label font-weight-bold">البيان العام للقيد <span class="text-danger">*</span></label>
            <input v-model="form.description" type="text" class="form-control" placeholder="أدخل وصفًا تفصيليًا للعملية المالية" required />
          </div>
        </div>

        <!-- Lines Table -->
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="fw-bold m-0"><i class="bi bi-list-nested me-2 text-primary"></i>سطور القيد (Journal Lines)</h5>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="addLine">
            <i class="bi bi-plus-lg me-1"></i> إضافة سطر
          </button>
        </div>

        <div class="table-responsive mb-4" style="overflow: visible;">
          <table class="table table-bordered align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th style="width: 25%;">الحساب المحاسبي <span class="text-danger">*</span></th>
                <th style="width: 18%;">عملة السطر (حسب الحساب)</th>
                <th style="width: 12%;">سعر الصرف</th>
                <th style="width: 17%;">البيان الفرعي</th>
                <th style="width: 11%;">مدين (Debit)</th>
                <th style="width: 11%;">دائن (Credit)</th>
                <th style="width: 6%;">حذف</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, idx) in form.lines" :key="idx">
                <td class="position-relative">
                  <div class="input-group">
                    <span class="input-group-text bg-light text-primary border-end-0">
                      <i class="bi bi-search"></i>
                    </span>
                    <input 
                      type="text" 
                      class="form-control border-start-0 ps-0 fw-bold text-dark" 
                      placeholder="ابحث أو اختر الحساب..." 
                      v-model="line.accountSearch" 
                      @focus="line.showDropdown = true" 
                      @input="line.showDropdown = true; line.account_id = ''; onAccountSelect(line)" 
                      @blur="onAccountBlur(line)" 
                      required 
                    />
                    <button 
                      type="button" 
                      class="btn btn-outline-secondary" 
                      @click="line.showDropdown = !line.showDropdown"
                      tabindex="-1"
                    >
                      <i class="bi bi-chevron-down small"></i>
                    </button>
                  </div>

                  <div 
                    v-if="line.showDropdown" 
                    class="dropdown-menu show shadow-lg border-0 rounded-3 mt-1 p-0" 
                    style="min-width: 380px; max-width: 100%; max-height: 280px; overflow-y: auto; z-index: 1060; top: 100%; right: 0; position: absolute;"
                  >
                    <div class="px-3 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                      <span class="small fw-bold text-muted">
                        <i class="bi bi-list-stars me-1 text-primary"></i> نتائج البحث 
                        <span class="badge bg-primary-subtle text-primary ms-1">{{ getFilteredAccounts(line.accountSearch).length }}</span>
                      </span>
                      <small class="text-muted" style="font-size: 0.75rem;">انقر للاختيار</small>
                    </div>

                    <div class="list-group list-group-flush">
                      <button 
                        type="button" 
                        v-for="acc in getFilteredAccounts(line.accountSearch)" 
                        :key="acc.id" 
                        class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center border-bottom-0" 
                        :class="{'bg-primary-subtle text-primary fw-bold': line.account_id === acc.id}" 
                        @mousedown.prevent="selectAccount(line, acc)"
                      >
                        <div class="d-flex align-items-center gap-2">
                          <span class="badge bg-secondary-subtle text-dark font-monospace px-2 py-1 fs-6">{{ acc.code }}</span>
                          <span class="fw-bold text-dark">{{ acc.name }}</span>
                        </div>
                        <i v-if="line.account_id === acc.id" class="bi bi-check-circle-fill text-primary fs-5"></i>
                      </button>

                      <div v-if="getFilteredAccounts(line.accountSearch).length === 0" class="p-4 text-center text-muted">
                        <i class="bi bi-search text-secondary display-6 d-block mb-2"></i>
                        <span class="fw-bold">لا يوجد حساب مطابق للبحث</span>
                        <div class="small text-muted mt-1">تأكد من كتابة اسم الحساب أو كوده بشكل صحيح</div>
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <select 
                    v-model="line.currency_id" 
                    class="form-select fw-bold" 
                    :disabled="!line.account_id"
                    @change="onLineCurrencySelect(line)"
                    required
                  >
                    <option value="" disabled>اختر العملة...</option>
                    <option v-for="curr in getLineAllowedCurrencies(line.account_id)" :key="curr.id" :value="curr.id">
                      {{ curr.code }} ({{ curr.name }})
                    </option>
                  </select>
                </td>
                <td>
                  <input 
                    v-model.number="line.exchange_rate" 
                    type="number" 
                    step="0.000001" 
                    min="0.000001" 
                    class="form-control font-monospace" 
                    required 
                  />
                </td>
                <td>
                  <input v-model="line.description" type="text" class="form-control" placeholder="بيان تفصيلي للسطر..." />
                </td>
                <td>
                  <input v-model.number="line.debit" type="number" step="0.01" min="0" class="form-control text-success fw-bold" @input="line.credit = 0" />
                </td>
                <td>
                  <input v-model.number="line.credit" type="number" step="0.01" min="0" class="form-control text-danger fw-bold" @input="line.debit = 0" />
                </td>
                <td class="text-center font-monospace small fw-bold">
                  {{ formatMoney((line.debit > 0 ? line.debit : line.credit) * line.exchange_rate) }}
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLine(idx)" :disabled="form.lines.length <= 2">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot class="table-light fw-bold">
              <tr>
                <td colspan="4" class="text-end">إجمالي المعادلة بالعملة المحلية للشركة:</td>
                <td class="text-success text-center fs-5">{{ formatMoney(totalBaseDebit) }}</td>
                <td class="text-danger text-center fs-5">{{ formatMoney(totalBaseCredit) }}</td>
                <td colspan="2"></td>
              </tr>
              <tr :class="difference === 0 ? 'table-success' : 'table-danger'">
                <td colspan="4" class="text-end">الفارق المحاسبي بالعملة المحلية:</td>
                <td colspan="2" class="text-center fs-5 fw-bold">
                  <span v-if="difference === 0" class="text-success"><i class="bi bi-check-circle-fill me-1"></i> القيد متوازن تمامًا (0.00)</span>
                  <span v-else class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> غير متوازن! الفارق: {{ formatMoney(difference) }}</span>
                </td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Buttons -->
        <div class="d-flex justify-content-end gap-2">
          <button type="submit" class="btn btn-warning" :disabled="saving || difference !== 0" @click="autoPostFlag = false">
            <i class="bi bi-save me-1"></i> حفظ كمسودة (Draft)
          </button>
          <button type="submit" class="btn btn-success" :disabled="saving || difference !== 0" @click="autoPostFlag = true">
            <i class="bi bi-check-all me-1"></i> حفظ واعتماد مباشر (Post)
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();

const entryNumber = ref('JE-2026-******');
const currencies = ref([]);
const branches = ref([]);
const postableAccounts = ref([]);

const getFilteredAccounts = (searchQuery) => {
  if (!searchQuery || !searchQuery.trim()) return postableAccounts.value;
  const q = searchQuery.trim().toLowerCase();
  return postableAccounts.value.filter(a =>
    a.name.toLowerCase().includes(q) || a.code.toLowerCase().includes(q)
  );
};
const errorMessage = ref('');
const saving = ref(false);
const autoPostFlag = ref(false);

const form = ref({
  branch_id: '',
  entry_date: new Date().toISOString().substring(0, 10),
  reference: '',
  description: '',
  lines: [
    { account_id: '', currency_id: '', exchange_rate: 1.0, description: '', debit: 0, credit: 0 },
    { account_id: '', currency_id: '', exchange_rate: 1.0, description: '', debit: 0, credit: 0 },
  ],
});

const formatMoney = (val) => Number(val || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 });

const getLineAllowedCurrencies = (accountId) => {
  if (!accountId) return currencies.value;
  const acc = postableAccounts.value.find(a => a.id === accountId);
  if (acc && acc.currencies && acc.currencies.length > 0) {
    const allowedIds = acc.currencies.map(c => c.id);
    return currencies.value.filter(c => allowedIds.includes(c.id));
  }
  return currencies.value;
};

const onAccountSelect = (line) => {
  const allowed = getLineAllowedCurrencies(line.account_id);
  if (allowed.length > 0) {
    line.currency_id = allowed[0].id;
    line.exchange_rate = allowed[0].exchange_rate || 1.0;
  }
};

const onLineCurrencySelect = (line) => {
  const curr = currencies.value.find(c => c.id === line.currency_id);
  if (curr) {
    line.exchange_rate = curr.exchange_rate || 1.0;
  }
};

const selectAccount = (line, acc) => {
  line.account_id = acc.id;
  line.accountSearch = `${acc.code} - ${acc.name}`;
  line.showDropdown = false;
  onAccountSelect(line);
};

const onAccountBlur = (line) => {
  setTimeout(() => {
    line.showDropdown = false;
    if (line.account_id) {
      const selected = postableAccounts.value.find(a => a.id === line.account_id);
      if (selected) {
        line.accountSearch = `${selected.code} - ${selected.name}`;
      }
    } else {
      line.accountSearch = '';
    }
  }, 200);
};

const addLine = () => {
  const defaultCurr = currencies.value.length > 0 ? currencies.value[0] : null;
  form.value.lines.push({
    account_id: '',
    accountSearch: '',
    showDropdown: false,
    currency_id: defaultCurr ? defaultCurr.id : '',
    exchange_rate: defaultCurr ? (defaultCurr.exchange_rate || 1.0) : 1.0,
    description: '',
    debit: 0,
    credit: 0,
  });
};

const removeLine = (idx) => {
  if (form.value.lines.length > 2) {
    form.value.lines.splice(idx, 1);
  }
};

const totalBaseDebit = computed(() => {
  return form.value.lines.reduce((sum, l) => sum + (Number(l.debit || 0) * Number(l.exchange_rate || 1)), 0);
});

const totalBaseCredit = computed(() => {
  return form.value.lines.reduce((sum, l) => sum + (Number(l.credit || 0) * Number(l.exchange_rate || 1)), 0);
});

const difference = computed(() => {
  return Math.abs(Math.round((totalBaseDebit.value - totalBaseCredit.value) * 10000) / 10000);
});

const fetchNextNumber = async () => {
  try {
    const res = await axios.get('/api/v1/journal-entries/next-number?date=' + form.value.entry_date);
    if (res.data.success) {
      entryNumber.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to fetch next number:', err);
  }
};

const fetchData = async () => {
  try {
    const [currRes, accRes, bRes] = await Promise.all([
      axios.get('/api/v1/currencies'),
      axios.get('/api/v1/accounts?tree=false&is_postable=true'),
      axios.get('/api/v1/branches'),
    ]);

    if (currRes.data.success) {
      currencies.value = currRes.data.data;
      // Initialize line currencies
      form.value.lines.forEach(l => {
        if (!l.currency_id && currencies.value.length > 0) {
          l.currency_id = currencies.value[0].id;
          l.exchange_rate = currencies.value[0].exchange_rate || 1.0;
        }
      });
    }

    if (accRes.data.success) postableAccounts.value = accRes.data.data;
    if (bRes.data.success) {
      branches.value = bRes.data.data;
      if (branches.value.length > 0 && !form.value.branch_id) {
        form.value.branch_id = branches.value[0].id;
      }
    }

    await fetchNextNumber();
  } catch (err) {
    console.error('Failed to load init data:', err);
  }
};

const submitJournal = async () => {
  if (difference.value !== 0) {
    errorMessage.value = 'لا يمكن حفظ القيد غير المتوازن بالعملة المحلية!';
    return;
  }

  saving.value = true;
  errorMessage.value = '';

  try {
    const payload = {
      ...form.value,
      branch_id: form.value.branch_id || null,
      auto_post: autoPostFlag.value,
    };
    const res = await axios.post('/api/v1/journal-entries', payload);
    if (res.data.success) {
      alert('تم حفظ القيد المحاسبي بنجاح.');
      router.push({ name: 'journal-entries' });
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'فشل حفظ القيد المحاسبي';
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchData();
});
</script>
