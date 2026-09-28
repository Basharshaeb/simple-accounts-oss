<template>
  <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="card p-4 shadow-lg border-0" style="max-width: 420px; width: 100%;">
      <div class="text-center mb-4">
        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block mb-3">
          <i class="bi bi-calculator fs-1"></i>
        </div>
        <h4 class="fw-bold">النظام المحاسبي المؤسسي</h4>
        <p class="text-muted small">سجل الدخول لبدء إدارة الحسابات والقيود المالية</p>
      </div>

      <div v-if="errorMessage" class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ errorMessage }}
      </div>

      <form @submit.prevent="handleLogin">
        <div class="mb-3">
          <label class="form-label font-weight-bold">رمز وكود الشركة</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-building"></i></span>
            <input v-model="companyCode" type="text" class="form-control" placeholder="مثال: COMP-001" required />
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label font-weight-bold">البريد الإلكتروني</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input v-model="email" type="email" class="form-control" placeholder="admin@accounts.com" required />
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label font-weight-bold">كلمة المرور</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input v-model="password" type="password" class="form-control" placeholder="••••••••" required />
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" :disabled="loading">
          <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
          تسجيل الدخول
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/authStore';

const router = useRouter();
const auth = useAuthStore();

const isDev = import.meta.env.DEV;
const companyCode = ref(isDev ? 'ADV-SOL' : '');
const email = ref(isDev ? 'admin@accounts.com' : '');
const password = ref(isDev ? 'password123' : '');
const loading = ref(false);
const errorMessage = ref('');

const handleLogin = async () => {
  loading.value = true;
  errorMessage.value = '';
  try {
    await auth.login(email.value, password.value, companyCode.value);
    router.push({ name: 'dashboard' });
  } catch (err) {
    errorMessage.value = err.message || 'فشل تسجيل الدخول';
  } finally {
    loading.value = false;
  }
};
</script>
