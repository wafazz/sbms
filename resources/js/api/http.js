import axios from 'axios';
import { useToastStore } from '../stores/toast.js';

/**
 * The single Axios instance for the whole SPA.
 * Sanctum SPA auth: cookies + the XSRF header are sent automatically.
 */
export const http = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    timeout: 15000,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/**
 * Sanctum needs a CSRF cookie before the first state-changing request.
 * It lives at the site root, not under /api/v1.
 */
export async function ensureCsrfCookie() {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

const AUTH_PATHS = ['/auth/me', '/auth/login'];

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const path = error.config?.url ?? '';
        const toast = useToastStore();

        if (status === 401) {
            const isAuthCall = AUTH_PATHS.some((p) => path.endsWith(p));

            if (!isAuthCall && window.location.pathname !== '/login') {
                toast.push('Your session has ended. Please sign in again.', 'error');
                window.location.assign('/login');
            }
        } else if (status === 403) {
            toast.push(error.response?.data?.message ?? 'You do not have permission to do that.', 'error');
        } else if (status === 429) {
            toast.push(error.response?.data?.message ?? 'Too many requests. Please slow down.', 'error');
        } else if (!status || status >= 500) {
            toast.push('Something went wrong. Please try again.', 'error');
        }

        // 422 is left to the caller so forms can show field errors.
        return Promise.reject(error);
    },
);
