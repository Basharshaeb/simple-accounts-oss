<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">سندات القبض (Receipt Vouchers)</h3>
        <p class="text-muted small m-0">استلام الأموال من العملاء والجهات وإيداعها في الصندوق أو البنك</p>
      </div>

      <router-link to="/receipts/create" class="btn btn-success fw-bold">
        <i class="bi bi-arrow-down-left-square me-1"></i> سند قبض جديد
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
              placeholder="بحث في كل العمليات (باسم الحساب/العميل/الإيراد/رقم السند/البيان)..." 
              @input="fetchReceipts" 
            />
          </div>
        </div>
        <div class="col-md-4">
          <select v-model="filterAccount" class="form-select form-select-sm" @change="fetchReceipts">
            <option value="">جميع الحسابات (عميل / إيراد / جهة)</option>
            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <select v-model="filterBranch" class="form-select form-select-sm" @change="fetchReceipts">
            <option value="">جميع الفروع (اختياري)</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} - {{ b.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="text-center py-4">
        <div class="spinner-border text-success" role="status"></div>
      </div>

      <div v-else-if="receipts.length === 0" class="text-center text-muted py-5">
        لا توجد سندات قبض مسجلة مطابقة للبحث.
      </div>

      <div v-else class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>رقم السند</th>
              <th>الفرع والصندوق</th>
              <th>التاريخ</th>
              <th>المستلم منه (الحساب)</th>
              <th>حساب الإيداع (صندوق/بنك)</th>
              <th>المبلغ</th>
              <th>الحالة</th>
              <th class="text-end">الإجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rcpt in receipts" :key="rcpt.id">
              <td class="fw-bold text-success">{{ rcpt.receipt_number }}</td>
              <td>
                <span class="d-block fw-bold small text-dark">{{ rcpt.branch ? rcpt.branch.name : 'عام / رئيسي' }}</span>
                <small class="text-muted">{{ rcpt.cash_box ? rcpt.cash_box.name : '-' }}</small>
              </td>
              <td>{{ rcpt.receipt_date }}</td>
              <td>{{ rcpt.account ? rcpt.account.name : '-' }}</td>
              <td>{{ rcpt.cash_or_bank_account ? rcpt.cash_or_bank_account.name : '-' }}</td>
              <td class="fw-bold text-success">{{ formatMoney(rcpt.amount) }} {{ rcpt.currency ? rcpt.currency.code : 'SAR' }}</td>
              <td>
                <span class="badge" :class="rcpt.status === 'POSTED' ? 'bg-success' : 'bg-warning text-dark'">
                  {{ rcpt.status === 'POSTED' ? 'معتمد' : 'مسودة' }}
                </span>
              </td>
              <td class="text-end">
                <button v-if="rcpt.status === 'DRAFT'" class="btn btn-sm btn-success" @click="postReceipt(rcpt.id)">
                  <i class="bi bi-check-circle me-1"></i> اعتماد وترحيل
                </button>
                <span v-else-if="rcpt.journal_entry" class="badge bg-info-subtle text-info p-2">
                  <i class="bi bi-link-45deg me-1"></i> قيد رقم #{{ rcpt.journal_entry.entry_number }}
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
const receipts = ref([]);
const branches = ref([]);
const accounts = ref([]);
const filterBranch = ref('');
const filterAccount = ref('');
const searchQuery = ref('');

const formatMoney = (val) => Number(val || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 });

const fetchReceipts = async () => {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    if (filterBranch.value) params.append('branch_id', filterBranch.value);
    if (filterAccount.value) params.append('account_id', filterAccount.value);
    if (searchQuery.value) params.append('search', searchQuery.value);

    const res = await axios.get('/api/v1/receipts?' + params.toString());
    if (res.data.success) {
      receipts.value = res.data.data.data;
    }
  } catch (err) {
    console.error('Failed to load receipts:', err);
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

const postReceipt = async (id) => {
  try {
    const res = await axios.post(`/api/v1/receipts/${id}/post`);
    if (res.data.success) {
      alert('تم اعتماد سند القبض وتوليد القيد التلقائي.');
      fetchReceipts();
    }
  } catch (err) {
    alert(err.response?.data?.message || 'فشل اعتماد سند القبض');
  }
};

onMounted(() => {
  fetchMetadata();
  fetchReceipts();
});
</script>
