<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">إدارة الفروع والصناديق والبنوك (Branches, Cash Boxes & Banks)</h3>
        <p class="text-muted small m-0">تنظيم الهيكل التنفيذي للفروع والحسابات والخزائن النقدية والبنوك التابعة للمؤسسة</p>
      </div>

      <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" @click="openBranchModal">
          <i class="bi bi-building-add me-1"></i> إضافة فرع جديد
        </button>
        <button class="btn btn-success btn-sm" @click="openCashBoxModal">
          <i class="bi bi-safe me-1"></i> إضافة صندوق / بنك
        </button>
      </div>
    </div>

    <div class="row g-4">
      <!-- Branches List Column -->
      <div class="col-md-6">
        <div class="card p-4 bg-white h-100">
          <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
            <h5 class="fw-bold m-0 text-primary"><i class="bi bi-buildings me-2"></i>فروع الشركة (Branches)</h5>
            <span class="badge bg-primary fs-6">{{ branches.length }} فروع</span>
          </div>

          <div v-if="loading" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
          <div v-else-if="branches.length === 0" class="text-center text-muted py-4">لا توجد فروع مسجلة.</div>
          <div v-else class="list-group">
            <div v-for="b in branches" :key="b.id" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <div>
                <span class="fw-bold text-dark d-block">{{ b.code }} - {{ b.name }}</span>
                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i> {{ b.address || 'العنوان غير محدد' }} | <i class="bi bi-telephone me-1"></i> {{ b.phone || '-' }}</small>
              </div>
              <span class="badge bg-success-subtle text-success">نشط</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Cash Boxes List Column -->
      <div class="col-md-6">
        <div class="card p-4 bg-white h-100">
          <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
            <h5 class="fw-bold m-0 text-success"><i class="bi bi-safe2 me-2"></i>الصناديق والبنوك (Cash Boxes & Banks)</h5>
            <span class="badge bg-success fs-6">{{ cashBoxes.length }} صناديق/بنوك</span>
          </div>

          <div v-if="loading" class="text-center py-4"><div class="spinner-border text-success"></div></div>
          <div v-else-if="cashBoxes.length === 0" class="text-center text-muted py-4">لا توجد صناديق نقدية مسجلة.</div>
          <div v-else class="list-group">
            <div v-for="cb in cashBoxes" :key="cb.id" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <div>
                <div class="d-flex align-items-center gap-2">
                  <span class="fw-bold text-dark d-block">{{ cb.code }} - {{ cb.name }}</span>
                  <span v-if="cb.is_default" class="badge bg-warning text-dark small"><i class="bi bi-star-fill me-1"></i>افتراضي</span>
                </div>
                <small class="text-muted">
                  <i class="bi bi-building me-1"></i> {{ cb.branch ? cb.branch.name : 'بدون فرع' }} | 
                  <i class="bi bi-person me-1"></i> الأمين: {{ cb.keeper_name || 'غير محدد' }}
                </small>
                <div class="small text-primary mt-1">
                  <i class="bi bi-diagram-2 me-1"></i> الحساب المحاسبي المرتبط: 
                  <strong>{{ cb.account ? `${cb.account.code} - ${cb.account.name}` : '-' }}</strong>
                </div>
              </div>
              <span class="badge bg-success-subtle text-success">نشط</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Branch Modal -->
    <div class="modal fade" id="branchModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title fw-bold"><i class="bi bi-building-add me-2"></i>إضافة فرع جديد</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form @submit.prevent="saveBranch">
            <div class="modal-body">
              <div v-if="branchError" class="alert alert-danger py-2 small mb-3">{{ branchError }}</div>
              <div class="mb-3">
                <label class="form-label font-weight-bold">كود الفرع (Code)</label>
                <input v-model="branchForm.code" type="text" class="form-control" placeholder="مثال: BR-03" required />
              </div>
              <div class="mb-3">
                <label class="form-label font-weight-bold">اسم الفرع</label>
                <input v-model="branchForm.name" type="text" class="form-control" placeholder="اسم الفرع" required />
              </div>
              <div class="mb-3">
                <label class="form-label font-weight-bold">رقم الهاتف</label>
                <input v-model="branchForm.phone" type="text" class="form-control" placeholder="+96611..." />
              </div>
              <div class="mb-3">
                <label class="form-label font-weight-bold">العنوان</label>
                <textarea v-model="branchForm.address" class="form-control" rows="2" placeholder="تفاصيل موقع الفرع"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" class="btn btn-primary fw-bold" :disabled="savingBranch">حفظ الفرع</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Cash Box Modal -->
    <div class="modal fade" id="cashBoxModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title fw-bold"><i class="bi bi-safe me-2"></i>إضافة صندوق نقدية جديد</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <form @submit.prevent="saveCashBox">
            <div class="modal-body">
              <div v-if="cashBoxError" class="alert alert-danger py-2 small mb-3">{{ cashBoxError }}</div>
              <div class="mb-3">
                <label class="form-label font-weight-bold">الفرع التابع له <span class="text-danger">*</span></label>
                <select v-model="cashBoxForm.branch_id" class="form-select" required>
                  <option value="" disabled>اختر الفرع التابع له...</option>
                  <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} - {{ b.name }}</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label font-weight-bold">كود الصندوق / البنك <span class="text-danger">*</span></label>
                <input v-model="cashBoxForm.code" type="text" class="form-control" placeholder="مثال: CB-03 أو BK-01" required />
              </div>
              <div class="mb-3">
                <label class="form-label font-weight-bold">اسم الصندوق / الخزينة / البنك <span class="text-danger">*</span></label>
                <input v-model="cashBoxForm.name" type="text" class="form-control" placeholder="اسم الصندوق أو البنك" required />
              </div>
              <div class="mb-3">
                <label class="form-label font-weight-bold">الحساب المحاسبي المرتبط بالشجرة (GL Account) <span class="text-danger">*</span></label>
                <select v-model="cashBoxForm.account_id" class="form-select" required>
                  <option value="" disabled>اختر حساب النقدية/البنك من الشجرة المحاسبية...</option>
                  <option v-for="acc in cashAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label font-weight-bold">اسم أمين الصندوق / المسئول</label>
                <input v-model="cashBoxForm.keeper_name" type="text" class="form-control" placeholder="اسم الموظف المسؤول" />
              </div>
              <div class="form-check mb-3">
                <input v-model="cashBoxForm.is_default" type="checkbox" class="form-check-input" id="isDefaultBox">
                <label class="form-check-label fw-bold text-success" for="isDefaultBox">
                  <i class="bi bi-star-fill text-warning me-1"></i> تعيين كـ (صندوق/بنك افتراضي) للنظام
                </label>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" class="btn btn-success fw-bold" :disabled="savingCashBox">حفظ الصندوق</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import * as bootstrap from 'bootstrap';


