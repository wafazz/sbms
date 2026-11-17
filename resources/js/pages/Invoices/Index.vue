<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import InvoiceFormModal from './InvoiceFormModal.vue';
import { useInvoicesStore } from '../../stores/invoices.js';
import { useClientsStore } from '../../stores/clients.js';
import { useToastStore } from '../../stores/toast.js';
import { errorMessage } from '../../api/errors.js';
import DataTable from '../../components/DataTable.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import Pagination from '../../components/Pagination.vue';

const invoices = useInvoicesStore();
const clients = useClientsStore();
const toast = useToastStore();

const route = useRoute();
const router = useRouter();
const form = reactive({ open: false });

// The sidebar submenu links to ?status=..., so the status filter follows the URL.
const filters = reactive({
    q: '',
    status: typeof route.query.status === 'string' ? route.query.status : '',
    client_id: '',
    from: '',
    to: '',
    sort: 'issue_date',
    dir: 'desc',
});

watch(() => route.query.status, (status) => {
    filters.status = typeof status === 'string' ? status : '';
});
const page = ref(1);
const clientOptions = ref([]);

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'partially_paid', label: 'Partially paid' },
    { value: 'paid', label: 'Paid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'cancelled', label: 'Cancelled' },
];

const columns = [
    { key: 'invoice_number', label: 'Number' },
    { key: 'client', label: 'Client' },
    { key: 'issue_date', label: 'Issued' },
    { key: 'due_date', label: 'Due' },
    { key: 'status', label: 'Status' },
    { key: 'total', label: 'Total', class: 'text-right' },
    { key: 'balance', label: 'Balance', class: 'text-right' },
];

function params() {
    const out = { page: page.value, sort: filters.sort, dir: filters.dir };
    for (const key of ['q', 'status', 'client_id', 'from', 'to']) {
        if (filters[key] !== '') out[key] = filters[key];
    }
    return out;
}

async function load() {
    try {
        await invoices.fetch(params());
    } catch (error) {
        toast.push(errorMessage(error), 'error');
    }
}

let timer = null;
watch(() => filters.q, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        page.value = 1;
        load();
    }, 300);
});
watch(() => [filters.status, filters.client_id, filters.from, filters.to], () => {
    page.value = 1;
    load();
});

function money(value) {
    return Number(value ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

onMounted(async () => {
    clientOptions.value = await clients.options().catch(() => []);
    load();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <input v-model="filters.q" type="search" placeholder="Search invoice number" aria-label="Search invoices" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm" />
                <select v-model="filters.status" aria-label="Filter by status" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <select v-model="filters.client_id" aria-label="Filter by client" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    <option value="">All clients</option>
                    <option v-for="c in clientOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <input v-model="filters.from" type="date" aria-label="Issued from" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm" />
                <input v-model="filters.to" type="date" aria-label="Issued to" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm" />
            </div>
            <button type="button" class="rounded-md bg-brand-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-brand-700" @click="form.open = true">New invoice</button>
        </div>

        <DataTable :columns="columns" :rows="invoices.rows" :loading="invoices.loading" empty-title="No invoices match" empty-description="Change the filters or create an invoice.">
            <template #cell-invoice_number="{ row }">
                <RouterLink :to="{ name: 'invoice-show', params: { id: row.id } }" class="font-mono text-xs text-brand-700 hover:underline">{{ row.invoice_number }}</RouterLink>
            </template>
            <template #cell-client="{ row }">{{ row.client?.name }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status.value" :label="row.status.label" /></template>
            <template #cell-total="{ row }"><span class="block text-right">{{ row.currency }} {{ money(row.total) }}</span></template>
            <template #cell-balance="{ row }"><span class="block text-right">{{ row.currency }} {{ money(row.balance) }}</span></template>
        </DataTable>

        <div v-if="invoices.totals" class="flex flex-wrap justify-end gap-6 rounded-lg bg-white px-5 py-3 text-sm shadow-sm ring-1 ring-slate-200" aria-label="Totals for the filtered invoices">
            <span class="text-slate-500">{{ invoices.totals.count }} invoice(s)</span>
            <span>Total <strong class="text-slate-900">MYR {{ money(invoices.totals.total) }}</strong></span>
            <span>Paid <strong class="text-slate-900">MYR {{ money(invoices.totals.amount_paid) }}</strong></span>
            <span>Balance <strong class="text-slate-900">MYR {{ money(invoices.totals.balance) }}</strong></span>
        </div>

        <p class="text-xs text-slate-500">
            Sort:
            <button v-for="key in ['issue_date', 'due_date', 'total', 'balance']" :key="key" type="button" class="mx-1 underline" @click="filters.sort = key, filters.dir = filters.dir === 'asc' ? 'desc' : 'asc', load()">
                {{ key.replace('_', ' ') }}<span v-if="filters.sort === key">{{ filters.dir === 'asc' ? ' ↑' : ' ↓' }}</span>
            </button>
        </p>

        <Pagination :meta="invoices.meta" @page="(p) => ((page = p), load())" />

        <InvoiceFormModal
            :open="form.open"
            @close="form.open = false"
            @saved="(inv) => router.push({ name: 'invoice-show', params: { id: inv.id } })"
        />
    </div>
</template>
