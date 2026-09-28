<template>
  <div class="min-vh-100 d-flex align-items-center justify-content-center py-5 px-3" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #020617 100%);">
    <div class="card border-0 shadow-lg text-white" style="max-width: 440px; width: 100%; background: rgba(30, 41, 59, 0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); rounded: 16px;">
      <div class="card-body p-4 p-md-5">
        <!-- Brand Header -->
        <div class="text-center mb-4">
          <div class="bg-warning bg-opacity-10 p-3 rounded-circle d-inline-flex align-items-center justify-content-center mb-3 border border-warning-subtle shadow-sm" style="width: 70px; height: 70px;">
            <i class="bi bi-shield-lock-fill fs-1 text-warning"></i>
          </div>
          <h3 class="fw-bold text-white mb-1">إدارة النظام المركزية</h3>
          <small class="text-slate-400" style="color: #94a3b8;">Super Admin Platform Control Panel</small>
        </div>

        <!-- Alert Error Message -->
        <div v-if="errorMessage" class="alert alert-danger py-2 small mb-4 text-center border-0 shadow-sm">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ errorMessage }}
        </div>

        <form @submit.prevent="handleLogin">
          <div class="mb-3">
            <label class="form-label text-slate-300 small fw-bold" style="color: #cbd5e1;">البريد الإلكتروني لمدير النظام</label>
            <div class="input-group">
              <span class="input-group-text bg-slate-800 text-slate-400 border-slate-700" style="background-color: #0f172a; border-color: #334155; color: #94a3b8;"><i class="bi bi-envelope"></i></span>
              <input 
                v-model="email" 
                type="email" 
                class="form-control text-white bg-slate-900 border-slate-700 shadow-none" 
                style="background-color: #020617; border-color: #334155; color: #ffffff;"
                placeholder="admin@accounts.com" 
                required 
              />
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label text-slate-300 small fw-bold" style="color: #cbd5e1;">كلمة السر الإدارية</label>
            <div class="input-group">
              <span class="input-group-text bg-slate-800 text-slate-400 border-slate-700" style="background-color: #0f172a; border-color: #334155; color: #94a3b8;"><i class="bi bi-lock"></i></span>
              <input 
                v-model="password" 
                type="password" 
                class="form-control text-white bg-slate-900 border-slate-700 shadow-none" 
                style="background-color: #020617; border-color: #334155; color: #ffffff;"
                placeholder="••••••••" 
                required 
              />
            </div>
          </div>

          <button 
            type="submit" 
            class="btn btn-warning w-100 py-3 fw-bold text-dark rounded-3 shadow" 
            :disabled="loading"
          >
            <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
            <i v-else class="bi bi-shield-check me-2 fs-5"></i>
            تسجيل الدخول كـ مدير النظام
          </button>
        </form>

        <div class="mt-4 pt-3 border-top border-slate-800 text-center">
          <router-link to="/login" class="text-slate-400 text-decoration-none small" style="color: #94a3b8;">
            <i class="bi bi-arrow-right me-1"></i> العودة لتسجيل دخول الشركات العادي
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const email = ref(import.meta.env.DEV ? 'superadmin@accounts.com' : '');
const password = ref(import.meta.env.DEV ? 'superadmin123' : '');
const errorMessage = ref('');
const loading = ref(false);

const handleLogin = async () => {
  errorMessage.value = '';
  loading.value = true;

  try {
    const res = await axios.post('/api/v1/superadmin/login', {
      email: email.value,
      password: password.value,
    });

    if (res.data.success) {
      localStorage.setItem('superadmin_token', res.data.data.token);
      localStorage.setItem('superadmin_user', JSON.stringify(res.data.data.user));
      // Also store main token for company actions
      localStorage.setItem('accounting_token', res.data.data.token);
      axios.defaults.headers.common['Authorization'] = `Bearer ${res.data.data.token}`;
      router.push('/superadmin');
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'فشل تسجيل الدخول كـ مدير النظام.';
  } finally {
    loading.value = false;
  }
};
</script>