const loading = ref(true);
const branches = ref([]);
const cashBoxes = ref([]);
const accounts = ref([]);

const branchError = ref('');
const savingBranch = ref(false);
const branchForm = ref({ code: '', name: '', phone: '', address: '' });

const cashBoxError = ref('');
const savingCashBox = ref(false);
const cashBoxForm = ref({ branch_id: '', code: '', name: '', account_id: '', keeper_name: '', is_default: false });

const cashAccounts = computed(() => accounts.value.filter(a => a.code.startsWith('111') || a.code.startsWith('112') || a.name.includes('صندوق') || a.name.includes('بنك')));

const fetchData = async () => {
  loading.value = true;
  try {
    const [bRes, cbRes, aRes] = await Promise.all([
      axios.get('/api/v1/branches'),
      axios.get('/api/v1/cash-boxes'),
      axios.get('/api/v1/accounts?tree=false&is_postable=true'),
    ]);

    if (bRes.data.success) branches.value = bRes.data.data;
    if (cbRes.data.success) cashBoxes.value = cbRes.data.data;
    if (aRes.data.success) accounts.value = aRes.data.data;
  } catch (err) {
    console.error('Failed to load data:', err);
  } finally {
    loading.value = false;
  }
};

const openBranchModal = () => {
  branchError.value = '';
  branchForm.value = { code: 'BR-0' + (branches.value.length + 1), name: '', phone: '', address: '' };
  const modalEl = document.getElementById('branchModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

const saveBranch = async () => {
  savingBranch.value = true;
  branchError.value = '';
  try {
    const res = await axios.post('/api/v1/branches', branchForm.value);
    if (res.data.success) {
      const modalEl = document.getElementById('branchModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      fetchData();
    }
  } catch (err) {
    branchError.value = err.response?.data?.message || 'فشل حفظ الفرع';
  } finally {
    savingBranch.value = false;
  }
};

const openCashBoxModal = () => {
  cashBoxError.value = '';
  const defaultBranch = branches.value.length > 0 ? branches.value[0].id : '';
  cashBoxForm.value = { branch_id: defaultBranch, code: 'CB-0' + (cashBoxes.value.length + 1), name: '', account_id: '', keeper_name: '', is_default: false };
  const modalEl = document.getElementById('cashBoxModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

const saveCashBox = async () => {
  savingCashBox.value = true;
  cashBoxError.value = '';
  try {
    const res = await axios.post('/api/v1/cash-boxes', cashBoxForm.value);
    if (res.data.success) {
      const modalEl = document.getElementById('cashBoxModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      fetchData();
    }
  } catch (err) {
    cashBoxError.value = err.response?.data?.message || 'فشل حفظ الصندوق';
  } finally {
    savingCashBox.value = false;
  }
};

onMounted(() => {
  fetchData();
});
</script>
