<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">القيود اليومية (Journal Entries)</h3>
        <p class="text-muted small m-0">سجل القيود اليومية بنظام القيد المزدوج المحاسبي</p>
      </div>

      <router-link to="/journal-entries/create" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> قيد يومي جديد
      </router-link>
    </div>

    <!-- Filters & Table Card -->
    <div class="card p-4 bg-white">
      <div class="row g-2 mb-3">
        <div class="col-md-3">
          <select v-model="filterStatus" class="form-select form-select-sm" @change="fetchEntries">
            <option value="">جميع الحالات</option>
            <option value="DRAFT">مسودة (DRAFT)</option>
            <option value="POSTED">معتمد (POSTED)</option>
            <option value="REVERSED">ملغى (REVERSED)</option>
          </select>
        </div>
        <div class="col-md-3">
          <select v-model="filterBranch" class="form-select form-select-sm" @change="fetchEntries">
            <option value="">جميع الفروع (اختياري)</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} - {{ b.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="text-center py-4">
        <div class="spinner-border text-primary" role="status"></div>
      </div>

      <div v-else-if="entries.length === 0" class="text-center text-muted py-5">
        لا توجد قيود يومية مسجلة بهذه الشروط.
      </div>

      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>رقم القيد</th>
              <th>الفرع</th>
              <th>التاريخ</th>
              <th>البيان / الوصف</th>
              <th>المرجع</th>
              <th>إجمالي المدين</th>
              <th>إجمالي الدائن</th>
              <th>الحالة</th>
              <th class="text-end">الإجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in entries" :key="entry.id">
              <td class="fw-bold text-primary">{{ entry.entry_number }}</td>
              <td><span class="badge bg-secondary-subtle text-secondary">{{ entry.branch ? entry.branch.name : 'عام' }}</span></td>
              <td class="text-nowrap">{{ entry.entry_date }}</td>
              <td>{{ entry.description }}</td>
              <td><span class="badge bg-light text-dark border">{{ entry.reference || '-' }}</span></td>
              <td class="fw-bold text-success">{{ formatMoney(entry.total_debit) }}</td>
              <td class="fw-bold text-danger">{{ formatMoney(entry.total_credit) }}</td>
              <td>
                <span class="badge" :class="getStatusBadge(entry.status)">{{ getStatusText(entry.status) }}</span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-info me-1" @click="viewEntry(entry.id)" title="عرض التفاصيل">
                  <i class="bi bi-eye"></i>
                </button>
                <button v-if="entry.status === 'DRAFT'" class="btn btn-sm btn-success me-1" @click="postEntry(entry.id)" title="اعتماد القيد">
                  <i class="bi bi-check-circle"></i> اعتماد
                </button>
                <button v-if="entry.status === 'POSTED'" class="btn btn-sm btn-outline-danger" @click="reverseEntry(entry.id)" title="إلغاء وعكس القيد">
                  <i class="bi bi-arrow-counterclockwise"></i> إلغاء
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Entry Details -->
    <div class="modal fade" id="entryDetailModal" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" v-if="selectedEntry">
          <div class="modal-header">
            <h5 class="modal-title fw-bold">تفاصيل القيد رقم: {{ selectedEntry.entry_number }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row mb-3 bg-light p-3 rounded">
              <div class="col-md-3"><strong>الفرع:</strong> {{ selectedEntry.branch ? selectedEntry.branch.name : 'عام / المركز الرئيسي' }}</div>
              <div class="col-md-3"><strong>التاريخ:</strong> {{ selectedEntry.entry_date }}</div>
              <div class="col-md-3"><strong>المرجع:</strong> {{ selectedEntry.reference || '-' }}</div>
              <div class="col-md-3"><strong>العملة:</strong> {{ selectedEntry.currency ? selectedEntry.currency.code : 'SAR' }}</div>
              <div class="col-md-3"><strong>الحالة:</strong> <span class="badge" :class="getStatusBadge(selectedEntry.status)">{{ getStatusText(selectedEntry.status) }}</span></div>
              <div class="col-12 mt-2"><strong>البيان:</strong> {{ selectedEntry.description }}</div>
            </div>

            <h6 class="fw-bold mb-2">سطور القيد المحاسبية (Lines):</h6>
            <div class="table-responsive">
              <table class="table table-bordered align-middle text-center">
                <thead class="table-secondary">
                  <tr>
                    <th>رمز الحساب</th>
                    <th>اسم الحساب</th>
                    <th>العملة والسعر</th>
                    <th>البيان التفصيلي</th>
                    <th>مدين</th>
                    <th>دائن</th>
                    <th>المبلغ المحلي</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in selectedEntry.lines" :key="line.id">
                    <td class="fw-bold">{{ line.account ? line.account.code : '-' }}</td>
                    <td>{{ line.account ? line.account.name : '-' }}</td>
                    <td>
                      <span class="badge bg-light text-dark border">
                        {{ line.currency ? line.currency.code : (selectedEntry.currency ? selectedEntry.currency.code : 'SAR') }}
                        ({{ Number(line.exchange_rate || 1).toFixed(4) }})
                      </span>
                    </td>
                    <td>{{ line.description || '-' }}</td>
                    <td class="text-success fw-bold">{{ line.debit > 0 ? formatMoney(line.debit) : '-' }}</td>
                    <td class="text-danger fw-bold">{{ line.credit > 0 ? formatMoney(line.credit) : '-' }}</td>
                    <td class="font-monospace small fw-bold">{{ formatMoney(line.base_debit > 0 ? line.base_debit : line.base_credit) }}</td>
                  </tr>
                </tbody>
                <tfoot class="table-light fw-bold">
                  <tr>
                    <td colspan="4" class="text-end">إجمالي القيد بالعملة المحلية:</td>
                    <td class="text-success">{{ formatMoney(selectedEntry.total_debit) }}</td>
                    <td class="text-danger">{{ formatMoney(selectedEntry.total_credit) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
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
const entries = ref([]);
const branches = ref([]);
const filterStatus = ref('');
const filterBranch = ref('');
const selectedEntry = ref(null);

const formatMoney = (val) => Number(val || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 });

const getStatusBadge = (status) => {
  switch(status) {
    case 'POSTED': return 'bg-success';
    case 'DRAFT': return 'bg-warning text-dark';
    case 'REVERSED': return 'bg-danger';
    default: return 'bg-secondary';
  }
};

const getStatusText = (status) => {
  switch(status) {
    case 'POSTED': return 'معتمد';
    case 'DRAFT': return 'مسودة';
    case 'REVERSED': return 'عكسي/ملغى';
    default: return status;
  }
};

const fetchBranches = async () => {
  try {
    const res = await axios.get('/api/v1/branches');
    if (res.data.success) branches.value = res.data.data;
  } catch (err) {
    console.error('Failed to load branches', err);
  }
};

const fetchEntries = async () => {
  loading.value = true;
  try {
    let url = `/api/v1/journal-entries?status=${filterStatus.value}`;
    if (filterBranch.value) url += `&branch_id=${filterBranch.value}`;
    const res = await axios.get(url);
    if (res.data.success) {
      entries.value = res.data.data.data;
    }
  } catch (err) {
    console.error('Failed to load journal entries:', err);
  } finally {
    loading.value = false;
  }
};

const viewEntry = async (id) => {
  try {
    const res = await axios.get(`/api/v1/journal-entries/${id}`);
    if (res.data.success) {
      selectedEntry.value = res.data.data;
      const modalEl = document.getElementById('entryDetailModal');
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  } catch (err) {
    alert('فشل جلب تفاصيل القيد');
  }
};

const postEntry = async (id) => {
  if (!confirm('هل أنت تأكد من اعتماد هذا القيد المحاسبي؟ لا يمكن التعديل المباشر بعد الاعتماد.')) return;
  try {
    const res = await axios.post(`/api/v1/journal-entries/${id}/post`);
    if (res.data.success) {
      alert('تم اعتماد القيد بنجاح.');
      fetchEntries();
    }
  } catch (err) {
    alert(err.response?.data?.message || 'فشل اعتماد القيد');
  }
};

const reverseEntry = async (id) => {
  const reason = prompt('أدخل سبب إلغاء وتنفيذ قيد عكسي:');
  if (reason === null) return;
  try {
    const res = await axios.post(`/api/v1/journal-entries/${id}/reverse`, { reason });
    if (res.data.success) {
      alert('تم إلغاء القيد وتوليد قيد عكسي تلقائيًا.');
      fetchEntries();
    }
  } catch (err) {
    alert(err.response?.data?.message || 'فشل إلغاء القيد');
  }
};

onMounted(() => {
  fetchBranches();
  fetchEntries();
});
</script>
