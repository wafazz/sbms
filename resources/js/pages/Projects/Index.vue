<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import ProjectFormModal from './ProjectFormModal.vue';
import { useProjectsStore } from '../../stores/projects.js';
import { useClientsStore } from '../../stores/clients.js';
import { useToastStore } from '../../stores/toast.js';
import { errorMessage } from '../../api/errors.js';
import DataTable from '../../components/DataTable.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import Pagination from '../../components/Pagination.vue';

const projects = useProjectsStore();
const clients = useClientsStore();
const toast = useToastStore();

const route = useRoute();
const router = useRouter();

// The sidebar submenu links to ?status=..., so the status filter follows the URL.
const filters = reactive({
    q: '',
    status: typeof route.query.status === 'string' ? route.query.status : '',
    priority: '',
    client_id: '',
    sort: 'created_at',
    dir: 'desc',
});

watch(() => route.query.status, (status) => {
    filters.status = typeof status === 'string' ? status : '';
});
const page = ref(1);
const clientOptions = ref([]);

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'planning', label: 'Planning' },
    { value: 'active', label: 'Active' },
    { value: 'on_hold', label: 'On hold' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];
const priorityOptions = [
    { value: '', label: 'All priorities' },
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
];

const columns = [
    { key: 'code', label: 'Code' },
    { key: 'name', label: 'Project' },
    { key: 'client', label: 'Client' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Priority' },
    { key: 'end_date', label: 'Ends' },
    { key: 'progress', label: 'Progress' },
    { key: 'actions', label: '', class: 'text-right' },
];

const activeParams = computed(() => {
    const params = { page: page.value, sort: filters.sort, dir: filters.dir };
    for (const key of ['q', 'status', 'priority', 'client_id']) {
        if (filters[key] !== '') params[key] = filters[key];
    }
    return params;
});

async function load() {
    try {
        await projects.fetch(activeParams.value);
    } catch {
        /* toast from interceptor */
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
watch(() => [filters.status, filters.priority, filters.client_id], () => {
    page.value = 1;
    load();
});

const form = reactive({ open: false, projectId: null });

function openCreate() {
    form.projectId = null;
    form.open = true;
}

function openEdit(row) {
    form.projectId = row.id;
    form.open = true;
}

function onSaved(project) {
    projects.fetchStatusCounts();
    // A new project opens its detail page. An edit just refreshes the list.
    if (project && !form.projectId) {
        router.push({ name: 'project-show', params: { id: project.id } });
    } else {
        load();
    }
}

function sortBy(key) {
    if (filters.sort === key) {
        filters.dir = filters.dir === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort = key;
        filters.dir = 'asc';
    }
    load();
}

onMounted(async () => {
    try {
        clientOptions.value = await clients.options();
    } catch {
        toast.push(errorMessage(null, 'Could not load clients.'), 'error');
    }
    load();
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <input
                    v-model="filters.q"
                    type="search"
                    placeholder="Search name or code"
                    aria-label="Search projects"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-50"
                />
                <select v-model="filters.status" aria-label="Filter by status" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <select v-model="filters.priority" aria-label="Filter by priority" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    <option v-for="o in priorityOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <select v-model="filters.client_id" aria-label="Filter by client" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm">
                    <option value="">All clients</option>
                    <option v-for="c in clientOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <button type="button" class="rounded-md bg-brand-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-brand-700" @click="openCreate">
                New project
            </button>
        </div>

        <DataTable :columns="columns" :rows="projects.rows" :loading="projects.loading" empty-title="No projects match" empty-description="Change the filters or create a project.">
            <template #cell-code="{ row }">
                <RouterLink :to="{ name: 'project-show', params: { id: row.id } }" class="font-mono text-xs text-brand-700 hover:underline">{{ row.code }}</RouterLink>
            </template>
            <template #cell-name="{ row }">
                <span class="font-medium text-slate-900">{{ row.name }}</span>
            </template>
            <template #cell-client="{ row }">{{ row.client?.name }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="row.status.value" :label="row.status.label" /></template>
            <template #cell-priority="{ row }">{{ row.priority.label }}</template>
            <template #cell-progress="{ row }">
                <div class="flex items-center gap-2">
                    <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full bg-brand-600" :style="{ width: row.progress + '%' }" />
                    </div>
                    <span class="text-xs text-slate-600">{{ row.progress }}%</span>
                </div>
            </template>
            <template #cell-actions="{ row }">
                <button type="button" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="openEdit(row)">Edit</button>
            </template>
        </DataTable>

        <p class="text-xs text-slate-500">
            Sort:
            <button v-for="key in ['start_date', 'end_date', 'name', 'progress']" :key="key" type="button" class="mx-1 underline" @click="sortBy(key)">
                {{ key.replace('_', ' ') }}<span v-if="filters.sort === key">{{ filters.dir === 'asc' ? ' ↑' : ' ↓' }}</span>
            </button>
        </p>

        <Pagination :meta="projects.meta" @page="(p) => ((page = p), load())" />

        <ProjectFormModal :open="form.open" :project-id="form.projectId" @close="form.open = false" @saved="onSaved" />
    </div>
</template>
