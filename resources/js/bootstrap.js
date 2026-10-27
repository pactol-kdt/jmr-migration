import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.interceptors.request.use((config) => {
    if (window.__csrf) {
        config.headers['X-CSRF-Token'] = window.__csrf;
    }

    return config;
});
