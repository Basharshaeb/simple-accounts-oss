import axios from 'axios';
import * as bootstrap from 'bootstrap';

window.axios = axios;
window.bootstrap = bootstrap;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';


const token = localStorage.getItem('auth_token');
if (token) {
    window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

const currentCompany = JSON.parse(localStorage.getItem('current_company') || 'null');
if (currentCompany) {
    window.axios.defaults.headers.common['X-Company-ID'] = currentCompany.id;
}
