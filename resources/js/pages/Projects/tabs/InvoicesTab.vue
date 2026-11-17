<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useInvoicesStore } from '../../../stores/invoices.js';
import { errorMessage } from '../../../api/errors.js';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import InvoiceFormModal from '../../Invoices/InvoiceFormModal.vue';

const props = defineProps({
    project: { type: Object, required: true },
    activities: { type: Array, default: () => [] },
});

const invoices = useInvoicesStore();
const router = useRouter();
const error = ref('');
const formOpen = ref(false);

const columns = [
    { key: 'invoice_number', label: 'Number' },
    { key: 'issue_date', label: 'Issued' },
    { key: 'status', label: 'Status' },
    { key: 'total', label: 'Total', class: 'text-right' },
    { key: 'balance', label: 'Balance', class: 'text-right' },
];

async function load() {
    error.value = '';
    try {
        await invoices.fetch({ project_id: props.project.id, per_page: 100, sort: 'issue_date', dir: 'desc' });
    } catch (e) {
        error.value = errorMessage(e, 'Could not load invoices.');
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-slate-600">Invoices raised for this project.</p>
            <button type="button" class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700" @click="formOpen = true">
                New invoice
            </button>
        </div>

        <p v-if="error" role="alert" class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ error }}</p>

        <DataTable :columns="columns" :rows="invoices.rows" :loading="invoices.loading" empty-title="No invoices yet" empty-description="Create an invoice from this project when work is ready to bill.">
            <template #cell-invoice_number="{ row }">
                <RouterLink :to="{ name: 'invoice-show', params: { id: row.id } }" class="font-mono text-xs text-brand-700 hover:underline">{{ row.invoice_number }}</RouterLink>
            </template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status.value" :label="row.status.label" /></template>
            <template #cell-total="{ row }"><span class="block text-right tabular-nums">{{ row.currency }} {{ row.total }}</span></template>
            <template #cell-balance="{ row }"><span class="block text-right tabular-nums">{{ row.currency }} {{ row.balance }}</span></template>
        </DataTable>

        <InvoiceFormModal
            :open="formOpen"
            :project-id="project.id"
            @close="formOpen = false"
            @saved="(inv) => { load(); router.push({ name: 'invoice-show', params: { id: inv.id } }); }"
        />
    </div>
</template>
