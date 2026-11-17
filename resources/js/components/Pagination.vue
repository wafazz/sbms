<script setup>
import { computed } from 'vue';

const props = defineProps({
    // Laravel paginator meta: { current_page, last_page, total }
    meta: { type: Object, default: null },
});

const emit = defineEmits(['page']);

const hasPages = computed(() => props.meta && props.meta.last_page > 1);
</script>

<template>
    <nav v-if="hasPages" class="mt-4 flex items-center justify-between text-sm text-slate-600" aria-label="Pagination">
        <span>Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</span>
        <div class="flex gap-2">
            <button
                type="button"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50 disabled:opacity-50"
                :disabled="meta.current_page <= 1"
                @click="emit('page', meta.current_page - 1)"
            >
                Previous
            </button>
            <button
                type="button"
                class="rounded-md border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50 disabled:opacity-50"
                :disabled="meta.current_page >= meta.last_page"
                @click="emit('page', meta.current_page + 1)"
            >
                Next
            </button>
        </div>
    </nav>
</template>
