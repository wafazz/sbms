<script setup>
defineProps({
    columns: { type: Array, required: true }, // [{ key, label, class? }]
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    rowKey: { type: String, default: 'id' },
    emptyTitle: { type: String, default: 'Nothing here yet' },
    emptyDescription: { type: String, default: '' },
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                            :class="column.class"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <template v-if="loading">
                        <tr v-for="n in 5" :key="`skeleton-${n}`" aria-hidden="true">
                            <td v-for="column in columns" :key="column.key" class="px-4 py-3">
                                <div class="h-4 w-full animate-pulse rounded bg-slate-200" />
                            </td>
                        </tr>
                    </template>

                    <tr v-else-if="rows.length === 0">
                        <td :colspan="columns.length" class="p-0">
                            <div class="p-6">
                                <EmptyState :title="emptyTitle" :description="emptyDescription" />
                            </div>
                        </td>
                    </tr>

                    <template v-else>
                        <tr v-for="row in rows" :key="row[rowKey]" class="hover:bg-slate-50">
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="whitespace-nowrap px-4 py-3 text-slate-700"
                                :class="column.class"
                            >
                                <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                    {{ row[column.key] }}
                                </slot>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
