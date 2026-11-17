import { defineStore } from 'pinia';
import { http } from '../api/http.js';

export const useSettingsStore = defineStore('settings', {
    state: () => ({
        company: null,
    }),
    actions: {
        async fetch() {
            const { data } = await http.get('/settings');
            this.company = data.data;
            return this.company;
        },
        /** POST with FormData so the logo upload works. Axios sets the multipart boundary itself. */
        async save(formData) {
            const { data } = await http.post('/settings', formData);
            this.company = data.data;
            return this.company;
        },
    },
});
