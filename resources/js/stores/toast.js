import { defineStore } from 'pinia';

const DISMISS_AFTER_MS = 4000;
let nextId = 1;

export const useToastStore = defineStore('toast', {
    state: () => ({
        items: [],
    }),
    actions: {
        push(message, type = 'success') {
            const id = nextId++;

            this.items.push({ id, message, type });
            setTimeout(() => this.dismiss(id), DISMISS_AFTER_MS);
        },
        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    },
});
