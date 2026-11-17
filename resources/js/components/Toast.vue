<script setup>
import { useToastStore } from '../stores/toast.js';

const toast = useToastStore();

const styles = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    error: 'border-rose-200 bg-rose-50 text-rose-800',
    info: 'border-slate-200 bg-white text-slate-800',
};
</script>

<template>
    <div class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-4" aria-live="polite">
        <div
            v-for="item in toast.items"
            :key="item.id"
            role="status"
            class="pointer-events-auto flex w-full max-w-sm items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm shadow-md sm:w-80"
            :class="styles[item.type] ?? styles.info"
        >
            <span>{{ item.message }}</span>
            <button type="button" class="text-slate-500 hover:text-slate-800" aria-label="Dismiss" @click="toast.dismiss(item.id)">
                &times;
            </button>
        </div>
    </div>
</template>
