import { defineStore } from 'pinia';
import { http } from '../api/http.js';

export const useUsersStore = defineStore('users', {
    state: () => ({
        rows: [],
        meta: null,
        loading: false,
    }),
    actions: {
        async fetch({ page = 1, search = '' } = {}) {
            this.loading = true;
            try {
                const { data } = await http.get('/users', { params: { page, search } });
                this.rows = data.data;
                this.meta = data.meta;
            } finally {
                this.loading = false;
            }
        },
        async create(payload) {
            const { data } = await http.post('/users', payload);
            return data.data;
        },
        async update(id, payload) {
            const { data } = await http.put(`/users/${id}`, payload);
            return data.data;
        },
    },
});
