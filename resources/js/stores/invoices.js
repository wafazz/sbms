import { defineStore } from 'pinia';
import { http } from '../api/http.js';

export const useInvoicesStore = defineStore('invoices', {
    state: () => ({
        rows: [],
        meta: null,
        totals: null,
        loading: false,
        current: null,
        statusCounts: [],
        totalCount: 0,
    }),
    actions: {
        /** Counts per status for the sidebar badges. A failure keeps the last known numbers. */
        async fetchStatusCounts() {
            try {
                const { data } = await http.get('/invoices/status-counts');
                this.statusCounts = data.data;
                this.totalCount = data.total;
            } catch {
                /* the sidebar stays usable without badges */
            }
        },
        async fetch(params = {}) {
            this.loading = true;
            try {
                const { data } = await http.get('/invoices', { params });
                this.rows = data.data;
                this.meta = data.meta;
                this.totals = data.totals;
            } finally {
                this.loading = false;
            }
        },
        async show(id) {
            const { data } = await http.get(`/invoices/${id}`);
            this.current = data.data;
            return data.data;
        },
        async create(payload) {
            const { data } = await http.post('/invoices', payload);
            return data.data;
        },
        async createForProject(projectId, payload) {
            const { data } = await http.post(`/projects/${projectId}/invoices`, payload);
            return data.data;
        },
        async update(id, payload) {
            const { data } = await http.put(`/invoices/${id}`, payload);
            this.current = data.data;
            return data.data;
        },
        async send(id) {
            const { data } = await http.post(`/invoices/${id}/send`);
            this.current = data.data;
            return data.data;
        },
        async cancel(id) {
            const { data } = await http.post(`/invoices/${id}/cancel`);
            this.current = data.data;
            return data.data;
        },
        async recordPayment(id, payload) {
            const { data } = await http.post(`/invoices/${id}/payments`, payload);
            this.current = data.data;
            return data.data;
        },
        async remove(id) {
            await http.delete(`/invoices/${id}`);
        },
    },
});
