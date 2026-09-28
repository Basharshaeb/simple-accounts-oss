<template>
  <div class="min-vh-100 bg-slate-900 text-white d-flex flex-column" style="background-color: #0f172a;">
    <!-- SuperAdmin Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-slate-950 px-4 py-3 border-bottom border-slate-800 shadow-md" style="background-color: #020617;">
      <div class="container-fluid p-0 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-warning bg-opacity-10 p-2 rounded-3 border border-warning-subtle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
            <i class="bi bi-shield-lock-fill fs-3 text-warning"></i>
          </div>
          <div>
            <h4 class="fw-bold text-white mb-0">لوحة تحكم مدير النظام والمنشآت (SuperAdmin)</h4>
            <small class="text-slate-400" style="color: #94a3b8; font-size: 0.8rem;">Platform SuperAdmin Central Command Center</small>
          </div>
        </div>

        <div class="d-flex align-items-center gap-3">
          <button class="btn btn-outline-light btn-sm fw-bold px-3 py-2 rounded-3" @click="goToAccountingApp">
            <i class="bi bi-arrow-right-circle me-1"></i> الانتقال للنظام المحاسبي
          </button>

          <div class="vr bg-secondary mx-1" style="height: 24px;"></div>

          <div class="d-flex align-items-center text-white small gap-2">
            <div class="bg-warning bg-opacity-20 p-2 rounded-circle text-warning">
              <i class="bi bi-person-badge-fill fs-5"></i>
            </div>
            <div>
              <div class="fw-bold text-warning">{{ superAdminUser ? superAdminUser.name : 'مدير النظام' }}</div>
              <div class="text-slate-400" style="color: #94a3b8; font-size: 0.75rem;">{{ superAdminUser ? superAdminUser.email : '' }}</div>
            </div>
          </div>

          <button class="btn btn-sm btn-outline-danger" title="تسجيل الخروج" @click="handleLogout">
            <i class="bi bi-box-arrow-right fs-5"></i>
          </button>
        </div>
      </div>
    </nav>

    <!-- Content Body -->
    <div class="container-fluid p-4 flex-grow-1 bg-light text-dark">
      <!-- Executive SuperAdmin Banner -->
      <div class="card bg-white border-0 shadow-sm mb-4">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                  <i class="bi bi-shield-check me-1"></i>لوحة مدير النظام المركزية
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                  <i class="bi bi-cpu me-1"></i>خادم المنصة متصل (Active)
                </span>
              </div>
              <h3 class="fw-bold text-dark m-0">إدارة المنشآت والشركات (SuperAdmin Control)</h3>
              <p class="text-muted small m-0 mt-1">التحكم في كافة الشركات المسجلة بالنظام، وتفعيل أو إيقاف المنشآت، وتعيين الشركة للعمل التشغيلي</p>
            </div>

            <button class="btn btn-warning fw-bold px-4 py-2 shadow-sm text-dark" @click="openCreateModal">
              <i class="bi bi-plus-circle-fill me-1"></i> إضافة شركة / منشأة جديدة
            </button>
          </div>
        </div>
      </div>

      <!-- KPI Executive Stats -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card border-0 shadow-sm bg-white h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
              <div>
                <small class="text-muted d-block font-weight-bold">إجمالي الشركات المسجلة</small>
                <h3 class="fw-bold text-primary m-0 mt-1">{{ stats.total_companies || companies.length }}</h3>
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
                <h3 class="fw-bold text-success m-0 mt-1">{{ stats.active_companies || companies.filter(c => c.status === 'ACTIVE').length }}</h3>
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
                <small class="text-muted d-block font-weight-bold">الشركات المعطلة</small>
                <h3 class="fw-bold text-danger m-0 mt-1">{{ stats.suspended_companies || companies.filter(c => c.status === 'INACTIVE').length }}</h3>
              </div>
              <div class="bg-danger bg-opacity-10 p-3 rounded-circle text-danger">
                <i class="bi bi-slash-circle fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card border-0 shadow-sm bg-white h-100">
            <div class="card-body p-3 d-flex align-items-center justify-content-between">
              <div>
                <small class="text-muted d-block font-weight-bold">إجمالي مستخدمي المنصة</small>
                <h3 class="fw-bold text-info m-0 mt-1">{{ stats.total_users || 1 }}</h3>
              </div>
              <div class="bg-info bg-opacity-10 p-3 rounded-circle text-info">
                <i class="bi bi-people-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Search & Companies Grid -->
      <div class="card border-0 shadow-sm p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold text-dark m-0"><i class="bi bi-grid-fill me-2 text-warning"></i>قائمة المنشآت والشركات المسجلة</h5>
          <div style="max-width: 320px;" class="w-100">
            <input v-model="searchQuery" type="text" class="form-control form-control-sm" placeholder="بحث باسم الشركة أو كود الشركة..." />
          </div>
        </div>

        <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-warning" role="status"></div>
        </div>

        <div v-else-if="filteredCompanies.length === 0" class="text-center py-5 text-muted">
          لا توجد شركات مسجلة مطابقة للبحث.
        </div>

        <div v-else class="row g-4">
          <div v-for="comp in filteredCompanies" :key="comp.id" class="col-md-6 col-lg-4">
            <div class="card h-100 border-0 shadow-sm" :class="{'border-start border-4 border-warning': auth.currentCompany && auth.currentCompany.id === comp.id}">
              <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                  <div>
                    <h5 class="fw-bold text-primary mb-1">{{ comp.name }}</h5>
                    <span class="badge bg-dark text-white font-monospace">كود الشركة: {{ comp.code }}</span>
                  </div>
                  <span :class="comp.status === 'ACTIVE' ? 'badge bg-success' : 'badge bg-danger'">
                    {{ comp.status === 'ACTIVE' ? 'نشطة' : 'معطلة' }}
                  </span>
                </div>

                <hr class="text-light">

                <div class="small text-muted mb-2">
                  <i class="bi bi-currency-exchange me-1 text-primary"></i>
                  <strong>العملة الأساسية:</strong> {{ comp.base_currency ? comp.base_currency.name + ' (' + comp.base_currency.code + ')' : 'SAR' }}
                </div>
                <div class="small text-muted mb-2">
                  <i class="bi bi-telephone me-1 text-info"></i>
                  <strong>الهاتف:</strong> {{ comp.phone || '-' }}
                </div>
                <div class="small text-muted mb-2">
                  <i class="bi bi-envelope me-1 text-secondary"></i>
                  <strong>البريد الإلكتروني:</strong> {{ comp.email || '-' }}
                </div>
                <div class="small text-muted mb-3">
                  <i class="bi bi-receipt me-1 text-warning"></i>
                  <strong>الرقم الضريبي:</strong> {{ comp.tax_number || '-' }}
                </div>

                <!-- Default Login Credentials Box -->
                <div class="p-3 bg-light rounded border border-warning-subtle">
                  <div class="fw-bold text-dark small mb-1">
                    <i class="bi bi-key-fill text-warning me-1"></i> بيانات الدخول الافتراضية:
                  </div>
                  <div class="small font-monospace text-dark">
                    <div><strong>كود الشركة:</strong> <span class="badge bg-dark">{{ comp.code }}</span></div>
                    <div><strong>البريد الإلكتروني:</strong> <span class="text-primary">{{ comp.company_users && comp.company_users.length > 0 ? comp.company_users[0].user.email : 'admin@accounts.com' }}</span></div>
                    <div><strong>كلمة السر:</strong> <code>password123</code></div>
                  </div>
                </div>
              </div>

              <div class="card-footer bg-white border-top border-light p-3 d-flex justify-content-between align-items-center">
                <button 
                  class="btn btn-sm" 
                  :class="comp.status === 'ACTIVE' ? 'btn-outline-danger' : 'btn-outline-success'" 
                  @click="toggleCompanyStatus(comp)"
                >
                  <i :class="comp.status === 'ACTIVE' ? 'bi bi-slash-circle' : 'bi bi-check-circle'" class="me-1"></i>
                  {{ comp.status === 'ACTIVE' ? 'تعطيل' : 'تنشيط' }}
                </button>

                <button class="btn btn-sm btn-primary fw-bold" @click="selectCompany(comp)">
                  <i class="bi bi-box-arrow-in-right me-1"></i> دخول بيئة الشركة
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Company Modal -->
    <div class="modal fade text-dark" id="companyModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-slate-900 text-white" style="background-color: #0f172a;">
            <h5 class="modal-title fw-bold"><i class="bi bi-building-add me-2 text-warning"></i>إضافة شركة / منشأة جديدة</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form @submit.prevent="saveCompany">
            <div class="modal-body p-4">
              <div v-if="modalError" class="alert alert-danger py-2 small mb-3 border-0 shadow-sm">{{ modalError }}</div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label font-weight-bold">اسم الشركة العربي <span class="text-danger">*</span></label>
                  <input v-model="form.name" type="text" class="form-control" placeholder="مثال: شركة الحلول التقنية المتقدمة" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label font-weight-bold">رمز وكود الشركة <span class="text-danger">*</span></label>
                  <input v-model="form.code" type="text" class="form-control text-uppercase font-monospace" placeholder="مثال: COMP02" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label font-weight-bold">العملة الأساسية للشركة <span class="text-danger">*</span></label>
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
            <div class="modal-footer bg-light">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" class="btn btn-warning text-dark fw-bold px-4" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                حفظ وإنشاء الشركة
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import * as bootstrap from 'bootstrap';
import { useAuthStore } from '../stores/authStore';

