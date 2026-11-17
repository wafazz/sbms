<script setup>
import { ref } from 'vue';
import { useProjectsStore } from '../../../stores/projects.js';
import { useToastStore } from '../../../stores/toast.js';
import { errorMessage } from '../../../api/errors.js';

const props = defineProps({
    project: { type: Object, required: true },
    activities: { type: Array, default: () => [] },
});
const emit = defineEmits(['changed']);

const projects = useProjectsStore();
const toast = useToastStore();
const busy = ref(false);

async function useAutomaticProgress() {
    busy.value = true;
    try {
        await projects.update(props.project.id, { progress_override: false });
        toast.push('Progress now follows the task average.');
        emit('changed');
    } catch (error) {
        toast.push(errorMessage(error), 'error');
    } finally {
        busy.value = false;
    }
}

function formatWhen(iso) {
    return iso ? new Date(iso).toLocaleString('en-MY', { dateStyle: 'medium', timeStyle: 'short' }) : '';
}
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">Progress</h3>
                    <span v-if="project.progress_override" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Manual</span>
                    <span v-else class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Automatic</span>
                </div>
                <div class="mt-4 flex items-center gap-4">
                    <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-brand-600 transition-all" :style="{ width: project.progress + '%' }" />
                    </div>
                    <span class="w-12 text-right text-lg font-semibold text-slate-900">{{ project.progress }}%</span>
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    <span v-if="project.progress_override">Set by hand. Task progress is not used.</span>
                    <span v-else>Average of {{ project.tasks_count }} task(s).</span>
                </p>
                <button
                    v-if="project.progress_override"
                    type="button"
                    class="mt-3 text-sm font-medium text-brand-700 hover:underline disabled:opacity-60"
                    :disabled="busy"
                    @click="useAutomaticProgress"
                >
                    Use automatic progress
                </button>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h3 class="text-sm font-semibold text-slate-900">Details</h3>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">Client</dt>
                        <dd class="font-medium text-slate-900">{{ project.client?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Budget</dt>
                        <dd class="font-medium text-slate-900">RM {{ Number(project.budget ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2 }) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Start</dt>
                        <dd class="font-medium text-slate-900">{{ project.start_date }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">End</dt>
                        <dd class="font-medium text-slate-900">{{ project.end_date }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500">Description</dt>
                        <dd class="whitespace-pre-line text-slate-800">{{ project.description || '—' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h3 class="text-sm font-semibold text-slate-900">Recent activity</h3>
            <ol v-if="activities.length" class="mt-4 space-y-4">
                <li v-for="item in activities" :key="item.id" class="border-l-2 border-brand-100 pl-4">
                    <p class="text-sm text-slate-800">{{ item.description }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ item.user ?? 'System' }} · {{ formatWhen(item.created_at) }}</p>
                </li>
            </ol>
            <p v-else class="mt-4 text-sm text-slate-500">No activity yet.</p>
        </section>
    </div>
</template>
