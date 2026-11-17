import { defineStore } from 'pinia';
import { http, ensureCsrfCookie } from '../api/http.js';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        initialised: false,
    }),
    getters: {
        isAuthenticated: (state) => state.user !== null,
        isAdmin: (state) => state.user?.role === 'admin',
    },
    actions: {
        /** Restore the session on app start. Any failure means "not signed in". */
        async fetchMe() {
            try {
                const { data } = await http.get('/auth/me');
                this.user = data.data;
            } catch {
                this.user = null;
            } finally {
                this.initialised = true;
            }
        },
        async login(credentials) {
            await ensureCsrfCookie();
            const { data } = await http.post('/auth/login', credentials);
            this.user = data.data;
        },
        async logout() {
            try {
                await http.post('/auth/logout');
            } finally {
                this.user = null;
            }
        },
    },
});
