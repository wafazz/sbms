<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: null },
});

// One place for every status colour in the app.
const palette = {
    // generic
    active: 'bg-emerald-100 text-emerald-800',
    inactive: 'bg-slate-200 text-slate-700',
    // roles
    admin: 'bg-violet-100 text-violet-800',
    staff: 'bg-sky-100 text-sky-800',
    // projects (used from Phase 2)
    planning: 'bg-amber-100 text-amber-800',
    on_hold: 'bg-orange-100 text-orange-800',
    completed: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-slate-200 text-slate-700',
    // invoices (used from Phase 4)
    draft: 'bg-slate-200 text-slate-700',
    sent: 'bg-sky-100 text-sky-800',
    partially_paid: 'bg-amber-100 text-amber-800',
    paid: 'bg-emerald-100 text-emerald-800',
    overdue: 'bg-rose-100 text-rose-800',
};

const classes = computed(() => palette[props.status] ?? 'bg-slate-100 text-slate-700');
const text = computed(() => props.label ?? props.status.replace(/_/g, ' '));
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="classes">
        {{ text }}
    </span>
</template>