const router = useRouter();
const auth = useAuthStore();
const loading = ref(true);
const companies = ref([]);
const currencies = ref([]);
const stats = ref({});
const searchQuery = ref('');
const modalError = ref('');
const saving = ref(false);

const superAdminUser = computed(() => {
  const saved = localStorage.getItem('superadmin_user');
  return saved ? JSON.parse(saved) : null;
});

const filteredCompanies = computed(() => {
  if (!searchQuery.value || !searchQuery.value.trim()) return companies.value;
  const q = searchQuery.value.trim().toLowerCase();
  return companies.value.filter(c => 
    c.name.toLowerCase().includes(q) || c.code.toLowerCase().includes(q)
  );
});

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
    const [cRes, currRes, statsRes] = await Promise.all([
      axios.get('/api/v1/companies'),
      axios.get('/api/v1/currencies'),
      axios.get('/api/v1/superadmin/stats'),
    ]);
    if (cRes.data.success) companies.value = cRes.data.data;
    if (currRes.data.success) {
      currencies.value = currRes.data.data;
      if (currencies.value.length > 0) form.value.base_currency_id = currencies.value[0].id;
    }
    if (statsRes.data.success) {
      stats.value = statsRes.data.data;
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

const toggleCompanyStatus = async (comp) => {
  try {
    const res = await axios.put(`/api/v1/superadmin/companies/${comp.id}/status`);
    if (res.data.success) {
      fetchData();
    }
  } catch (err) {
    alert(err.response?.data?.message || 'فشل تغيير حالة الشركة.');
  }
};

const selectCompany = (comp) => {
  auth.switchCompany(comp);
  router.push('/');
};

const goToAccountingApp = () => {
  router.push('/');
};

const handleLogout = () => {
  localStorage.removeItem('superadmin_token');
  localStorage.removeItem('superadmin_user');
  router.push('/superadmin/login');
};

onMounted(() => {
  fetchData();
});
</script>
