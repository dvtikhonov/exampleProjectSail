/**
 * Axios-клиент для Laravel API (Sanctum SPA authentication).
 *
 * withCredentials — отправка session cookie; CSRF берётся из meta-тега Blade.
 * Для /sanctum/csrf-cookie вызывающий код переопределяет baseURL на ''.
 */
import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

api.interceptors.request.use((config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }

    return config;
});

export default api;
