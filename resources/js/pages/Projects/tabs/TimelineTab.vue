<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useTimelineStore } from '../../../stores/timeline.js';
import { useToastStore } from '../../../stores/toast.js';
import { errorMessage, fieldErrors } from '../../../api/errors.js';
import GanttChart from '../../../components/GanttChart.vue';
import ConfirmModal from '../../../components/ConfirmModal.vue';
import Modal from '../../../components/Modal.vue';
import FormInput from '../../../components/FormInput.vue';

const props = defineProps({
    project: { type: Object, required: true },
    activities: { type: Array, default: () => [] },
});

const timeline = useTimelineStore();
const toast = useToastStore();

const viewMode = ref('Week');
const zoom = [
    { mode: 'Day', label: 'Day' },
    { mode: 'Week', label: 'Week' },
    { mode: 'Month', label: 'Month' },
];
const loadError = ref('');

const milestoneModal = reactive({ open: false, id: null, busy: false, errors: {} });
const milestoneForm = reactive({ title: '', due_date: '' });
const confirm = reactive({ open: false, milestone: null, busy: false });

async function load() {
    loadError.value = '';
    try {
        await timeline.load(props.project.id);
    } catch (error) {
        loadError.value = errorMessage(error, 'Could not load the timeline.');
    }
}

async function onSchedule({ id, start_date, end_date }) {
    try {
        await timeline.scheduleTask(props.project.id, id, start_date, end_date);
        toast.push('Task dates saved.');
        await load();
    } catch (error) {
        // The server is the authority. Put the bar back where it was.
        toast.push(errorMessage(error, 'Those dates are not allowed.'), 'error');
        await load();
    }
}

function openMilestone(milestone = null) {
    milestoneModal.id = milestone?.id ?? null;
    milestoneModal.errors = {};
    Object.assign(milestoneForm, {
        title: milestone?.title ?? '',
        due_date: milestone?.due_date ?? props.project.start_date,
    });
    milestoneModal.open = true;
}

async function saveMilestone() {
    milestoneModal.busy = true;
    milestoneModal.errors = {};
    try {
        if (milestoneModal.id) {
            await timeline.updateMilestone(props.project.id, milestoneModal.id, { ...milestoneForm });
            toast.push('Milestone updated.');
        } else {
            await timeline.createMilestone(props.project.id, { ...milestoneForm });
            toast.push('Milestone added.');
        }
        milestoneModal.open = false;
        await load();
    } catch (error) {
        if (error.response?.status === 422) {
            milestoneModal.errors = fieldErrors(error);
        } else {
            toast.push(errorMessage(error), 'error');
        }
    } finally {
        milestoneModal.busy = false;
    }
}

async function toggleMilestone(milestone) {
    try {
        await timeline.updateMilestone(props.project.id, milestone.id, { is_completed: !milestone.is_completed });
        await load();
    } catch (error) {
        toast.push(errorMessage(error), 'error');
    }
}

async function removeMilestone() {
    confirm.busy = true;
    try {
        await timeline.deleteMilestone(props.project.id, confirm.milestone.id);
        toast.push('Milestone deleted.');
        confirm.open = false;
        await load();
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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-600">
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-slate-400" /> To do</span>
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-brand-600" /> In progress</span>
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500" /> Done</span>
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-rose-500" /> Overdue</span>
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rotate-45 bg-amber-500" /> Milestone</span>
            </div>
            <div class="inline-flex rounded-md border border-slate-300 bg-white p-0.5" role="group" aria-label="Zoom">
                <button
                    v-for="z in zoom"
                    :key="z.mode"
                    type="button"
                    class="rounded px-3 py-1 text-xs font-medium"
                    :class="viewMode === z.mode ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                    :aria-pressed="viewMode === z.mode"
                    @click="viewMode = z.mode"
                >
                    {{ z.label }}
                </button>
            </div>
        </div>

        <p v-if="loadError" role="alert" class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ loadError }}</p>

        <div v-if="timeline.loading && !timeline.tasks.length" class="h-64 animate-pulse rounded-lg bg-slate-200" aria-busy="true" />

        <div v-else class="grid gap-6 lg:grid-cols-4">
            <div class="min-w-0 lg:col-span-3">
                <div v-if="!timeline.tasks.length && !timeline.milestones.length" class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                    No tasks or milestones yet. Add tasks in the Tasks tab, and they appear here.
                </div>
                <GanttChart
                    v-else
                    :tasks="timeline.tasks"
                    :milestones="timeline.milestones"
                    :view-mode="viewMode"
                    @schedule="onSchedule"
                />
                <p class="mt-2 text-xs text-slate-500">Drag a bar to move it, or drag its edge to change its length. Dates must stay within {{ project.start_date }} to {{ project.end_date }}.</p>
            </div>

            <aside class="space-y-4 rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">Milestones</h3>
                    <button type="button" class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700" @click="openMilestone()">Add</button>
                </div>

                <ul v-if="timeline.milestones.length" class="space-y-2">
                    <li v-for="m in timeline.milestones" :key="m.id" class="flex items-start gap-2 rounded-md border border-slate-100 p-2">
                        <input
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-slate-300 text-brand-600"
                            :checked="m.is_completed"
                            :aria-label="`Mark ${m.title} complete`"
                            @change="toggleMilestone(m)"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm" :class="m.is_completed ? 'text-slate-400 line-through' : 'text-slate-800'">{{ m.title }}</p>
                            <p class="text-xs text-slate-500">Due {{ m.due_date }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2 text-xs">
                            <button type="button" class="text-brand-700 hover:underline" @click="openMilestone(m)">Edit</button>
                            <button type="button" class="text-rose-600 hover:underline" @click="(confirm.milestone = m) && (confirm.open = true)">Delete</button>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-500">No milestones yet.</p>
            </aside>
        </div>

        <Modal
            :open="milestoneModal.open"
            :title="milestoneModal.id ? 'Edit milestone' : 'Add milestone'"
            description="A milestone is a dated marker on the timeline."
            size="sm"
            :busy="milestoneModal.busy"
            @close="milestoneModal.open = false"
        >
            <form id="milestone-form" class="space-y-4" novalidate @submit.prevent="saveMilestone">
                <FormInput id="ms-title" v-model="milestoneForm.title" label="Title" :error="milestoneModal.errors.title" required />
                <FormInput id="ms-due" v-model="milestoneForm.due_date" label="Due date" type="date" :error="milestoneModal.errors.due_date" required />
            </form>

            <template #footer>
                <button type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="milestoneModal.busy" @click="milestoneModal.open = false">Cancel</button>
                <button type="submit" form="milestone-form" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60" :disabled="milestoneModal.busy">
                    {{ milestoneModal.busy ? 'Saving…' : milestoneModal.id ? 'Save changes' : 'Add milestone' }}
                </button>
            </template>
        </Modal>

        <ConfirmModal
            :open="confirm.open"
            title="Delete milestone?"
            :message="`${confirm.milestone?.title ?? ''} will be removed.`"
            confirm-label="Delete"
            danger
            :busy="confirm.busy"
            @confirm="removeMilestone"
            @cancel="confirm.open = false"
        />
    </div>
</template>
