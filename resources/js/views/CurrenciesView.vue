<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">إدارة العملات وأسعار الصرف</h3>
        <p class="text-muted small m-0">إدارة عملة النظام الأساسية والعملات الأجنبية المحاسبية وإدخال أسعار الصرف</p>
      </div>

      <button class="btn btn-primary" @click="openCreateModal">
        <i class="bi bi-plus-lg me-1"></i> إضافة عملة فرعية جديدة
      </button>
    </div>


    <!-- Currencies Cards/Table -->
    <div class="card p-4 bg-white border-0 shadow-sm">
      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
      </div>

      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th class="ps-4">رمز العملة</th>
              <th>اسم العملة</th>
              <th>الرمز / العلامة</th>
              <th>الخانة العشرية</th>
              <th>نوع العملة</th>
              <th>سعر الصرف الحالي (مقابل العملة الأساسية)</th>
              <th class="pe-4 text-end">الإجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="curr in currencies" :key="curr.id">
              <td class="ps-4 font-monospace fw-bold fs-5 text-primary">{{ curr.code }}</td>
              <td class="fw-bold">{{ curr.name }}</td>
              <td><span class="badge bg-light text-dark border fs-6">{{ curr.symbol }}</span></td>
              <td>{{ curr.decimal_places }} خانات</td>
              <td>
                <span :class="curr.is_base ? 'badge bg-success' : 'badge bg-secondary'">
                  {{ curr.is_base ? 'عملة أساسية (Base)' : 'عملة فرعية (Foreign)' }}
                </span>
              </td>
              <td>
                <span v-if="curr.is_base" class="fw-bold text-success font-monospace">1.000000 (ثابت)</span>
                <span v-else class="fw-bold text-dark font-monospace">{{ Number(curr.exchange_rate).toFixed(6) }}</span>
              </td>
              <td class="pe-4 text-end">
                <button class="btn btn-sm btn-outline-secondary" @click="openEditModal(curr)">
                  <i class="bi bi-pencil me-1"></i> تعديل سعر الصرف
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Currency Modal -->
    <div class="modal fade" id="currencyModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">{{ isEditing ? 'تعديل بيانات وسعر صرف العملة' : 'إضافة عملة فرعية جديدة' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form @submit.prevent="saveCurrency">
            <div class="modal-body">
              <div v-if="modalError" class="alert alert-danger py-2 small mb-3">{{ modalError }}</div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">رمز العملة (Code)</label>
                <input v-model="form.code" type="text" class="form-control text-uppercase" placeholder="مثال: USD" :disabled="isEditing && form.is_base" required />
              </div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">اسم العملة بالعربية</label>
                <input v-model="form.name" type="text" class="form-control" placeholder="مثال: دولار أمريكي" required />
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label font-weight-bold">رمز العلامة (Symbol)</label>
                  <input v-model="form.symbol" type="text" class="form-control" placeholder="$" required />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label font-weight-bold">الخانات العشرية</label>
                  <input v-model.number="form.decimal_places" type="number" min="0" max="6" class="form-control" required />
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">سعر الصرف (Exchange Rate)</label>
                <input 
                  v-model.number="form.exchange_rate" 
                  type="number" 
                  step="0.000001" 
                  class="form-control font-monospace fw-bold" 
                  :disabled="form.is_base" 
                  required 
                />
                <small v-if="form.is_base" class="text-success d-block mt-1"><i class="bi bi-lock me-1"></i>العملة الأساسية للنظام سعر صرفها دائماً 1.000000</small>
                <small v-else class="text-muted d-block mt-1">يجب أن يكون سعر الصرف أكبر من 0 (مثال: 3.750000).</small>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" class="btn btn-primary fw-bold" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                {{ isEditing ? 'حفظ التعديلات' : 'إضافة العملة' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import * as bootstrap from 'bootstrap';

const loading = ref(true);
const currencies = ref([]);
const isEditing = ref(false);
const modalError = ref('');
const saving = ref(false);

const form = ref({
  id: null,
  code: '',
  name: '',
  symbol: '',
  decimal_places: 2,
  is_base: false,
  exchange_rate: 1.0,
});

const fetchCurrencies = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/v1/currencies');
    if (res.data.success) {
      currencies.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to fetch currencies', err);
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  isEditing.value = false;
  modalError.value = '';
  form.value = {
    id: null,
    code: '',
    name: '',
    symbol: '',
    decimal_places: 2,
    is_base: false,
    exchange_rate: 3.75,
  };
  const modalEl = document.getElementById('currencyModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

const openEditModal = (curr) => {
  isEditing.value = true;
  modalError.value = '';
  form.value = {
    id: curr.id,
    code: curr.code,
    name: curr.name,
    symbol: curr.symbol,
    decimal_places: curr.decimal_places,
    is_base: curr.is_base,
    exchange_rate: curr.is_base ? 1.0 : (curr.exchange_rate || 1.0),
  };
  const modalEl = document.getElementById('currencyModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

const saveCurrency = async () => {
  if (!form.value.is_base && form.value.exchange_rate <= 0) {
    modalError.value = 'سعر الصرف للعملة الفرعية يجب أن يكون قيمة أكبر من 0';
    return;
  }

  saving.value = true;
  modalError.value = '';
  try {
    if (form.value.is_base) {
      form.value.exchange_rate = 1.0;
    }

    let res;
    if (isEditing.value && form.value.id) {
      res = await axios.put(`/api/v1/currencies/${form.value.id}`, form.value);
    } else {
      res = await axios.post('/api/v1/currencies', form.value);
    }

    if (res.data.success) {
      const modalEl = document.getElementById('currencyModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      fetchCurrencies();
    }
  } catch (err) {
    modalError.value = err.response?.data?.message || 'فشل حفظ العملة';
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchCurrencies();
});
</script>
