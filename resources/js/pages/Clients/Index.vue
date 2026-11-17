<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useClientsStore } from '../../stores/clients.js';
import { useToastStore } from '../../stores/toast.js';
import { useAuthStore } from '../../stores/auth.js';
import { errorMessage } from '../../api/errors.js';
import DataTable from '../../components/DataTable.vue';
import Pagination from '../../components/Pagination.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import ClientFormModal from './ClientFormModal.vue';

const clients = useClientsStore();
const toast = useToastStore();
const auth = useAuthStore();

const search = ref('');
const page = ref(1);
const form = reactive({ open: false, client: null });
const confirm = reactive({ open: false, client: null, busy: false });

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'company', label: 'Company' },
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'Phone' },
    { key: 'projects_count', label: 'Projects' },
    { key: 'actions', label: '', class: 'text-right' },
];

async function load() {
    try {
        await clients.fetch({ q: search.value, page: page.value });
    } catch {
        /* interceptor shows the toast */
    }
}

let timer = null;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        page.value = 1;
        load();
    }, 300);
});

function openCreate() {
    form.client = null;
    form.open = true;
}

function openEdit(client) {
    form.client = client;
    form.open = true;
}

async function remove() {
    confirm.busy = true;
    try {
        await clients.remove(confirm.client.id);
        toast.push('Client deleted.');
        confirm.open = false;
        load();
    } catch (error) {
        toast.push(errorMessage(error), 'error');
        confirm.open = false;
    } finally {
        confirm.busy = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <input
                v-model="search"
                type="search"
                placeholder="Search name, company or email"
                aria-label="Search clients"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-50 sm:max-w-xs"
            />
            <button type="button" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700" @click="openCreate">
                Add client
            </button>
        </div>

        <DataTable :columns="columns" :rows="clients.rows" :loading="clients.loading" empty-title="No clients yet" empty-description="Add your first client to start a project.">
            <template #cell-name="{ row }">
                <span class="font-medium text-slate-900">{{ row.name }}</span>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="openEdit(row)">Edit</button>
                    <button
                        v-if="auth.isAdmin"
                        type="button"
                        class="rounded-md border border-rose-200 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50"
                        @click="(confirm.client = row) && (confirm.open = true)"
                    >
                        Delete
                    </button>
                </div>
            </template>
        </DataTable>

        <Pagination :meta="clients.meta" @page="(p) => ((page = p), load())" />

        <ClientFormModal :open="form.open" :client="form.client" @close="form.open = false" @saved="load" />

        <ConfirmModal
            :open="confirm.open"
            title="Delete client?"
            :message="`${confirm.client?.name ?? ''} will be removed. Clients with projects or invoices cannot be deleted.`"
            confirm-label="Delete"
            danger
            :busy="confirm.busy"
            @confirm="remove"
            @cancel="confirm.open = false"
        />
    </div>
</template>
