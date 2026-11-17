<script setup>
import { RouterLink } from 'vue-router';
import NavIcon from './NavIcon.vue';

/**
 * Expandable sidebar section with one entry per status and a count badge.
 * Used for Projects and Invoices.
 */
const props = defineProps({
    label: { type: String, required: true },
    icon: { type: String, required: true },
    routeName: { type: String, required: true }, // list page, e.g. "projects"
    statuses: { type: Array, default: () => [] }, // [{ value, label, count }]
    total: { type: Number, default: 0 },
    open: { type: Boolean, default: false },
    sectionActive: { type: Boolean, default: false },
    activeStatus: { type: String, default: null }, // status currently filtered, or null
    allActive: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'navigate']);

const badgeClass = (count) => (count ? 'bg-brand-600 text-white' : 'bg-slate-700 text-slate-400');
</script>

<template>
    <div>
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm font-medium hover:bg-slate-800 hover:text-white"
            :class="sectionActive ? 'bg-slate-800 text-white' : 'text-slate-300'"
            :aria-expanded="open"
            :aria-controls="`${routeName}-submenu`"
            @click="emit('toggle')"
        >
            <span class="flex items-center gap-2">
                <NavIcon :name="icon" />
                {{ label }}
                <span v-if="total" class="rounded-full bg-slate-700 px-2 py-0.5 text-xs font-semibold text-slate-200 tabular-nums">{{ total }}</span>
            </span>
            <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>

        <ul v-if="open" :id="`${routeName}-submenu`" class="mt-1 space-y-0.5 pl-3">
            <li>
                <RouterLink
                    :to="{ name: routeName }"
                    class="flex items-center justify-between rounded-md px-3 py-1.5 text-sm hover:bg-slate-800 hover:text-white"
                    :class="allActive ? 'bg-slate-800 text-white' : 'text-slate-400'"
                    :aria-current="allActive ? 'page' : null"
                    @click="emit('navigate')"
                >
                    <span>All</span>
                    <span class="min-w-[1.5rem] rounded-full bg-slate-700 px-2 py-0.5 text-center text-xs font-semibold text-slate-200 tabular-nums">{{ total }}</span>
                </RouterLink>
            </li>
            <li v-for="status in statuses" :key="status.value">
                <RouterLink
                    :to="{ name: routeName, query: { status: status.value } }"
                    class="flex items-center justify-between rounded-md px-3 py-1.5 text-sm hover:bg-slate-800 hover:text-white"
                    :class="activeStatus === status.value ? 'bg-slate-800 text-white' : 'text-slate-400'"
                    :aria-current="activeStatus === status.value ? 'page' : null"
                    @click="emit('navigate')"
                >
                    <span>{{ status.label }}</span>
                    <span
                        class="min-w-[1.5rem] rounded-full px-2 py-0.5 text-center text-xs font-semibold tabular-nums"
                        :class="badgeClass(status.count)"
                        :aria-label="`${status.count} ${status.label.toLowerCase()}`"
                    >
                        {{ status.count }}
                    </span>
                </RouterLink>
            </li>
        </ul>
    </div>
</template>
