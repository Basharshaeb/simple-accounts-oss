<template>
  <div>
    <!-- Executive Admin Banner -->
    <div class="card bg-white border-0 shadow-sm mb-4">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                <i class="bi bi-shield-check me-1"></i>لوحة الإدارة المركزية
              </span>
              <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                <i class="bi bi-cpu me-1"></i>المنصة نشطة
              </span>
            </div>
            <h3 class="fw-bold text-dark m-0">إدارة الشركات والمنشآت (System Administration)</h3>
            <p class="text-muted small m-0 mt-1">التحكم في المنشآت والشركات، وإنشاء شركات جديدة، وتعيين الشركة الحالية لإدارة الحسابات والعمليات</p>
          </div>

          <button class="btn btn-primary fw-bold px-4 py-2 shadow-sm" @click="openCreateModal">
            <i class="bi bi-plus-circle me-1"></i> إضافة شركة / منشأة جديدة
          </button>
        </div>
      </div>
    </div>

    <!-- Admin KPI Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white h-100">
          <div class="card-body p-3 d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted d-block font-weight-bold">إجمالي الشركات المسجلة</small>
              <h3 class="fw-bold text-primary m-0 mt-1">{{ companies.length }}</h3>
            </div>
            <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
              <i class="bi bi-building fs-3"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white h-100">
          <div class="card-body p-3 d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted d-block font-weight-bold">الشركات الفعالة</small>
              <h3 class="fw-bold text-success m-0 mt-1">{{ companies.filter(c => c.status === 'ACTIVE').length }}</h3>
            </div>
            <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success">
              <i class="bi bi-check-circle fs-3"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white h-100">
          <div class="card-body p-3 d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted d-block font-weight-bold">الشركة الحالية النشطة</small>
              <h6 class="fw-bold text-dark m-0 mt-1 text-truncate" style="max-width: 140px;">
                {{ auth.currentCompany ? auth.currentCompany.name : 'غير محددة' }}
              </h6>
            </div>
            <div class="bg-warning bg-opacity-10 p-3 rounded-circle text-warning">
              <i class="bi bi-star-fill fs-3"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white h-100">
          <div class="card-body p-3 d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted d-block font-weight-bold">عزل البيانات والأمان</small>
              <span class="badge bg-success mt-2">عزل تام (Secure Multi-Tenant)</span>
            </div>
            <div class="bg-info bg-opacity-10 p-3 rounded-circle text-info">
              <i class="bi bi-lock-fill fs-3"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Companies Grid -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="companies.length === 0" class="card p-5 text-center text-muted border-0 shadow-sm">
      لا توجد شركات مسجلة.
    </div>

    <div v-else class="row g-4">
      <div v-for="comp in companies" :key="comp.id" class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="fw-bold text-primary mb-1">{{ comp.name }}</h5>
                <span class="badge bg-light text-dark border font-monospace">كود الشركة: {{ comp.code }}</span>
              </div>
              <span :class="comp.status === 'ACTIVE' ? 'badge bg-success' : 'badge bg-secondary'">
                {{ comp.status === 'ACTIVE' ? 'نشطة' : 'معطلة' }}
              </span>
            </div>

            <hr class="text-light">

            <div class="small text-muted mb-2">
              <i class="bi bi-currency-exchange me-1"></i>
              <strong>العملة الأساسية:</strong> {{ comp.base_currency ? comp.base_currency.name + ' (' + comp.base_currency.code + ')' : 'SAR' }}
            </div>
            <div class="small text-muted mb-2">
              <i class="bi bi-telephone me-1"></i>
              <strong>الهاتف:</strong> {{ comp.phone || '-' }}
            </div>
            <div class="small text-muted mb-2">
              <i class="bi bi-envelope me-1"></i>
              <strong>البريد:</strong> {{ comp.email || '-' }}
            </div>
            <div class="small text-muted">
              <i class="bi bi-receipt me-1"></i>
              <strong>الرقم الضريبي:</strong> {{ comp.tax_number || '-' }}
            </div>

            <!-- Default Login Credentials Box -->
            <div class="mt-3 p-3 bg-light rounded border border-warning-subtle">
              <div class="fw-bold text-dark small mb-1">
                <i class="bi bi-key-fill text-warning me-1"></i> بيانات الدخول الافتراضية:
              </div>
              <div class="small font-monospace text-dark">
                <div><strong>كود الشركة:</strong> <span class="badge bg-dark">{{ comp.code }}</span></div>
                <div><strong>البريد:</strong> <span class="text-primary">{{ comp.company_users && comp.company_users.length > 0 ? comp.company_users[0].user.email : 'admin@accounts.com' }}</span></div>
                <div><strong>كلمة السر:</strong> <code>password123</code></div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-white border-top border-light p-3 text-end">
            <button class="btn btn-sm btn-outline-primary" @click="selectCompany(comp)">
              <i class="bi bi-check-circle me-1"></i> اختيار كشركة حالية
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Company Modal -->
    <div class="modal fade" id="companyModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">إضافة شركة جديدة</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form @submit.prevent="saveCompany">
            <div class="modal-body">
              <div v-if="modalError" class="alert alert-danger py-2 small mb-3">{{ modalError }}</div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label font-weight-bold">اسم الشركة العربي</label>
                  <input v-model="form.name" type="text" class="form-control" placeholder="مثال: شركة الحلول المتقدمة" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label font-weight-bold">رمز وكود الشركة</label>
                  <input v-model="form.code" type="text" class="form-control text-uppercase" placeholder="مثال: COMP02" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label font-weight-bold">العملة الأساسية للشركة</label>
                  <select v-model="form.base_currency_id" class="form-select" required>
                    <option v-for="curr in currencies" :key="curr.id" :value="curr.id">
                      {{ curr.code }} - {{ curr.name }}
                    </option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label font-weight-bold">الرقم الضريبي (VAT)</label>
                  <input v-model="form.tax_number" type="text" class="form-control" placeholder="300000000000003" />
                </div>
                <div class="col-md-6">
                  <label class="form-label font-weight-bold">رقم الهاتف</label>
                  <input v-model="form.phone" type="text" class="form-control" placeholder="0112345678" />
                </div>
                <div class="col-md-6">
                  <label class="form-label font-weight-bold">البريد الإلكتروني للشركة</label>
                  <input v-model="form.email" type="email" class="form-control" placeholder="info@company.com" />
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" class="btn btn-primary fw-bold" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                إنشاء الشركة
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
import { useAuthStore } from '../stores/authStore';

