<template>
  <div v-if="!auth.isAuthenticated || route.path.startsWith('/superadmin')">
    <router-view />
  </div>

  <!-- Standalone Layout for Platform Companies Management -->
  <div v-else-if="route.path === '/companies'" class="min-vh-100 bg-light d-flex flex-column">
    <nav class="navbar navbar-expand-lg navbar-dark bg-slate-900 px-4 py-3 shadow-md border-bottom border-slate-800" style="background-color: #0f172a;">
      <div class="container-fluid p-0 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
          <div class="bg-warning bg-opacity-10 p-2 rounded-3 border border-warning-subtle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
            <i class="bi bi-shield-lock-fill fs-3 text-warning"></i>
          </div>
          <div>
            <h4 class="fw-bold text-white mb-0">لوحة الإدارة المركزية والمنشآت</h4>
            <small class="text-slate-400" style="color: #94a3b8; font-size: 0.8rem;">System Administration & Multi-Company Control Panel</small>
          </div>
        </div>

        <div class="d-flex align-items-center gap-3">
          <router-link to="/" class="btn btn-primary btn-sm fw-bold px-3 py-2 rounded-3">
            <i class="bi bi-arrow-right-circle me-1"></i> العودة للنظام المحاسبي التشغيلي
          </router-link>
          
          <div class="vr bg-secondary mx-1" style="height: 24px;"></div>

          <div class="d-flex align-items-center text-white small gap-2">
            <div class="bg-info bg-opacity-20 p-2 rounded-circle text-info">
              <i class="bi bi-person-badge-fill fs-5"></i>
            </div>
            <div>
              <div class="fw-bold">{{ auth.user ? auth.user.name : 'مسؤول النظام' }}</div>
              <div class="text-slate-400" style="color: #94a3b8; font-size: 0.75rem;">{{ auth.user ? auth.user.email : '' }}</div>
            </div>
          </div>

          <button class="btn btn-sm btn-outline-danger" title="تسجيل الخروج" @click="auth.logout()">
            <i class="bi bi-box-arrow-right fs-5"></i>
          </button>
        </div>
      </div>
    </nav>

    <div class="container-fluid p-4 flex-grow-1">
      <router-view />
    </div>
  </div>

  <!-- Standard Company Operational Layout -->
  <div v-else class="d-flex min-vh-100">
    <!-- Sidebar -->
    <div class="app-sidebar p-3 d-flex flex-column shrink-0">
      <!-- Header Brand -->
      <div class="d-flex align-items-center mb-3 pb-3 border-bottom border-slate-700 gap-2">
        <div class="bg-primary bg-gradient p-2 rounded-3 text-white shadow-sm d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
          <i class="bi bi-calculator-fill fs-4"></i>
        </div>
        <div>
          <span class="fs-5 fw-bold text-white d-block lh-1">نظام المحاسبة</span>
          <small class="text-slate-400" style="font-size: 0.75rem;">Enterprise Accounts</small>
        </div>
      </div>

      <!-- Navigation Links -->
      <div class="flex-grow-1 overflow-auto pe-1">
        <div class="sidebar-category">الرئيسية</div>
        <router-link to="/" class="sidebar-link" active-class="active">
          <i class="bi bi-speedometer2 text-info"></i>
          <span>لوحة التحكم</span>
        </router-link>

        <div class="sidebar-category">العمليات المالية</div>
        <router-link to="/receipts" class="sidebar-link" active-class="active">
          <i class="bi bi-arrow-down-left-square-fill text-success"></i>
          <span>سندات القبض</span>
        </router-link>
        <router-link to="/payments" class="sidebar-link" active-class="active">
          <i class="bi bi-arrow-up-right-square-fill text-danger"></i>
          <span>سندات الصرف</span>
        </router-link>
        <router-link to="/journal-entries" class="sidebar-link" active-class="active">
          <i class="bi bi-journal-text text-primary"></i>
          <span>القيود اليومية</span>
        </router-link>

        <div class="sidebar-category">الشجرة والتهيئة</div>
        <router-link to="/accounts" class="sidebar-link" active-class="active">
          <i class="bi bi-diagram-3-fill text-warning"></i>
          <span>دليل الحسابات</span>
        </router-link>
        <router-link to="/branches-and-cash-boxes" class="sidebar-link" active-class="active">
          <i class="bi bi-buildings-fill text-info"></i>
          <span>الفروع والصناديق</span>
        </router-link>
        <router-link to="/fiscal-years" class="sidebar-link" active-class="active">
          <i class="bi bi-calendar-range-fill text-primary"></i>
          <span>السنوات المالية</span>
        </router-link>
        <router-link to="/currencies" class="sidebar-link" active-class="active">
          <i class="bi bi-currency-exchange text-success"></i>
          <span>العملات</span>
        </router-link>

        <div class="sidebar-category">التقارير والإدارة</div>
        <router-link to="/reports" class="sidebar-link" active-class="active">
          <i class="bi bi-file-earmark-bar-graph-fill text-info"></i>
          <span>التقارير المالية</span>
        </router-link>
        <router-link to="/users" class="sidebar-link" active-class="active">
          <i class="bi bi-people-fill text-secondary"></i>
          <span>المستخدمين والصلاحيات</span>
        </router-link>
      </div>

      <hr class="border-secondary my-3">

      <!-- User Profile & Logout -->
      <div class="user-card d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center text-white text-decoration-none gap-2">
          <div class="bg-info bg-opacity-20 p-2 rounded-circle text-info d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
            <i class="bi bi-person-fill fs-5"></i>
          </div>
          <div style="font-size: 0.85rem;" class="overflow-hidden">
            <div class="fw-bold text-truncate" style="max-width: 130px;">{{ auth.user ? auth.user.name : 'المستخدم' }}</div>
            <div class="text-slate-400 small text-truncate" style="font-size: 0.72rem; max-width: 130px;">{{ auth.user ? auth.user.email : '' }}</div>
          </div>
        </div>
        <button class="btn btn-sm btn-outline-danger border-0" title="تسجيل الخروج" @click="auth.logout()">
          <i class="bi bi-box-arrow-right fs-5"></i>
        </button>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-grow-1 d-flex flex-column bg-light">
      <nav class="navbar navbar-expand-lg navbar-white bg-white border-bottom px-4 py-2">
        <div class="container-fluid p-0">
          <div class="d-flex align-items-center gap-2">
            <span class="navbar-brand text-primary fw-bold mb-0">
              {{ auth.currentCompany ? auth.currentCompany.name : 'النظام المحاسبي' }}
            </span>
            <span class="badge bg-primary-subtle text-primary fs-6 me-1">{{ auth.currentCompany ? auth.currentCompany.base_currency ? auth.currentCompany.base_currency.code : 'SAR' : 'SAR' }}</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle">
              <i class="bi bi-calendar-check me-1"></i>السنة المالية الحالية: {{ new Date().getFullYear() }} (مفتوحة)
            </span>
          </div>
          <div class="d-flex align-items-center gap-3 ms-auto">
            <span class="text-muted small">
              <i class="bi bi-calendar-event me-1"></i>
              التاريخ: {{ new Date().toLocaleDateString('ar-SA') }}
            </span>
          </div>
        </div>
      </nav>

      <div class="p-4 flex-grow-1">
        <router-view />
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from './stores/authStore';

const auth = useAuthStore();
const route = useRoute();

onMounted(() => {
  if (auth.isAuthenticated) {
    auth.fetchMe();
  }
});
</script>

<style scoped>
.app-sidebar {
  width: 270px;
  background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
  color: #f8fafc;
  box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
}

.sidebar-category {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.5px;
  color: #64748b;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  padding-right: 0.5rem;
  text-transform: uppercase;
}

.sidebar-link {
  border-radius: 10px;
  padding: 10px 14px;
  color: #cbd5e1;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 500;
  font-size: 0.95rem;
  transition: all 0.2s ease-in-out;
  margin-bottom: 3px;
}

.sidebar-link:hover {
  background-color: rgba(255, 255, 255, 0.08);
  color: #ffffff;
  transform: translateX(-4px);
}

.sidebar-link.active {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: #ffffff;
  font-weight: 700;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
}

.sidebar-link i {
  font-size: 1.15rem;
  transition: transform 0.2s ease;
}

.sidebar-link:hover i {
  transform: scale(1.15);
}

.sidebar-link.active i {
  color: #ffffff !important;
}

.user-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  padding: 10px 12px;
  backdrop-filter: blur(8px);
}
</style>
