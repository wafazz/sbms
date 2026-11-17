<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useUsersStore } from '../../stores/users.js';
import { useToastStore } from '../../stores/toast.js';
import { useAuthStore } from '../../stores/auth.js';
import { errorMessage } from '../../api/errors.js';
import DataTable from '../../components/DataTable.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import Pagination from '../../components/Pagination.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import UserFormModal from './UserFormModal.vue';

const users = useUsersStore();
const toast = useToastStore();
const auth = useAuthStore();

const search = ref('');
const page = ref(1);
const form = reactive({ open: false, user: null });
const confirm = reactive({ open: false, user: null, busy: false });

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: '', class: 'text-right' },
];

async function load() {
    try {
        await users.fetch({ page: page.value, search: search.value });
    } catch {
        /* interceptor toast */
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
    form.user = null;
    form.open = true;
}

function openEdit(user) {
    form.user = user;
    form.open = true;
}

const isSelf = (user) => auth.user?.id === user.id;

async function applyToggle() {
    if (!confirm.user) return;
    confirm.busy = true;
    try {
        const next = !confirm.user.is_active;
        await users.update(confirm.user.id, { is_active: next });
        toast.push(next ? 'User reactivated.' : 'User deactivated.');
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
                placeholder="Search name or email"
                aria-label="Search users"
                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-50 sm:max-w-xs"
            />
            <button type="button" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700" @click="openCreate">Add user</button>
        </div>

        <DataTable
            :columns="columns"
            :rows="users.rows"
            :loading="users.loading"
            empty-title="No users found"
            empty-description="Try a different search, or add a user."
        >
            <template #cell-role="{ row }">
                <StatusBadge :status="row.role" :label="row.role_label" />
            </template>
            <template #cell-is_active="{ row }">
                <StatusBadge :status="row.is_active ? 'active' : 'inactive'" :label="row.is_active ? 'Active' : 'Inactive'" />
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="openEdit(row)">Edit</button>
                    <button
                        v-if="!isSelf(row)"
                        type="button"
                        class="rounded-md border px-2.5 py-1 text-xs font-medium"
                        :class="row.is_active ? 'border-rose-200 text-rose-700 hover:bg-rose-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"
                        @click="(confirm.user = row) && (confirm.open = true)"
                    >
                        {{ row.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                    <span v-else class="self-center text-xs text-slate-400">You</span>
                </div>
            </template>
        </DataTable>

        <Pagination :meta="users.meta" @page="(p) => ((page = p), load())" />

        <UserFormModal :open="form.open" :user="form.user" @close="form.open = false" @saved="load" />

        <ConfirmModal
            :open="confirm.open"
            :title="confirm.user?.is_active ? 'Deactivate user?' : 'Activate user?'"
            :message="confirm.user?.is_active ? `${confirm.user?.name} will no longer be able to sign in.` : `${confirm.user?.name} will be able to sign in again.`"
            :confirm-label="confirm.user?.is_active ? 'Deactivate' : 'Activate'"
            :danger="!!confirm.user?.is_active"
            :busy="confirm.busy"
            @confirm="applyToggle"
            @cancel="confirm.open = false"
        />
    </div>
</template>
