<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">السنوات والفترات المالية (Fiscal Years & Periods)</h3>
        <p class="text-muted small m-0">إدارة السنوات المالية وإقفال الفترات المحاسبية الشهرية للشركة</p>
      </div>

      <button class="btn btn-primary" @click="openCreateModal">
        <i class="bi bi-plus-lg me-1"></i> فتح سنة مالية جديدة
      </button>
    </div>

    <!-- Alert Banner -->
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-4">
      <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
      <div>
        <strong>تنبيه محاسبي:</strong> يتم فتح الفترات الشهرية الـ 12 تلقائياً عند إنشاء السنة المالية. الفترات المغلقة تمنع إضافة أو تعديل أي قيود يومية داخل نطاقها الزمني.
      </div>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="fiscalYears.length === 0" class="card p-5 text-center text-muted">
      <i class="bi bi-calendar-x fs-1 mb-2 text-secondary"></i>
      <h5>لا توجد سنوات مالية مسجلة بعد</h5>
      <p class="small">قم بإضافة سنة مالية جديدة للبدء في التسجيل المحاسبي.</p>
    </div>

    <div v-else>
      <div v-for="fy in fiscalYears" :key="fy.id" class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom border-light">
          <div class="d-flex align-items-center gap-3">
            <span class="fs-4 fw-bold text-primary">السنة المالية {{ fy.year }}</span>
            <span :class="fy.status === 'OPEN' ? 'badge bg-success' : 'badge bg-secondary'">
              {{ fy.status === 'OPEN' ? 'مفتوحة' : 'مغلقة' }}
            </span>
            <span class="text-muted small">
              <i class="bi bi-calendar3 me-1"></i>
              من {{ fy.start_date }} إلى {{ fy.end_date }}
            </span>
          </div>

          <button class="btn btn-sm btn-outline-secondary" @click="toggleYear(fy.id)">
            <i :class="expandedYears.includes(fy.id) ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"></i>
            {{ expandedYears.includes(fy.id) ? 'إخفاء الفترات' : 'عرض الفترات الشهرية (12)' }}
          </button>
        </div>

        <div v-if="expandedYears.includes(fy.id)" class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">رقم الفترة</th>
                  <th>اسم الفترة</th>
                  <th>تاريخ البداية</th>
                  <th>تاريخ النهاية</th>
                  <th>الحالة</th>
                  <th class="pe-4 text-end">الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="period in fy.periods" :key="period.id">
                  <td class="ps-4 font-monospace fw-bold">#{{ period.period_number }}</td>
                  <td class="fw-bold">{{ period.name }}</td>
                  <td>{{ period.start_date }}</td>
                  <td>{{ period.end_date }}</td>
                  <td>
                    <span :class="period.status === 'OPEN' ? 'badge bg-success-subtle text-success' : 'badge bg-secondary-subtle text-secondary'">
                      {{ period.status === 'OPEN' ? 'مفتوحة' : 'مغلقة' }}
                    </span>
                  </td>
                  <td class="pe-4 text-end">
                    <button 
                      v-if="period.status === 'OPEN'"
                      class="btn btn-sm btn-outline-danger"
                      :disabled="closingPeriodId === period.id"
                      @click="closePeriod(period.id)"
                    >
                      <span v-if="closingPeriodId === period.id" class="spinner-border spinner-border-sm me-1"></span>
                      <i v-else class="bi bi-lock me-1"></i>
                      إقفال الفترة
                    </button>
                    <span v-else class="text-muted small"><i class="bi bi-check-all me-1"></i>مقفلة</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Fiscal Year Modal -->
    <div class="modal fade" id="fiscalYearModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">فتح سنة مالية جديدة</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form @submit.prevent="saveFiscalYear">
            <div class="modal-body">
              <div v-if="modalError" class="alert alert-danger small py-2">{{ modalError }}</div>

              <div class="mb-3">
                <label class="form-label font-weight-bold">السنة المالية</label>
                <input v-model.number="form.year" type="number" class="form-control" min="2000" max="2100" required @change="updateDates" />
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label font-weight-bold">تاريخ البداية</label>
                  <input v-model="form.start_date" type="date" class="form-control" required />
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label font-weight-bold">تاريخ النهاية</label>
                  <input v-model="form.end_date" type="date" class="form-control" required />
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
              <button type="submit" class="btn btn-primary fw-bold" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                إنشاء السنة المالية
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
const fiscalYears = ref([]);
const expandedYears = ref([]);
const modalError = ref('');
const saving = ref(false);
const closingPeriodId = ref(null);

const currentYear = new Date().getFullYear();
const form = ref({
  year: currentYear,
  start_date: `${currentYear}-01-01`,
  end_date: `${currentYear}-12-31`,
});

const updateDates = () => {
  if (form.value.year) {
    form.value.start_date = `${form.value.year}-01-01`;
    form.value.end_date = `${form.value.year}-12-31`;
  }
};

const fetchFiscalYears = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/v1/fiscal-years');
    if (res.data.success) {
      fiscalYears.value = res.data.data;
      if (fiscalYears.value.length > 0) {
        expandedYears.value = [fiscalYears.value[0].id];
      }
    }
  } catch (err) {
    console.error('Failed to load fiscal years', err);
  } finally {
    loading.value = false;
  }
};

const toggleYear = (id) => {
  if (expandedYears.value.includes(id)) {
    expandedYears.value = expandedYears.value.filter(yId => yId !== id);
  } else {
    expandedYears.value.push(id);
  }
};

const openCreateModal = () => {
  modalError.value = '';
  const nextYr = fiscalYears.value.length > 0 ? Math.max(...fiscalYears.value.map(f => f.year)) + 1 : currentYear;
  form.value = {
    year: nextYr,
    start_date: `${nextYr}-01-01`,
    end_date: `${nextYr}-12-31`,
  };

  const modalEl = document.getElementById('fiscalYearModal');
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
};

const saveFiscalYear = async () => {
  saving.value = true;
  modalError.value = '';
  try {
    const res = await axios.post('/api/v1/fiscal-years', form.value);
    if (res.data.success) {
      const modalEl = document.getElementById('fiscalYearModal');
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
      fetchFiscalYears();
    }
  } catch (err) {
    if (err.response?.data?.errors) {
      const firstErrKey = Object.keys(err.response.data.errors)[0];
      modalError.value = err.response.data.errors[firstErrKey][0];
    } else {
      modalError.value = err.response?.data?.message || 'فشل إنشاء السنة المالية';
    }
  } finally {
    saving.value = false;
  }
};

const closePeriod = async (periodId) => {
  if (!confirm('هل أنت تأكد من رغبتك في إقفال هذه الفترة المالية؟ لن تتمكن من إضافة قيود في هذا التاريخ بعد الإقفال.')) return;
  closingPeriodId.value = periodId;
  try {
    const res = await axios.post(`/api/v1/fiscal-periods/${periodId}/close`);
    if (res.data.success) {
      fetchFiscalYears();
    }
  } catch (err) {
    alert(err.response?.data?.message || 'فشل إقفال الفترة المالية');
  } finally {
    closingPeriodId.value = null;
  }
};

onMounted(() => {
  fetchFiscalYears();
});
</script>
