<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useProjectsStore } from '../../stores/projects.js';
import { useClientsStore } from '../../stores/clients.js';
import { useToastStore } from '../../stores/toast.js';
import { errorMessage, fieldErrors } from '../../api/errors.js';
import Modal from '../../components/Modal.vue';
import FormInput from '../../components/FormInput.vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    projectId: { type: Number, default: null }, // null = create
});
const emit = defineEmits(['close', 'saved']);

const projects = useProjectsStore();
const clients = useClientsStore();
const toast = useToastStore();

const isEdit = computed(() => Boolean(props.projectId));
const loading = ref(false);
const submitting = ref(false);
const errors = ref({});
const clientOptions = ref([]);
const originalProgress = ref(null);

const form = reactive({
    client_id: '',
    name: '',
    description: '',
    status: 'planning',
    priority: 'medium',
    start_date: '',
    end_date: '',
    budget: '',
    progress: 0,
});

const statusOptions = [
    { value: 'planning', label: 'Planning' },
    { value: 'active', label: 'Active' },
    { value: 'on_hold', label: 'On hold' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];
const priorityOptions = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
];

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) return;
        errors.value = {};
        loading.value = true;
        try {
            if (!clientOptions.value.length) {
                clientOptions.value = (await clients.options()).map((c) => ({ value: c.id, label: c.name }));
            }

            if (isEdit.value) {
                const project = await projects.show(props.projectId);
                Object.assign(form, {
                    client_id: project.client?.id ?? '',
                    name: project.name,
                    description: project.description ?? '',
                    status: project.status.value,
                    priority: project.priority.value,
                    start_date: project.start_date,
                    end_date: project.end_date,
                    budget: project.budget ?? '',
                    progress: project.progress,
                });
                originalProgress.value = Number(project.progress);
            } else {
                Object.assign(form, {
                    client_id: '',
                    name: '',
                    description: '',
                    status: 'planning',
                    priority: 'medium',
                    start_date: new Date().toISOString().slice(0, 10),
                    end_date: '',
                    budget: '',
                    progress: 0,
                });
                originalProgress.value = null;
            }
        } catch {
            toast.push(errorMessage(null, 'Could not load the project.'), 'error');
            emit('close');
        } finally {
            loading.value = false;
        }
    },
);

function payload() {
    const data = { ...form, client_id: Number(form.client_id), budget: form.budget === '' ? null : form.budget };

    // Send progress only when the user changed it. Otherwise saving other fields would switch the override on.
    if (!isEdit.value || Number(form.progress) === originalProgress.value) {
        delete data.progress;
    }
    return data;
}

async function save() {
    submitting.value = true;
    errors.value = {};
    try {
        let result;
        if (isEdit.value) {
            result = await projects.update(props.projectId, payload());
            toast.push('Project saved.');
        } else {
            result = await projects.create(payload());
            toast.push(`Project ${result.code} created.`);
        }
        emit('saved', result);
        emit('close');
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
</script>

<template>
    <Modal
        :open="open"
        :title="isEdit ? 'Edit project' : 'New project'"
        :description="isEdit ? 'Changing progress here overrides the task average.' : 'Dates must be set. Tasks are added after the project is created.'"
        size="xl"
        :busy="submitting"
        @close="emit('close')"
    >
        <div v-if="loading" class="grid gap-4 sm:grid-cols-2" aria-busy="true">
            <div v-for="n in 6" :key="n" class="h-10 animate-pulse rounded bg-slate-200" />
        </div>

        <form v-else id="project-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
            <div class="sm:col-span-2">
                <FormInput id="p-client" v-model="form.client_id" label="Client" :options="[{ value: '', label: 'Select a client' }, ...clientOptions]" :error="errors.client_id" required />
            </div>
            <div class="sm:col-span-2">
                <FormInput id="p-name" v-model="form.name" label="Project name" :error="errors.name" required />
            </div>
            <div class="sm:col-span-2">
                <FormInput id="p-desc" v-model="form.description" label="Description" :error="errors.description" />
            </div>
            <FormInput id="p-status" v-model="form.status" label="Status" :options="statusOptions" :error="errors.status" required />
            <FormInput id="p-priority" v-model="form.priority" label="Priority" :options="priorityOptions" :error="errors.priority" required />
            <FormInput id="p-start" v-model="form.start_date" label="Start date" type="date" :error="errors.start_date" required />
            <FormInput id="p-end" v-model="form.end_date" label="End date" type="date" :error="errors.end_date" required />
            <FormInput id="p-budget" v-model="form.budget" label="Budget (MYR)" type="number" :error="errors.budget" />
            <FormInput v-if="isEdit" id="p-progress" v-model="form.progress" label="Progress (%)" type="number" :error="errors.progress" />
        </form>

        <template #footer>
            <button type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="submitting" @click="emit('close')">Cancel</button>
            <button type="submit" form="project-form" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60" :disabled="submitting || loading">
                {{ submitting ? 'Saving…' : isEdit ? 'Save changes' : 'Create project' }}
            </button>
        </template>
    </Modal>
</template>
