<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">لوحة التحكم والأداء المالي</h3>
        <p class="text-muted small m-0">مؤشرات الأداء المالي الحية للشركة المعتمدة على القيود المحاسبية المعتمدة (POSTED)</p>
      </div>

      <div class="d-flex gap-2">
        <router-link to="/journal-entries/create" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-circle me-1"></i> قيد يومي جديد
        </router-link>
        <router-link to="/receipts" class="btn btn-success btn-sm">
          <i class="bi bi-arrow-down-left-square me-1"></i> سند قبض
        </router-link>
        <router-link to="/payments" class="btn btn-danger btn-sm">
          <i class="bi bi-arrow-up-right-square me-1"></i> سند صرف
        </router-link>
      </div>
    </div>

    <!-- Spinner Loading -->
    <div v-if="loading" class="text-center my-5 py-5">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-2 text-muted">جاري تحميل البيانات المالية...</p>
    </div>

    <div v-else>
      <!-- KPI Row 1: Assets, Liabilities, Equity, Net Profit -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card p-3 border-start border-4 border-primary bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <span class="text-muted small d-block">إجمالي الأصول (Assets)</span>
                <h4 class="fw-bold text-primary mt-1 mb-0">{{ formatMoney(data.total_assets) }}</h4>
              </div>
              <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                <i class="bi bi-bank fs-4"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card p-3 border-start border-4 border-warning bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <span class="text-muted small d-block">إجمالي الخصوم (Liabilities)</span>
                <h4 class="fw-bold text-warning mt-1 mb-0">{{ formatMoney(data.total_liabilities) }}</h4>
              </div>
              <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                <i class="bi bi-credit-card fs-4"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card p-3 border-start border-4 border-info bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <span class="text-muted small d-block">حقوق الملكية (Equity)</span>
                <h4 class="fw-bold text-info mt-1 mb-0">{{ formatMoney(data.total_equity) }}</h4>
              </div>
              <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                <i class="bi bi-pie-chart fs-4"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card p-3 border-start border-4 bg-white h-100" :class="data.net_profit >= 0 ? 'border-success' : 'border-danger'">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <span class="text-muted small d-block">صافي الربح / الخسارة</span>
                <h4 class="fw-bold mt-1 mb-0" :class="data.net_profit >= 0 ? 'text-success' : 'text-danger'">
                  {{ formatMoney(data.net_profit) }}
                </h4>
              </div>
              <div class="p-3 rounded-circle" :class="data.net_profit >= 0 ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'">
                <i class="bi" :class="data.net_profit >= 0 ? 'bi-graph-up-arrow fs-4' : 'bi-graph-down-arrow fs-4'"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Row 2: Revenues, Expenses, Cash, Bank -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card p-3 bg-white">
            <span class="text-muted small">الإيرادات (Revenue)</span>
            <h5 class="fw-bold text-success mt-1 mb-0">{{ formatMoney(data.total_revenue) }}</h5>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card p-3 bg-white">
            <span class="text-muted small">المصروفات (Expenses)</span>
            <h5 class="fw-bold text-danger mt-1 mb-0">{{ formatMoney(data.total_expense) }}</h5>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card p-3 bg-white">
            <span class="text-muted small">رصيد الصندوق النقدي</span>
            <h5 class="fw-bold text-dark mt-1 mb-0">{{ formatMoney(data.cash_balance) }}</h5>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card p-3 bg-white">
            <span class="text-muted small">رصيد حسابات البنوك</span>
            <h5 class="fw-bold text-dark mt-1 mb-0">{{ formatMoney(data.bank_balance) }}</h5>
          </div>
        </div>
      </div>

      <!-- Operational Counts Card -->
      <div class="card p-4 bg-white">
        <h5 class="fw-bold mb-3"><i class="bi bi-list-check me-2 text-primary"></i>ملخص الحركات والمستندات المسجلة</h5>
        <div class="row text-center g-3">
          <div class="col-md-4">
            <div class="p-3 bg-light rounded">
              <i class="bi bi-journal-text fs-2 text-primary d-block mb-1"></i>
              <span class="text-muted small d-block">عدد القيود اليومية</span>
              <span class="fs-4 fw-bold">{{ data.counts ? data.counts.journal_entries : 0 }}</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 bg-light rounded">
              <i class="bi bi-arrow-down-left-square fs-2 text-success d-block mb-1"></i>
              <span class="text-muted small d-block">عدد سندات القبض</span>
              <span class="fs-4 fw-bold">{{ data.counts ? data.counts.receipts : 0 }}</span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 bg-light rounded">
              <i class="bi bi-arrow-up-right-square fs-2 text-danger d-block mb-1"></i>
              <span class="text-muted small d-block">عدد سندات الصرف</span>
              <span class="fs-4 fw-bold">{{ data.counts ? data.counts.payments : 0 }}</span>
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
import { useAuthStore } from '../stores/authStore';

const auth = useAuthStore();
const loading = ref(true);
const data = ref({});

const formatMoney = (val) => {
  const num = Number(val || 0);
  const currencyCode = auth.currentCompany && auth.currentCompany.base_currency ? auth.currentCompany.base_currency.code : 'SAR';
  return num.toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currencyCode;
};

const fetchDashboard = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/v1/reports/dashboard');
    if (res.data.success) {
      data.value = res.data.data;
    }
  } catch (err) {
    console.error('Failed to load dashboard:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchDashboard();
});
</script>
