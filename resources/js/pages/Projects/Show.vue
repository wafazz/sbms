<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useProjectsStore } from '../../stores/projects.js';
import { useAuthStore } from '../../stores/auth.js';
import { useToastStore } from '../../stores/toast.js';
import { errorMessage } from '../../api/errors.js';
import StatusBadge from '../../components/StatusBadge.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import ProjectFormModal from './ProjectFormModal.vue';
import OverviewTab from './tabs/OverviewTab.vue';
import TimelineTab from './tabs/TimelineTab.vue';
import TasksTab from './tabs/TasksTab.vue';
import InvoicesTab from './tabs/InvoicesTab.vue';

const route = useRoute();
const router = useRouter();
const projects = useProjectsStore();
const auth = useAuthStore();
const toast = useToastStore();

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'timeline', label: 'Timeline' },
    { key: 'tasks', label: 'Tasks' },
    { key: 'invoices', label: 'Invoices' },
];
const components = { overview: OverviewTab, timeline: TimelineTab, tasks: TasksTab, invoices: InvoicesTab };

const loading = ref(true);
const notFound = ref(false);
const confirmDelete = ref(false);
const editOpen = ref(false);
const deleting = ref(false);

const activeTab = computed(() => (tabs.some((t) => t.key === route.query.tab) ? route.query.tab : 'overview'));
const project = computed(() => projects.current);

async function load() {
    loading.value = true;
    notFound.value = false;
    try {
        await projects.show(route.params.id);
    } catch (error) {
        notFound.value = error.response?.status === 404;
    } finally {
        loading.value = false;
    }
}

function setTab(key) {
    router.replace({ name: 'project-show', params: { id: route.params.id }, query: { tab: key } });
}

async function removeProject() {
    deleting.value = true;
    try {
        await projects.remove(route.params.id);
        toast.push('Project deleted.');
        router.push({ name: 'projects' });
    } catch (error) {
        toast.push(errorMessage(error), 'error');
    } finally {
        deleting.value = false;
        confirmDelete.value = false;
    }
}

onMounted(load);
watch(() => route.params.id, load);
</script>

<template>
    <div class="space-y-6">
        <div v-if="loading" class="space-y-4" aria-busy="true">
            <div class="h-8 w-1/3 animate-pulse rounded bg-slate-200" />
            <div class="h-40 animate-pulse rounded-lg bg-slate-200" />
        </div>

        <div v-else-if="notFound" class="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">Project not found</h2>
            <RouterLink :to="{ name: 'projects' }" class="mt-3 inline-block text-sm text-brand-600 hover:underline">Back to projects</RouterLink>
        </div>

        <template v-else-if="project">
            <div class="flex flex-col gap-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="font-mono text-xs text-slate-500">{{ project.code }}</p>
                    <h2 class="mt-1 truncate text-2xl font-semibold text-slate-900">{{ project.name }}</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        {{ project.client?.name }}<span v-if="project.client?.company"> · {{ project.client.company }}</span>
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <StatusBadge :status="project.status.value" :label="project.status.label" />
                        <span class="text-xs text-slate-500">Priority: {{ project.priority.label }}</span>
                        <span class="text-xs text-slate-500">{{ project.start_date }} → {{ project.end_date }}</span>
                    </div>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="editOpen = true">
                        Edit
                    </button>
                    <button v-if="auth.isAdmin" type="button" class="rounded-md border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50" @click="confirmDelete = true">
                        Delete
                    </button>
                </div>
            </div>

            <nav class="flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Project sections">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium"
                    :class="activeTab === tab.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                    :aria-current="activeTab === tab.key ? 'page' : null"
                    @click="setTab(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </nav>

            <component :is="components[activeTab]" :project="project" :activities="projects.activities" @changed="load" />
        </template>

        <ProjectFormModal
            :open="editOpen"
            :project-id="project?.id ?? null"
            @close="editOpen = false"
            @saved="load"
        />

        <ConfirmModal
            :open="confirmDelete"
            title="Delete project?"
            message="The project is moved to the trash. Its tasks and milestones are kept with it."
            confirm-label="Delete"
            danger
            :busy="deleting"
            @confirm="removeProject"
            @cancel="confirmDelete = false"
        />
    </div>
</template>
