<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">إدارة المستخدمين والصلاحيات</h3>
        <p class="text-muted small m-0">ربط وتحديد صلاحيات المستخدمين داخل الشركة الحالية وتوزيع الأدوار</p>
      </div>

      <button class="btn btn-primary" @click="openCreateModal">
        <i class="bi bi-person-plus me-1"></i> إضافة مستخدم للشركة
      </button>
    </div>

    <div v-if="pageError" class="alert alert-danger py-2 small mb-3">
      <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ pageError }}
    </div>

    <!-- Users Table -->
    <div class="card p-4 bg-white border-0 shadow-sm">
      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
      </div>

      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th class="ps-4">المستخدم</th>
              <th>البريد الإلكتروني</th>
              <th>دور المستخدم بالشركة</th>
              <th>الصلاحية الإضافية</th>
              <th>آخر دخول</th>
              <th class="pe-4 text-end">الحالة</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cu in companyUsers" :key="cu.id">
              <td class="ps-4">
                <div class="d-flex align-items-center">
                  <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle me-2">
                    <i class="bi bi-person-fill"></i>
                  </div>
                  <div>
                    <strong class="d-block">{{ cu.user ? cu.user.name : '-' }}</strong>
                    <small v-if="cu.is_online" class="text-success">
                      <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>متصل الآن
                    </small>
                    <small v-else class="text-muted">
                      <i class="bi bi-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>غير متصل<template v-if="cu.last_seen_at"> · آخر ظهور {{ formatDateTime(cu.last_seen_at) }}</template>
                    </small>
                  </div>
                </div>
              </td>
              <td class="font-monospace">{{ cu.user ? cu.user.email : '-' }}</td>
              <td>
                <span :class="getRoleBadgeClass(cu.role)">
                  {{ getRoleLabel(cu.role) }}
                </span>
              </td>
              <td>
                <span class="badge bg-light text-dark border">
                  {{ cu.role_obj ? cu.role_obj.display_name : 'افتراضي' }}
                </span>
              </td>
              <td class="small" :class="cu.last_login_at ? 'text-dark' : 'text-muted'">
                <div>{{ cu.last_login_at ? formatDateTime(cu.last_login_at) : 'لم يسجل الدخول بعد' }}</div>
                <div v-if="cu.last_login_ip" class="font-monospace text-muted" dir="ltr">{{ cu.last_login_ip }}</div>
                <button v-if="cu.last_login_at" type="button" class="btn btn-link btn-sm p-0" @click="openLoginsModal(cu)">
                  سجل الدخول
                </button>
              </td>
              <td class="pe-4 text-end text-nowrap">
                <span :class="cu.is_active ? 'badge bg-success-subtle text-success' : 'badge bg-danger-subtle text-danger'">
                  {{ cu.is_active ? 'نشط' : 'موقوف' }}
                </span>
                <button
                  v-if="cu.user_id !== auth.user?.id"
                  type="button"
                  class="btn btn-sm ms-2"
                  :class="cu.is_active ? 'btn-outline-danger' : 'btn-outline-success'"
                  :disabled="togglingId === cu.id"
                  @click="toggleStatus(cu)"
                >
                  <span v-if="togglingId === cu.id" class="spinner-border spinner-border-sm"></span>
                  <template v-else>{{ cu.is_active ? 'إيقاف' : 'تفعيل' }}</template>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Login History Modal -->
    <div class="modal fade" id="loginsModal" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">سجل الدخول — {{ loginsFor?.user?.name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div v-if="loginsLoading" class="text-center py-4">
              <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div v-else-if="loginsError" class="alert alert-danger py-2 small m-0">{{ loginsError }}</div>
            <p v-else-if="logins.length === 0" class="text-muted text-center m-0">لا توجد عمليات دخول مسجلة لهذه الشركة.</p>
            <div v-else class="table-responsive">
              <table class="table table-sm align-middle m-0">
                <thead class="table-light">
                  <tr>
                    <th>التاريخ والوقت</th>
                    <th>عنوان IP</th>
                    <th>المتصفح / الجهاز</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="log in logins" :key="log.id">
                    <td class="text-nowrap">{{ formatDateTime(log.created_at) }}</td>
                    <td class="font-monospace" dir="ltr">{{ log.ip_address || '-' }}</td>
                    <td class="small text-muted" dir="ltr">{{ log.user_agent || '-' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-if="logins.length === 50" class="text-muted small mt-2 mb-0">يُعرض آخر 50 عملية دخول.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">إضافة مستخدم وتحديد الصلاحيات</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <form @submit.prevent="saveUser">
            <div class="modal-body">
              <div v-if="modalError" class="alert alert-danger py-2 small mb-3">{{ modalError }}</div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">الاسم الكامل</label>
                <input v-model="form.name" type="text" class="form-control" placeholder="اسم المستخدم" required />
              </div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">البريد الإلكتروني</label>
                <input v-model="form.email" type="email" class="form-control" placeholder="user@company.com" required />
              </div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">كلمة المرور</label>
                <input v-model="form.password" type="password" class="form-control" placeholder="••••••••" required />
              </div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">دور المستخدم</label>
                <select v-model="form.role" class="form-select" required>
                  <option value="ADMIN">مدير نظام - كامل الصلاحيات</option>
                  <option value="ACCOUNTANT">محاسب - إضافة وتعديل واعتماد القيود والسندات</option>
                  <option value="AUDITOR">مدقق محاسبي - مراجعة واعتماد فقط</option>
                  <option value="VIEWER">مستعرض - استعراض واستخراج التقارير فقط</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" class="btn btn-primary fw-bold" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                إضافة المستخدم
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import * as bootstrap from 'bootstrap';
import { useAuthStore } from '../stores/authStore';

const auth = useAuthStore();
const loading = ref(true);
const companyUsers = ref([]);
const roles = ref([]);
const modalError = ref('');
const pageError = ref('');
const saving = ref(false);
const togglingId = ref(null);

const dateTimeFormatter = new Intl.DateTimeFormat('ar-SA-u-ca-gregory', { dateStyle: 'medium', timeStyle: 'short' });

const formatDateTime = (value) => dateTimeFormatter.format(new Date(value));

const loginsFor = ref(null);
const logins = ref([]);
const loginsLoading = ref(false);
const loginsError = ref('');

const openLoginsModal = async (companyUser) => {
  loginsFor.value = companyUser;
  logins.value = [];
  loginsError.value = '';
  loginsLoading.value = true;
  new bootstrap.Modal(document.getElementById('loginsModal')).show();
  try {
    const res = await axios.get(`/api/v1/users/${companyUser.id}/logins`);
    logins.value = res.data.data;
  } catch (err) {
    loginsError.value = err.response?.data?.message || 'تعذر تحميل سجل الدخول';
  } finally {
    loginsLoading.value = false;
  }
};

const toggleStatus = async (companyUser) => {
  togglingId.value = companyUser.id;
  pageError.value = '';
  try {
    const res = await axios.put(`/api/v1/users/${companyUser.id}/status`);
    if (res.data.success) {
      companyUser.is_active = res.data.data.is_active;
    }
  } catch (err) {
    pageError.value = err.response?.data?.message || 'فشل تغيير حالة المستخدم';
  } finally {
    togglingId.value = null;
  }
};

const form = ref({
  name: '',
  email: '',
  password: '',
  role: 'ACCOUNTANT',
  role_id: null,
});

const getRoleBadgeClass = (role) => {
  switch (role) {
    case 'ADMIN': return 'badge bg-danger';
    case 'ACCOUNTANT': return 'badge bg-primary';
    case 'AUDITOR': return 'badge bg-warning text-dark';
    case 'VIEWER': return 'badge bg-info text-dark';
    default: return 'badge bg-secondary';
  }
};

const getRoleLabel = (role) => {
  switch (role) {
    case 'ADMIN': return 'مدير نظام';
    case 'ACCOUNTANT': return 'محاسب';
    case 'AUDITOR': return 'مدقق محاسبي';
    case 'VIEWER': return 'مستعرض';
    default: return role;
  }
};

const fetchUsers = async ({ silent = false } = {}) => {
  if (!silent) loading.value = true;
  try {
    const res = await axios.get('/api/v1/users');
    if (res.data.success) {
      companyUsers.value = res.data.data;
      roles.value = res.data.roles;
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
    email: '',
    password: 'password123',
    role: 'ACCOUNTANT',
    role_id: null,
  };
  const modalEl = document.getElementById('userModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

const saveUser = async () => {
  saving.value = true;
  modalError.value = '';
  try {
    const res = await axios.post('/api/v1/users', form.value);
    if (res.data.success) {
      const modalEl = document.getElementById('userModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      fetchUsers();
    }
  } catch (err) {
    modalError.value = err.response?.data?.message || 'فشل إضافة المستخدم';
  } finally {
    saving.value = false;
  }
};

let presenceTimer = null;

onMounted(() => {
  fetchUsers();
  presenceTimer = setInterval(() => fetchUsers({ silent: true }), 60000);
});

onUnmounted(() => clearInterval(presenceTimer));
</script>
