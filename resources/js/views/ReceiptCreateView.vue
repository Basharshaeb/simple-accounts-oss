<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0 text-success"><i class="bi bi-arrow-down-left-square me-2"></i>إنشاء سند قبض جديد</h3>
        <p class="text-muted small m-0">استلام الأموال وتسجيل أسطر القبض متعددة الأطراف وتوليد القيد التلقائي</p>
      </div>

      <router-link to="/receipts" class="btn btn-secondary btn-sm">
        <i class="bi bi-arrow-right me-1"></i> العودة لقائمة سندات القبض
      </router-link>
    </div>

    <div class="card p-4 bg-white shadow-sm border-0">
      <div v-if="errorMessage" class="alert alert-danger py-2 mb-3">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ errorMessage }}
      </div>

      <form @submit.prevent="submitReceipt">
        <!-- Main Form Fields -->
        <div class="row g-3 mb-4 bg-light p-3 rounded border">
          <div class="col-md-3">
            <label class="form-label font-weight-bold text-success">رقم السند (تسلسل تلقائي)</label>
            <input type="text" :value="voucherNumber" class="form-control bg-white font-monospace fw-bold text-success border-success" readonly disabled />
          </div>

          <div class="col-md-3">
            <label class="form-label font-weight-bold">الفرع <span class="text-danger">*</span></label>
            <select v-model="form.branch_id" class="form-select" required>
              <option value="" disabled>اختر الفرع...</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} - {{ b.name }}</option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label font-weight-bold">طريقة الدفع <span class="text-danger">*</span></label>
            <select v-model="form.payment_method" class="form-select" @change="onPaymentMethodChange" required>
              <option value="CASH">نقداً (الصندوق)</option>
              <option value="BANK_TRANSFER">تحويل بنكي (البنك)</option>
              <option value="CHEQUE">شيك (البنك)</option>
              <option value="OTHER">أخرى</option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label font-weight-bold">
              {{ form.payment_method === 'CASH' ? 'الصندوق النقدي' : (form.payment_method === 'OTHER' ? 'الصندوق / البنك' : 'الحساب البنكي') }} <span class="text-danger">*</span>
            </label>
            <select v-model="form.cash_box_id" class="form-select" @change="onCashBoxSelect" required>
              <option value="" disabled>اختر الصندوق أو البنك...</option>
              <option v-for="cb in filteredCashBoxes" :key="cb.id" :value="cb.id">
                {{ cb.code }} - {{ cb.name }} ({{ cb.account ? cb.account.name : '' }})
              </option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label font-weight-bold">تاريخ السند <span class="text-danger">*</span></label>
            <input v-model="form.receipt_date" type="date" class="form-control" @change="fetchNextNumber" required />
          </div>

          <div class="col-md-4">
            <label class="form-label font-weight-bold">العملة <span class="text-danger">*</span></label>
            <select v-model="form.currency_id" class="form-select" required>
              <option v-for="curr in allowedCurrencies" :key="curr.id" :value="curr.id">{{ curr.code }} - {{ curr.name }}</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label font-weight-bold">المرجع / رقم الشيك أو التحويل</label>
            <input v-model="form.reference" type="text" class="form-control" placeholder="مثال: CHQ-88219 أو TR-0012" />
          </div>

          <div class="col-12">
            <label class="form-label font-weight-bold">البيان العام للسند <span class="text-danger">*</span></label>
            <input v-model="form.description" type="text" class="form-control" placeholder="أدخل بيان سند القبض العام" required />
          </div>
        </div>

        <!-- Multi-Party Lines Table -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold m-0 text-dark"><i class="bi bi-list-stars me-2 text-success"></i>أطراف الاستلام (سند متعدد الأطراف)</h5>
          <button type="button" class="btn btn-outline-success btn-sm" @click="addLine">
            <i class="bi bi-plus-lg me-1"></i> إضافة سطر استلام
          </button>
        </div>

        <div class="table-responsive mb-4" style="overflow: visible;">
          <table class="table table-bordered align-middle">
            <thead class="table-dark text-center">
              <tr>
                <th style="width: 40%;">الحساب المستلم منه (عميل / إيراد / جهة) <span class="text-danger">*</span></th>
                <th style="width: 35%;">البيان الفرعي للسطر</th>
                <th style="width: 20%;">المبلغ <span class="text-danger">*</span></th>
                <th style="width: 5%;">حذف</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, idx) in form.lines" :key="idx">
                <td class="position-relative">
                  <div class="input-group">
                    <span class="input-group-text bg-light text-success border-end-0">
                      <i class="bi bi-search"></i>
                    </span>
                    <input 
                      type="text" 
                      class="form-control border-start-0 ps-0 fw-bold text-dark" 
                      placeholder="ابحث أو اختر الحساب (عميل / إيراد / جهة)..." 
                      v-model="line.accountSearch" 
                      @focus="line.showDropdown = true" 
                      @input="line.showDropdown = true; line.account_id = ''" 
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
                        <i class="bi bi-list-stars me-1 text-success"></i> نتائج البحث 
                        <span class="badge bg-success-subtle text-success ms-1">{{ getFilteredAccounts(line.accountSearch).length }}</span>
                      </span>
                      <small class="text-muted" style="font-size: 0.75rem;">انقر للاختيار</small>
                    </div>

                    <div class="list-group list-group-flush">
                      <button 
                        type="button" 
                        v-for="acc in getFilteredAccounts(line.accountSearch)" 
                        :key="acc.id" 
                        class="list-group-item list-group-item-action py-2 px-3 d-flex justify-content-between align-items-center border-bottom-0" 
                        :class="{'bg-success-subtle text-success fw-bold': line.account_id === acc.id}" 
                        @mousedown.prevent="selectAccount(line, acc)"
                      >
                        <div class="d-flex align-items-center gap-2">
                          <span class="badge bg-secondary-subtle text-dark font-monospace px-2 py-1 fs-6">{{ acc.code }}</span>
                          <span class="fw-bold text-dark">{{ acc.name }}</span>
                        </div>
                        <i v-if="line.account_id === acc.id" class="bi bi-check-circle-fill text-success fs-5"></i>
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
                  <input v-model="line.description" type="text" class="form-control" placeholder="وصف السطر الفرعي (اختياري)..." />
                </td>
                <td>
                  <input 
                    v-model.number="line.amount" 
                    type="number" 
                    step="0.01" 
                    min="0.01" 
                    class="form-control text-end fw-bold text-success font-monospace fs-6" 
                    required 
                  />
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLine(idx)" :disabled="form.lines.length <= 1">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot class="table-light fw-bold fs-5">
              <tr>
                <td colspan="2" class="text-end">إجمالي مبلغ سند القبض:</td>
                <td class="text-end text-success font-monospace">{{ formatMoney(totalReceiptAmount) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Form Buttons -->
        <div class="d-flex justify-content-end gap-2">
          <button type="submit" class="btn btn-warning px-4" :disabled="saving" @click="autoPostFlag = false">
            <i class="bi bi-save me-1"></i> حفظ كمسودة
          </button>
          <button type="submit" class="btn btn-success fw-bold px-4" :disabled="saving" @click="autoPostFlag = true">
            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="bi bi-check-all me-1"></i> حفظ واعتماد مباشر
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

const voucherNumber = ref('RV-2026-******');
const currencies = ref([]);
const accounts = ref([]);
const branches = ref([]);
const cashBoxes = ref([]);
const errorMessage = ref('');
const saving = ref(false);
const autoPostFlag = ref(true);

const form = ref({
  branch_id: '',
  cash_box_id: '',
  receipt_date: new Date().toISOString().substring(0, 10),
  cash_or_bank_account_id: '',
  currency_id: '',
  payment_method: 'CASH',
  reference: '',
  description: '',
  lines: [
    { account_id: '', amount: 0, description: '' },
  ],
});

const formatMoney = (val) => Number(val || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 });

const addLine = () => {
  form.value.lines.push({ account_id: '', amount: 0, description: '', accountSearch: '', showDropdown: false });
};

const selectAccount = (line, acc) => {
  line.account_id = acc.id;
  line.accountSearch = `${acc.code} - ${acc.name}`;
  line.showDropdown = false;
};

const onAccountBlur = (line) => {
  setTimeout(() => {
    line.showDropdown = false;
    if (line.account_id) {
      const selected = accounts.value.find(a => a.id === line.account_id);
      if (selected) {
        line.accountSearch = `${selected.code} - ${selected.name}`;
      }
    } else {
      line.accountSearch = '';
    }
  }, 200);
};

const removeLine = (idx) => {
  if (form.value.lines.length > 1) {
    form.value.lines.splice(idx, 1);
  }
};

const totalReceiptAmount = computed(() => {
  return form.value.lines.reduce((sum, l) => sum + (Number(l.amount) || 0), 0);
});

const postableAccounts = computed(() => accounts.value.filter(a => !a.name.includes('صندوق') && !a.name.includes('البنك')));

const getFilteredAccounts = (searchQuery) => {
  if (!searchQuery || !searchQuery.trim()) return postableAccounts.value;
  const q = searchQuery.trim().toLowerCase();
  return postableAccounts.value.filter(a =>
    a.name.toLowerCase().includes(q) || a.code.toLowerCase().includes(q)
  );
};

// Filter Cash Boxes based on Payment Method (CASH vs BANK)
const filteredCashBoxes = computed(() => {
  if (!cashBoxes.value || cashBoxes.value.length === 0) return [];

  const pm = form.value.payment_method;
  if (pm === 'CASH') {
    return cashBoxes.value.filter(cb => {
      const code = (cb.code || '').toUpperCase();
      const accCode = cb.account ? (cb.account.code || '') : '';
      const name = (cb.name || '');
      const accName = cb.account ? (cb.account.name || '') : '';
      return accCode.startsWith('111') || name.includes('صندوق') || accName.includes('صندوق') || code.startsWith('CB') || name.includes('خزينة');
    });
  } else if (pm === 'BANK_TRANSFER' || pm === 'CHEQUE') {
    return cashBoxes.value.filter(cb => {
      const code = (cb.code || '').toUpperCase();
      const accCode = cb.account ? (cb.account.code || '') : '';
      const name = (cb.name || '');
      const accName = cb.account ? (cb.account.name || '') : '';
      return accCode.startsWith('112') || name.includes('بنك') || accName.includes('بنك') || code.startsWith('BK');
    });
  }

  return cashBoxes.value;
});

// Filter allowed currencies based on selected Cash Box / Bank account
const allowedCurrencies = computed(() => {
  if (!form.value.cash_box_id) return currencies.value;
  const cb = cashBoxes.value.find(c => String(c.id) === String(form.value.cash_box_id));
  if (cb && cb.account) {
    const acc = cb.account;
    if (acc.currencies && acc.currencies.length > 0) {
      const allowedIds = acc.currencies.map(c => c.id);
      return currencies.value.filter(c => allowedIds.includes(c.id));
    }
    if (acc.currency_id) {
      return currencies.value.filter(c => c.id === acc.currency_id);
    }
  }
  return currencies.value;
});

const onCashBoxSelect = () => {
  if (!form.value.cash_box_id) return;
  const cb = cashBoxes.value.find(c => String(c.id) === String(form.value.cash_box_id));
  if (cb) {
    if (cb.branch_id) form.value.branch_id = cb.branch_id;
    if (cb.account_id) form.value.cash_or_bank_account_id = cb.account_id;

    // Check currency validity
    const allowed = allowedCurrencies.value;
    if (allowed.length > 0) {
      const exists = allowed.some(c => c.id === form.value.currency_id);
      if (!exists) {
        form.value.currency_id = allowed[0].id;
      }
    }
  }
};

const onPaymentMethodChange = () => {
  const list = filteredCashBoxes.value;
  if (list.length > 0) {
    const defaultInList = list.find(c => c.is_default) || list[0];
    form.value.cash_box_id = defaultInList.id;
  } else {
    form.value.cash_box_id = '';
    form.value.cash_or_bank_account_id = '';
  }
  onCashBoxSelect();
};

const fetchNextNumber = async () => {
  try {
    const res = await axios.get('/api/v1/receipts/next-number?date=' + form.value.receipt_date);
    if (res.data.success) {
      voucherNumber.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to fetch next number:', err);
  }
};

const fetchData = async () => {
  try {
    const [currRes, accRes, bRes, cbRes] = await Promise.all([
      axios.get('/api/v1/currencies'),
      axios.get('/api/v1/accounts?tree=false&is_postable=true'),
      axios.get('/api/v1/branches'),
      axios.get('/api/v1/cash-boxes'),
    ]);

    if (currRes.data.success) {
      currencies.value = currRes.data.data;
      if (currencies.value.length > 0) form.value.currency_id = currencies.value[0].id;
    }
    if (accRes.data.success) accounts.value = accRes.data.data;
    if (bRes.data.success) {
      branches.value = bRes.data.data;
      if (branches.value.length > 0 && !form.value.branch_id) {
        form.value.branch_id = branches.value[0].id;
      }
    }
    if (cbRes.data.success) {
      cashBoxes.value = cbRes.data.data;
      onPaymentMethodChange();
    }

    await fetchNextNumber();
  } catch (err) {
    console.error('Failed to load init data:', err);
    errorMessage.value = 'فشل تحميل البيانات الأولية للنظام';
  }
};

const submitReceipt = async () => {
  if (totalReceiptAmount.value <= 0) {
    errorMessage.value = 'يجب أن يكون إجمالي مبلغ السند أكبر من الصفر!';
    return;
  }

  if (!form.value.cash_or_bank_account_id) {
    errorMessage.value = 'يرجى اختيار الصندوق أو البنك المرتبط بحساب محاسبي!';
    return;
  }

  saving.value = true;
  errorMessage.value = '';

  try {
    const payload = {
      ...form.value,
      branch_id: form.value.branch_id || null,
      cash_box_id: form.value.cash_box_id || null,
      auto_post: autoPostFlag.value,
    };
    const res = await axios.post('/api/v1/receipts', payload);
    if (res.data.success) {
      alert('تم حفظ سند القبض بنجاح.');
      router.push({ name: 'receipts' });
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'فشل حفظ سند القبض';
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchData();
});
</script>
