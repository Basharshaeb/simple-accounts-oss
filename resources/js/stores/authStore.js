import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('auth_token') || '',
        user: JSON.parse(localStorage.getItem('auth_user') || 'null'),
        currentCompany: JSON.parse(localStorage.getItem('current_company') || 'null'),
        userCompanies: JSON.parse(localStorage.getItem('user_companies') || '[]'),
    }),
    getters: {
        isAuthenticated: (state) => !!state.token,
    },
    actions: {
        setToken(token) {
            this.token = token;
            localStorage.setItem('auth_token', token);
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        },
        setCurrentCompany(company) {
            this.currentCompany = company;
            localStorage.setItem('current_company', JSON.stringify(company));
            if (company) {
                axios.defaults.headers.common['X-Company-ID'] = company.id;
            }
        },
        async login(email, password, companyCode = '') {
            const payload = { email, password };
            if (companyCode) payload.company_code = companyCode;
            const res = await axios.post('/api/v1/auth/login', payload);
            if (res.data.success) {
                this.setToken(res.data.data.token);
                this.user = res.data.data.user;
                localStorage.setItem('auth_user', JSON.stringify(this.user));

                if (res.data.data.current_company) {
                    this.setCurrentCompany(res.data.data.current_company);
                }
                return res.data;
            }
            throw new Error(res.data.message || 'فشل تسجيل الدخول');
        },
        async fetchMe() {
            if (!this.token) return;
            axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
            if (this.currentCompany) {
                axios.defaults.headers.common['X-Company-ID'] = this.currentCompany.id;
            }

            try {
                const res = await axios.get('/api/v1/auth/me');
                if (res.data.success) {
                    this.user = res.data.data.user;
                    this.userCompanies = res.data.data.companies;
                    localStorage.setItem('auth_user', JSON.stringify(this.user));
                    localStorage.setItem('user_companies', JSON.stringify(this.userCompanies));

                    if (!this.currentCompany && this.userCompanies.length > 0) {
                        this.setCurrentCompany(this.userCompanies[0].company);
                    }
                }
            } catch (err) {
                this.logout();
            }
        },
        switchCompany(company) {
            this.setCurrentCompany(company);
            window.location.reload();
        },
        logout() {
            this.token = '';
            this.user = null;
            this.currentCompany = null;
            this.userCompanies = [];
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            localStorage.removeItem('current_company');
            localStorage.removeItem('user_companies');
            delete axios.defaults.headers.common['Authorization'];
            delete axios.defaults.headers.common['X-Company-ID'];
        }
    }
});
