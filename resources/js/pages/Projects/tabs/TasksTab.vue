<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useTimelineStore } from '../../../stores/timeline.js';
import { useToastStore } from '../../../stores/toast.js';
import { errorMessage, fieldErrors } from '../../../api/errors.js';
import DataTable from '../../../components/DataTable.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import FormInput from '../../../components/FormInput.vue';
import ConfirmModal from '../../../components/ConfirmModal.vue';
import Modal from '../../../components/Modal.vue';

const props = defineProps({
    project: { type: Object, required: true },
    activities: { type: Array, default: () => [] },
});

const timeline = useTimelineStore();
const toast = useToastStore();

const formOpen = ref(false);
const editingId = ref(null);
const submitting = ref(false);
const errors = ref({});
const form = reactive({
    title: '',
    description: '',
    start_date: '',
    end_date: '',
    status: 'todo',
    progress: 0,
    assigned_to: '',
    parent_id: '',
});
const confirm = reactive({ open: false, task: null, busy: false });

const statusOptions = [
    { value: 'todo', label: 'To do' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'done', label: 'Done' },
];

const columns = [
    { key: 'title', label: 'Task' },
    { key: 'dates', label: 'Dates' },
    { key: 'status', label: 'Status' },
    { key: 'progress', label: 'Progress' },
    { key: 'assignee', label: 'Assignee' },
    { key: 'actions', label: '', class: 'text-right' },
];

const assigneeOptions = computed(() => [
    { value: '', label: 'Unassigned' },
    ...timeline.team.map((m) => ({ value: m.id, label: m.name })),
]);

const parentOptions = computed(() => [
    { value: '', label: 'None (top level)' },
    ...timeline.tasks
        .filter((t) => t.id !== editingId.value)
        .map((t) => ({ value: t.id, label: t.title })),
]);

const rows = computed(() => timeline.tasks.map((t) => ({ ...t, dates: `${t.start_date} → ${t.end_date}` })));

function openCreate() {
    editingId.value = null;
    errors.value = {};
    Object.assign(form, {
        title: '',
        description: '',
        start_date: props.project.start_date,
        end_date: props.project.start_date,
        status: 'todo',
        progress: 0,
        assigned_to: '',
        parent_id: '',
    });
    formOpen.value = true;
}

function openEdit(task) {
    editingId.value = task.id;
    errors.value = {};
    Object.assign(form, {
        title: task.title,
        description: task.description ?? '',
        start_date: task.start_date,
        end_date: task.end_date,
        status: task.status.value,
        progress: task.progress,
        assigned_to: task.assigned_to?.id ?? '',
        parent_id: task.parent_id ?? '',
    });
    formOpen.value = true;
}

function payload() {
    return {
        ...form,
        progress: Number(form.progress),
        assigned_to: form.assigned_to === '' ? null : Number(form.assigned_to),
        parent_id: form.parent_id === '' ? null : Number(form.parent_id),
    };
}

async function save() {
    submitting.value = true;
    errors.value = {};
    try {
        if (editingId.value) {
            await timeline.updateTask(props.project.id, editingId.value, payload());
            toast.push('Task updated.');
        } else {
            await timeline.createTask(props.project.id, payload());
            toast.push('Task added.');
        }
        formOpen.value = false;
        await timeline.load(props.project.id);
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = fieldErrors(error);
        } else {
            toast.push(errorMessage(error), 'error');
        }
    } finally {
        submitting.value = false;
    }
}

async function remove() {
    confirm.busy = true;
    try {
        await timeline.deleteTask(props.project.id, confirm.task.id);
        toast.push('Task deleted.');
        confirm.open = false;
        await timeline.load(props.project.id);
    } catch (error) {
        toast.push(errorMessage(error), 'error');
        confirm.open = false;
    } finally {
        confirm.busy = false;
    }
}

onMounted(async () => {
    try {
        await Promise.all([timeline.load(props.project.id), timeline.loadTeam()]);
    } catch {
        /* interceptor toast */
    }
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-slate-600">{{ timeline.tasks.length }} task(s). Project progress is the average of task progress unless set by hand.</p>
            <button type="button" class="shrink-0 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700" @click="openCreate">Add task</button>
        </div>

        <DataTable :columns="columns" :rows="rows" :loading="timeline.loading" empty-title="No tasks yet" empty-description="Add tasks to plan the work and track progress.">
            <template #cell-title="{ row }">
                <span :class="row.parent_id ? 'pl-4 text-slate-600' : 'font-medium text-slate-900'">{{ row.title }}</span>
            </template>
            <template #cell-status="{ row }">
                <div class="flex items-center gap-2">
                    <StatusBadge :status="row.status.value" :label="row.status.label" />
                    <span v-if="row.overdue" class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Overdue</span>
                </div>
            </template>
            <template #cell-progress="{ row }">{{ row.progress }}%</template>
            <template #cell-assignee="{ row }">{{ row.assigned_to?.name ?? '—' }}</template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="openEdit(row)">Edit</button>
                    <button type="button" class="rounded-md border border-rose-200 px-2.5 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50" @click="(confirm.task = row) && (confirm.open = true)">Delete</button>
                </div>
            </template>
        </DataTable>

        <Modal
            :open="formOpen"
            :title="editingId ? 'Edit task' : 'Add task'"
            :description="`Tasks must fall within ${project.start_date} to ${project.end_date}.`"
            size="lg"
            :busy="submitting"
            @close="formOpen = false"
        >
            <form id="task-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
                <div class="sm:col-span-2">
                    <FormInput id="t-title" v-model="form.title" label="Title" :error="errors.title" required />
                </div>
                <div class="sm:col-span-2">
                    <FormInput id="t-desc" v-model="form.description" label="Description" :error="errors.description" />
                </div>
                <FormInput id="t-start" v-model="form.start_date" label="Start" type="date" :error="errors.start_date" required />
                <FormInput id="t-end" v-model="form.end_date" label="End" type="date" :error="errors.end_date" required />
                <FormInput id="t-status" v-model="form.status" label="Status" :options="statusOptions" :error="errors.status" />
                <FormInput id="t-progress" v-model="form.progress" label="Progress (%)" type="number" :error="errors.progress" />
                <FormInput id="t-assignee" v-model="form.assigned_to" label="Assignee" :options="assigneeOptions" :error="errors.assigned_to" />
                <FormInput id="t-parent" v-model="form.parent_id" label="Parent task" :options="parentOptions" :error="errors.parent_id" />
            </form>

            <template #footer>
                <button type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="submitting" @click="formOpen = false">Cancel</button>
                <button type="submit" form="task-form" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60" :disabled="submitting">
                    {{ submitting ? 'Saving…' : editingId ? 'Save changes' : 'Add task' }}
                </button>
            </template>
        </Modal>

        <ConfirmModal
            :open="confirm.open"
            title="Delete task?"
            :message="`${confirm.task?.title ?? ''} and its sub-tasks will be removed.`"
            confirm-label="Delete"
            danger
            :busy="confirm.busy"
            @confirm="remove"
            @cancel="confirm.open = false"
        />
    </div>
</template>
