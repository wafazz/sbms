import { defineStore } from 'pinia';
import { http } from '../api/http.js';

export const useClientsStore = defineStore('clients', {
    state: () => ({
        rows: [],
        meta: null,
        loading: false,
    }),
    actions: {
        async fetch(params = {}) {
            this.loading = true;
            try {
                const { data } = await http.get('/clients', { params });
                this.rows = data.data;
                this.meta = data.meta;
            } finally {
                this.loading = false;
            }
        },
        /** Every client for dropdowns (first 100). */
        async options() {
            const { data } = await http.get('/clients', { params: { per_page: 100 } });
            return data.data;
        },
        async create(payload) {
            const { data } = await http.post('/clients', payload);
            return data.data;
        },
        async update(id, payload) {
            const { data } = await http.put(`/clients/${id}`, payload);
            return data.data;
        },
        async remove(id) {
            await http.delete(`/clients/${id}`);
        },
    },
});
