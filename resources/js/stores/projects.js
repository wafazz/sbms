import { defineStore } from 'pinia';
import { http } from '../api/http.js';

export const useProjectsStore = defineStore('projects', {
    state: () => ({
        rows: [],
        meta: null,
        loading: false,
        current: null,
        activities: [],
        statusCounts: [],
        totalCount: 0,
    }),
    actions: {
        /** Counts per status for the sidebar badges. A failure keeps the last known numbers. */
        async fetchStatusCounts() {
            try {
                const { data } = await http.get('/projects/status-counts');
                this.statusCounts = data.data;
                this.totalCount = data.total;
            } catch {
                /* the sidebar stays usable without badges */
            }
        },
        async fetch(params = {}) {
            this.loading = true;
            try {
                const { data } = await http.get('/projects', { params });
                this.rows = data.data;
                this.meta = data.meta;
            } finally {
                this.loading = false;
            }
        },
        async show(id) {
            const { data } = await http.get(`/projects/${id}`);
            this.current = data.data;
            this.activities = data.activities ?? [];
            return this.current;
        },
        async create(payload) {
            const { data } = await http.post('/projects', payload);
            return data.data;
        },
        async update(id, payload) {
            const { data } = await http.put(`/projects/${id}`, payload);
            this.current = data.data;
            return data.data;
        },
        async remove(id) {
            await http.delete(`/projects/${id}`);
        },
    },
});
