<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">سندات الصرف (Payment Vouchers)</h3>
        <p class="text-muted small m-0">صرف الأموال للموردين والمصروفات من النقدية أو البنك</p>
      </div>

      <router-link to="/payments/create" class="btn btn-danger fw-bold">
        <i class="bi bi-arrow-up-right-square me-1"></i> سند صرف جديد
      </router-link>
    </div>

    <!-- Table -->
    <div class="card p-4 bg-white shadow-sm border-0">
      <div class="row g-2 mb-3">
        <div class="col-md-5">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
            <input 
              v-model="searchQuery" 
              type="text" 
              class="form-control" 
              placeholder="بحث في كل العمليات (باسم الحساب/المورد/المصروف/رقم السند/البيان)..." 
              @input="fetchPayments" 
            />
          </div>
        </div>
        <div class="col-md-4">
          <select v-model="filterAccount" class="form-select form-select-sm" @change="fetchPayments">
            <option value="">جميع الحسابات (مورد / مصروف / جهة)</option>
            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <select v-model="filterBranch" class="form-select form-select-sm" @change="fetchPayments">
            <option value="">جميع الفروع (اختياري)</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} - {{ b.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="text-center py-4">
        <div class="spinner-border text-danger" role="status"></div>
      </div>

      <div v-else-if="payments.length === 0" class="text-center text-muted py-5">
        لا توجد سندات صرف مسجلة مطابقة للبحث.
      </div>

      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>رقم السند</th>
              <th>الفرع والصندوق</th>
              <th>التاريخ</th>
              <th>المصروف / المورد (الحساب)</th>
              <th>مصدر الصرف (صندوق/بنك)</th>
              <th>المبلغ</th>
              <th>الحالة</th>
              <th class="text-end">الإجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pmt in payments" :key="pmt.id">
              <td class="fw-bold text-danger">{{ pmt.payment_number }}</td>
              <td>
                <span class="d-block fw-bold small text-dark">{{ pmt.branch ? pmt.branch.name : 'عام / رئيسي' }}</span>
                <small class="text-muted">{{ pmt.cash_box ? pmt.cash_box.name : '-' }}</small>
              </td>
              <td class="text-nowrap">{{ pmt.payment_date }}</td>
              <td>{{ pmt.account ? pmt.account.name : '-' }}</td>
              <td>{{ pmt.cash_or_bank_account ? pmt.cash_or_bank_account.name : '-' }}</td>
              <td class="fw-bold text-danger">{{ formatMoney(pmt.amount) }} {{ pmt.currency ? pmt.currency.code : 'SAR' }}</td>
              <td>
                <span class="badge" :class="pmt.status === 'POSTED' ? 'bg-success' : 'bg-warning text-dark'">
                  {{ pmt.status === 'POSTED' ? 'معتمد' : 'مسودة' }}
                </span>
              </td>
              <td class="text-end">
                <button v-if="pmt.status === 'DRAFT'" class="btn btn-sm btn-success" @click="postPayment(pmt.id)">
                  <i class="bi bi-check-circle me-1"></i> اعتماد وترحيل
                </button>
                <span v-else-if="pmt.journal_entry" class="badge bg-info-subtle text-info p-2">
                  <i class="bi bi-link-45deg me-1"></i> قيد رقم #{{ pmt.journal_entry.entry_number }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(true);
const payments = ref([]);
const branches = ref([]);
const accounts = ref([]);
const filterBranch = ref('');
const filterAccount = ref('');
const searchQuery = ref('');

const formatMoney = (val) => Number(val || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 });

const fetchPayments = async () => {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    if (filterBranch.value) params.append('branch_id', filterBranch.value);
    if (filterAccount.value) params.append('account_id', filterAccount.value);
    if (searchQuery.value) params.append('search', searchQuery.value);

    const res = await axios.get('/api/v1/payments?' + params.toString());
    if (res.data.success) {
      payments.value = res.data.data.data;
    }
  } catch (err) {
    console.error('Failed to load payments:', err);
  } finally {
    loading.value = false;
  }
};

const fetchMetadata = async () => {
  try {
    const [bRes, aRes] = await Promise.all([
      axios.get('/api/v1/branches'),
      axios.get('/api/v1/accounts?tree=false&is_postable=true'),
    ]);
    if (bRes.data.success) branches.value = bRes.data.data;
    if (aRes.data.success) accounts.value = aRes.data.data;
  } catch (err) {
    console.error('Failed to load metadata', err);
  }
};

const postPayment = async (id) => {
  try {
    const res = await axios.post(`/api/v1/payments/${id}/post`);
    if (res.data.success) {
      alert('تم اعتماد سند الصرف وتوليد القيد التلقائي.');
      fetchPayments();
    }
  } catch (err) {
    alert(err.response?.data?.message || 'فشل اعتماد سند الصرف');
  }
};

onMounted(() => {
  fetchMetadata();
  fetchPayments();
});
</script>
