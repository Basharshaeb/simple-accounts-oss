<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="fw-bold m-0">التقارير والقوائم المالية</h3>
        <p class="text-muted small m-0">التقارير والقوائم خالية من أخطاء التراكم، مستخرجة مباشرة من سجل المحرك المحاسبي</p>
      </div>

      <button class="btn btn-outline-dark btn-sm" @click="printReport">
        <i class="bi bi-printer me-1"></i> طباعة التقرير
      </button>
    </div>

    <!-- Branch Filter Header -->
    <div class="card p-3 bg-white mb-4 border-0 shadow-sm">
      <div class="row align-items-center">
        <div class="col-md-4">
          <label class="form-label font-weight-bold small text-secondary mb-1">تصفية التقارير حسب الفرع (اختياري):</label>
          <select v-model="selectedBranchId" class="form-select" @change="reloadActiveReport">
            <option value="">جميع الفروع (تقرير شامل لكل الشركة)</option>
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} - {{ b.name }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4 bg-white p-2 rounded shadow-sm">
      <li class="nav-item">
        <button class="nav-link fw-bold" :class="{ active: activeTab === 'trial_balance' }" @click="activeTab = 'trial_balance'">
          <i class="bi bi-scale me-1"></i> ميزان المراجعة
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link fw-bold" :class="{ active: activeTab === 'account_statement' }" @click="activeTab = 'account_statement'">
          <i class="bi bi-file-text me-1"></i> كشف الحساب الأستاذ
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link fw-bold" :class="{ active: activeTab === 'income_statement' }" @click="activeTab = 'income_statement'">
          <i class="bi bi-graph-up me-1"></i> قائمة الدخل
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link fw-bold" :class="{ active: activeTab === 'balance_sheet' }" @click="activeTab = 'balance_sheet'">
          <i class="bi bi-building me-1"></i> الميزانية العمومية
        </button>
      </li>
    </ul>

    <!-- Report 1: Trial Balance -->
    <div v-if="activeTab === 'trial_balance'" class="card p-4 bg-white print-area">
      <h5 class="fw-bold mb-3 border-bottom pb-2">ميزان المراجعة</h5>
      
      <div v-if="loadingTB" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      <div v-else class="table-responsive">
        <table class="table table-bordered align-middle text-center">
          <thead class="table-dark">
            <tr>
              <th rowspan="2" class="align-middle">رمز الحساب</th>
              <th rowspan="2" class="align-middle text-start">اسم الحساب</th>
              <th colspan="2">الرصيد الافتتاحي</th>
              <th colspan="2">حركة الفترة</th>
              <th colspan="2">الرصيد النهائي</th>
            </tr>
            <tr>
              <th>مدين</th>
              <th>دائن</th>
              <th>مدين</th>
              <th>دائن</th>
              <th>مدين</th>
              <th>دائن</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in trialBalance.accounts" :key="item.account_id">
              <td class="fw-bold">{{ item.code }}</td>
              <td class="text-start">{{ item.name }}</td>
              <td>{{ item.opening_debit > 0 ? formatMoney(item.opening_debit) : '-' }}</td>
              <td>{{ item.opening_credit > 0 ? formatMoney(item.opening_credit) : '-' }}</td>
              <td>{{ item.period_debit > 0 ? formatMoney(item.period_debit) : '-' }}</td>
              <td>{{ item.period_credit > 0 ? formatMoney(item.period_credit) : '-' }}</td>
              <td class="fw-bold text-success">{{ item.ending_debit > 0 ? formatMoney(item.ending_debit) : '-' }}</td>
              <td class="fw-bold text-danger">{{ item.ending_credit > 0 ? formatMoney(item.ending_credit) : '-' }}</td>
            </tr>
          </tbody>
          <tfoot class="table-light fw-bold" v-if="trialBalance.totals">
            <tr>
              <td colspan="2" class="text-end">الإجمالي الكلي:</td>
              <td>{{ formatMoney(trialBalance.totals.opening_debit) }}</td>
              <td>{{ formatMoney(trialBalance.totals.opening_credit) }}</td>
              <td>{{ formatMoney(trialBalance.totals.period_debit) }}</td>
              <td>{{ formatMoney(trialBalance.totals.period_credit) }}</td>
              <td class="text-success fs-6">{{ formatMoney(trialBalance.totals.ending_debit) }}</td>
              <td class="text-danger fs-6">{{ formatMoney(trialBalance.totals.ending_credit) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Report 2: Account Statement -->
    <div v-if="activeTab === 'account_statement'" class="card p-4 bg-white print-area">
      <div class="row g-3 mb-4 no-print">
        <div class="col-md-6">
          <label class="form-label font-weight-bold">اختر الحساب القابل للتسجيل</label>
          <select v-model="selectedAccountId" class="form-select" @change="fetchAccountStatement">
            <option value="" disabled>اختر الحساب لعرض حركة كشف الحساب...</option>
            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="loadingStmt" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      <div v-else-if="statement" class="table-responsive">
        <div class="p-3 bg-light rounded mb-3">
          <h5 class="fw-bold m-0">كشف حساب: {{ statement.account.code }} - {{ statement.account.name }}</h5>
          <span class="text-muted small">الرصيد الافتتاحي: {{ formatMoney(statement.opening_balance) }} | الرصيد النهائي: {{ formatMoney(statement.ending_balance) }}</span>
        </div>

        <table class="table table-bordered align-middle">
          <thead class="table-light text-center">
            <tr>
              <th>التاريخ</th>
              <th>رقم القيد</th>
              <th>المرجع</th>
              <th>البيان التفصيلي</th>
              <th>مدين (Debit)</th>
              <th>دائن (Credit)</th>
              <th>الرصيد الجاري</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(m, idx) in statement.movements" :key="idx">
              <td class="text-center">{{ m.date }}</td>
              <td class="text-center fw-bold text-primary">{{ m.entry_number }}</td>
              <td class="text-center">{{ m.reference || '-' }}</td>
              <td>{{ m.description }}</td>
              <td class="text-center text-success fw-bold">{{ m.debit > 0 ? formatMoney(m.debit) : '-' }}</td>
              <td class="text-center text-danger fw-bold">{{ m.credit > 0 ? formatMoney(m.credit) : '-' }}</td>
              <td class="text-center fw-bold">{{ formatMoney(m.running_balance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Report 3: Income Statement -->
    <div v-if="activeTab === 'income_statement'" class="card p-4 bg-white print-area">
      <h5 class="fw-bold mb-3 border-bottom pb-2">قائمة الدخل (Income Statement / Profit & Loss)</h5>

      <div v-if="loadingIncome" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      <div v-else class="row g-4">
        <div class="col-md-6">
          <div class="card p-3 border-success border-top border-4">
            <h6 class="fw-bold text-success mb-3"><i class="bi bi-arrow-up-circle me-1"></i> الإيرادات (Revenues)</h6>
            <ul class="list-group list-group-flush mb-3">
              <li v-for="rev in incomeStatement.revenues" :key="rev.account_id" class="list-group-item d-flex justify-content-between">
                <span>{{ rev.code }} - {{ rev.name }}</span>
                <span class="fw-bold text-success">{{ formatMoney(rev.amount) }}</span>
              </li>
            </ul>
            <div class="d-flex justify-content-between fw-bold fs-6 p-2 bg-success-subtle rounded text-success">
              <span>إجمالي الإيرادات:</span>
              <span>{{ formatMoney(incomeStatement.total_revenue) }}</span>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card p-3 border-danger border-top border-4">
            <h6 class="fw-bold text-danger mb-3"><i class="bi bi-arrow-down-circle me-1"></i> المصروفات (Expenses)</h6>
            <ul class="list-group list-group-flush mb-3">
              <li v-for="exp in incomeStatement.expenses" :key="exp.account_id" class="list-group-item d-flex justify-content-between">
                <span>{{ exp.code }} - {{ exp.name }}</span>
                <span class="fw-bold text-danger">{{ formatMoney(exp.amount) }}</span>
              </li>
            </ul>
            <div class="d-flex justify-content-between fw-bold fs-6 p-2 bg-danger-subtle rounded text-danger">
              <span>إجمالي المصروفات:</span>
              <span>{{ formatMoney(incomeStatement.total_expense) }}</span>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="p-3 rounded text-center fw-bold fs-4" :class="incomeStatement.net_profit >= 0 ? 'bg-success text-white' : 'bg-danger text-white'">
            صافي الربح / الخسارة: {{ formatMoney(incomeStatement.net_profit) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Report 4: Balance Sheet -->
    <div v-if="activeTab === 'balance_sheet'" class="card p-4 bg-white print-area">
      <h5 class="fw-bold mb-3 border-bottom pb-2">الميزانية العمومية (Balance Sheet)</h5>

      <div v-if="loadingBS" class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      <div v-else class="row g-4">
        <!-- Assets Column -->
        <div class="col-md-6">
          <div class="card p-3 border-primary border-top border-4">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-bank me-1"></i> الأصول (Assets)</h6>
            <ul class="list-group list-group-flush mb-3">
              <li v-for="a in balanceSheet.assets" :key="a.id" class="list-group-item d-flex justify-content-between">
                <span>{{ a.code }} - {{ a.name }}</span>
                <span class="fw-bold">{{ formatMoney(a.amount) }}</span>
              </li>
            </ul>
            <div class="d-flex justify-content-between fw-bold fs-5 p-2 bg-primary-subtle text-primary rounded">
              <span>إجمالي الأصول:</span>
              <span>{{ formatMoney(balanceSheet.total_assets) }}</span>
            </div>
          </div>
        </div>

        <!-- Liabilities & Equity Column -->
        <div class="col-md-6">
          <div class="card p-3 border-warning border-top border-4 mb-3">
            <h6 class="fw-bold text-warning mb-3"><i class="bi bi-credit-card me-1"></i> الخصوم (Liabilities)</h6>
            <ul class="list-group list-group-flush mb-3">
              <li v-for="l in balanceSheet.liabilities" :key="l.id" class="list-group-item d-flex justify-content-between">
                <span>{{ l.code }} - {{ l.name }}</span>
                <span class="fw-bold">{{ formatMoney(l.amount) }}</span>
              </li>
            </ul>
            <div class="d-flex justify-content-between fw-bold p-2 bg-warning-subtle text-warning rounded">
              <span>إجمالي الخصوم:</span>
              <span>{{ formatMoney(balanceSheet.total_liabilities) }}</span>
            </div>
          </div>

          <div class="card p-3 border-info border-top border-4">
            <h6 class="fw-bold text-info mb-3"><i class="bi bi-pie-chart me-1"></i> حقوق الملكية (Equity)</h6>
            <ul class="list-group list-group-flush mb-3">
              <li v-for="e in balanceSheet.equity" :key="e.id" class="list-group-item d-flex justify-content-between">
                <span>{{ e.code }} - {{ e.name }}</span>
                <span class="fw-bold">{{ formatMoney(e.amount) }}</span>
              </li>
              <li class="list-group-item d-flex justify-content-between bg-light fw-bold">
                <span>أرباح (خسائر) الفترة المحققة:</span>
                <span>{{ formatMoney(balanceSheet.retained_earnings) }}</span>
              </li>
            </ul>
            <div class="d-flex justify-content-between fw-bold p-2 bg-info-subtle text-info rounded">
              <span>إجمالي حقوق الملكية:</span>
              <span>{{ formatMoney(balanceSheet.total_equity + balanceSheet.retained_earnings) }}</span>
            </div>
          </div>

          <div class="d-flex justify-content-between fw-bold fs-5 p-3 mt-3 bg-dark text-white rounded">
            <span>مجموع الخصوم وحقوق الملكية:</span>
            <span>{{ formatMoney(balanceSheet.total_equity_and_liabilities) }}</span>
          </div>
        </div>

        <div class="col-12 text-center mt-3">
          <span class="badge p-2 fs-6" :class="balanceSheet.is_balanced ? 'bg-success' : 'bg-danger'">
            <i class="bi" :class="balanceSheet.is_balanced ? 'bi-check-circle-fill' : 'bi-x-circle-fill'"></i>
            {{ balanceSheet.is_balanced ? 'المعادلة المحاسبية متوازنة تمامًا (Assets = Liabilities + Equity)' : 'المعادلة المحاسبية غير متوازنة!' }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

const activeTab = ref('trial_balance');
const formatMoney = (val) => Number(val || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2 });

const branches = ref([]);
const selectedBranchId = ref('');

// Trial Balance State
const loadingTB = ref(false);
const trialBalance = ref({});

// Statement State
const accounts = ref([]);
const selectedAccountId = ref('');
const loadingStmt = ref(false);
const statement = ref(null);

// Income Statement State
const loadingIncome = ref(false);
const incomeStatement = ref({ revenues: [], expenses: [], total_revenue: 0, total_expense: 0, net_profit: 0 });

// Balance Sheet State
const loadingBS = ref(false);
const balanceSheet = ref({ assets: [], liabilities: [], equity: [], total_assets: 0, total_liabilities: 0, total_equity: 0, retained_earnings: 0, total_equity_and_liabilities: 0 });

const fetchBranches = async () => {
  try {
    const res = await axios.get('/api/v1/branches');
    if (res.data.success) branches.value = res.data.data;
  } catch (e) { console.error(e); }
};

const fetchTrialBalance = async () => {
  loadingTB.value = true;
  try {
    let url = '/api/v1/reports/trial-balance';
    if (selectedBranchId.value) url += `?branch_id=${selectedBranchId.value}`;
    const res = await axios.get(url);
    if (res.data.success) trialBalance.value = res.data.data;
  } catch (e) { console.error(e); }
  finally { loadingTB.value = false; }
};

const fetchAccountsList = async () => {
  try {
    const res = await axios.get('/api/v1/accounts?tree=false&is_postable=true');
    if (res.data.success) {
      accounts.value = res.data.data;
      if (accounts.value.length > 0) {
        selectedAccountId.value = accounts.value[0].id;
        fetchAccountStatement();
      }
    }
  } catch (e) { console.error(e); }
};

const fetchAccountStatement = async () => {
  if (!selectedAccountId.value) return;
  loadingStmt.value = true;
  try {
    let url = `/api/v1/reports/account-statement?account_id=${selectedAccountId.value}`;
    if (selectedBranchId.value) url += `&branch_id=${selectedBranchId.value}`;
    const res = await axios.get(url);
    if (res.data.success) statement.value = res.data.data;
  } catch (e) { console.error(e); }
  finally { loadingStmt.value = false; }
};

const fetchIncomeStatement = async () => {
  loadingIncome.value = true;
  try {
    let url = '/api/v1/reports/income-statement';
    if (selectedBranchId.value) url += `?branch_id=${selectedBranchId.value}`;
    const res = await axios.get(url);
    if (res.data.success) incomeStatement.value = res.data.data;
  } catch (e) { console.error(e); }
  finally { loadingIncome.value = false; }
};

const fetchBalanceSheet = async () => {
  loadingBS.value = true;
  try {
    let url = '/api/v1/reports/balance-sheet';
    if (selectedBranchId.value) url += `?branch_id=${selectedBranchId.value}`;
    const res = await axios.get(url);
    if (res.data.success) balanceSheet.value = res.data.data;
  } catch (e) { console.error(e); }
  finally { loadingBS.value = false; }
};

const reloadActiveReport = () => {
  if (activeTab.value === 'trial_balance') fetchTrialBalance();
  if (activeTab.value === 'account_statement') fetchAccountStatement();
  if (activeTab.value === 'income_statement') fetchIncomeStatement();
  if (activeTab.value === 'balance_sheet') fetchBalanceSheet();
};

const printReport = () => {
  window.print();
};

watch(activeTab, (tab) => {
  if (tab === 'trial_balance') fetchTrialBalance();
  if (tab === 'account_statement') fetchAccountsList();
  if (tab === 'income_statement') fetchIncomeStatement();
  if (tab === 'balance_sheet') fetchBalanceSheet();
});

onMounted(() => {
  fetchBranches();
  fetchTrialBalance();
});
</script>