const auth = useAuthStore();
const loading = ref(true);
const companies = ref([]);
const currencies = ref([]);
const modalError = ref('');
const saving = ref(false);

const form = ref({
  name: '',
  code: '',
  base_currency_id: '',
  tax_number: '',
  phone: '',
  email: '',
});

const fetchData = async () => {
  loading.value = true;
  try {
    const [cRes, currRes] = await Promise.all([
      axios.get('/api/v1/companies'),
      axios.get('/api/v1/currencies')
    ]);
    if (cRes.data.success) companies.value = cRes.data.data;
    if (currRes.data.success) {
      currencies.value = currRes.data.data;
      if (currencies.value.length > 0) form.value.base_currency_id = currencies.value[0].id;
    }
  } catch (err) {
    console.error(err);
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  modalError.value = '';
  form.value = {
    name: '',
    code: 'COMP' + Math.floor(100 + Math.random() * 900),
    base_currency_id: currencies.value.length > 0 ? currencies.value[0].id : '',
    tax_number: '',
    phone: '',
    email: '',
  };
  const modalEl = document.getElementById('companyModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

const saveCompany = async () => {
  modalError.value = '';

  // Frontend Validation Checks
  if (!form.value.name || form.value.name.trim().length < 3) {
    modalError.value = 'يرجى إدخال اسم شركة صحيح يتكون من 3 أحرف على الأقل.';
    return;
  }

  if (!form.value.code || form.value.code.trim().length < 2) {
    modalError.value = 'يرجى إدخال كود شركة صحيح (أحرف إنجليزية ورسائل دون مسافات).';
    return;
  }

  if (!form.value.base_currency_id) {
    modalError.value = 'يرجى اختيار العملة الأساسية للشركة.';
    return;
  }

  saving.value = true;
  try {
    const res = await axios.post('/api/v1/companies', form.value);
    if (res.data.success) {
      const modalEl = document.getElementById('companyModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      fetchData();
    }
  } catch (err) {
    if (err.response?.data?.errors) {
      const firstErrKey = Object.keys(err.response.data.errors)[0];
      modalError.value = err.response.data.errors[firstErrKey][0];
    } else {
      modalError.value = err.response?.data?.message || 'فشل إنشاء الشركة وتدقيق البيانات.';
    }
  } finally {
    saving.value = false;
  }
};

const selectCompany = (comp) => {
  auth.switchCompany(comp);
};

onMounted(() => {
  fetchData();
});
</script>
