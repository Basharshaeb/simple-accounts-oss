import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/authStore';

import LoginView from './views/LoginView.vue';
import DashboardView from './views/DashboardView.vue';
import AccountsView from './views/AccountsView.vue';
import JournalEntriesView from './views/JournalEntriesView.vue';
import JournalEntryCreateView from './views/JournalEntryCreateView.vue';
import ReceiptsView from './views/ReceiptsView.vue';
import ReceiptCreateView from './views/ReceiptCreateView.vue';
import PaymentsView from './views/PaymentsView.vue';
import PaymentCreateView from './views/PaymentCreateView.vue';
import ReportsView from './views/ReportsView.vue';
import BranchesAndCashBoxesView from './views/BranchesAndCashBoxesView.vue';
import FiscalYearsView from './views/FiscalYearsView.vue';
import CurrenciesView from './views/CurrenciesView.vue';
import UsersView from './views/UsersView.vue';
import SuperAdminLoginView from './views/SuperAdminLoginView.vue';
import SuperAdminDashboardView from './views/SuperAdminDashboardView.vue';

const routes = [
    { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
    { path: '/superadmin/login', name: 'superadmin-login', component: SuperAdminLoginView, meta: { superAdminGuest: true } },
    { path: '/superadmin', name: 'superadmin-dashboard', component: SuperAdminDashboardView, meta: { requiresSuperAdmin: true } },
    { path: '/companies', redirect: '/superadmin' },

    { path: '/', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true } },
    { path: '/accounts', name: 'accounts', component: AccountsView, meta: { requiresAuth: true } },
    { path: '/currencies', name: 'currencies', component: CurrenciesView, meta: { requiresAuth: true } },
    { path: '/users', name: 'users', component: UsersView, meta: { requiresAuth: true } },
    { path: '/branches-and-cash-boxes', name: 'branches-cash-boxes', component: BranchesAndCashBoxesView, meta: { requiresAuth: true } },
    { path: '/fiscal-years', name: 'fiscal-years', component: FiscalYearsView, meta: { requiresAuth: true } },
    { path: '/journal-entries', name: 'journal-entries', component: JournalEntriesView, meta: { requiresAuth: true } },
    { path: '/journal-entries/create', name: 'journal-entry-create', component: JournalEntryCreateView, meta: { requiresAuth: true } },
    { path: '/receipts', name: 'receipts', component: ReceiptsView, meta: { requiresAuth: true } },
    { path: '/receipts/create', name: 'receipt-create', component: ReceiptCreateView, meta: { requiresAuth: true } },
    { path: '/payments', name: 'payments', component: PaymentsView, meta: { requiresAuth: true } },
    { path: '/payments/create', name: 'payment-create', component: PaymentCreateView, meta: { requiresAuth: true } },
    { path: '/reports', name: 'reports', component: ReportsView, meta: { requiresAuth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const auth = useAuthStore();
    const superAdminToken = localStorage.getItem('superadmin_token');

    if (to.meta.requiresSuperAdmin && !superAdminToken) {
        return next({ name: 'superadmin-login' });
    }

    if (to.meta.superAdminGuest && superAdminToken) {
        return next({ name: 'superadmin-dashboard' });
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return next({ name: 'login' });
    }
    if (to.meta.guest && auth.isAuthenticated) {
        return next({ name: 'dashboard' });
    }
    next();
});

export default router;
