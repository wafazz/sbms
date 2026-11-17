<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Gantt from 'frappe-gantt';
import '../../css/frappe-gantt.css';

/**
 * Thin wrapper around frappe-gantt.
 * tasks: [{ id, title, start_date, end_date, progress, status, overdue }]
 * milestones: [{ id, title, due_date, is_completed }]
 * Emits "schedule" with { id, start_date, end_date } when a task is dragged or resized.
 */
const props = defineProps({
    tasks: { type: Array, default: () => [] },
    milestones: { type: Array, default: () => [] },
    viewMode: { type: String, default: 'Week' },
});
const emit = defineEmits(['schedule']);

const container = ref(null);
let gantt = null;

// frappe-gantt takes dates as "YYYY-MM-DD" and returns Date objects. Keep the calendar day, not UTC.
function ymd(date) {
    const d = date instanceof Date ? date : new Date(date);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function shiftDay(value, days) {
    const d = new Date(`${value}T00:00:00`);
    d.setDate(d.getDate() + days);
    return ymd(d);
}

// One rule for every bar: the chart gets the day after the last day, and changes come back one day earlier.
const plusOneDay = (value) => shiftDay(value, 1);
const minusOneDay = (value) => shiftDay(value, -1);

function toBars() {
    const taskBars = props.tasks.map((t) => ({
        id: `t-${t.id}`,
        name: t.title,
        start: t.start_date,
        end: plusOneDay(t.end_date),
        progress: t.progress,
        custom_class: t.overdue ? 'overdue' : `st-${t.status.value}`,
    }));

    const milestoneBars = props.milestones.map((m) => ({
        id: `m-${m.id}`,
        name: `◆ ${m.title}`,
        start: m.due_date,
        end: plusOneDay(m.due_date),
        progress: m.is_completed ? 100 : 0,
        custom_class: m.is_completed ? 'milestone milestone-done' : 'milestone',
    }));

    return [...taskBars, ...milestoneBars];
}

function render() {
    if (!container.value) return;

    const bars = toBars();

    if (bars.length === 0) {
        container.value.innerHTML = '';
        gantt = null;
        return;
    }

    if (!gantt) {
        gantt = new Gantt(container.value, bars, {
            view_mode: props.viewMode,
            bar_height: 22,
            padding: 14,
            popup_on: 'hover',
            language: 'en',
            readonly_progress: true,
            on_date_change: (task, start, end) => {
                // Milestones are not draggable.
                if (!String(task.id).startsWith('t-')) return;
                emit('schedule', {
                    id: Number(String(task.id).slice(2)),
                    start_date: ymd(start),
                    end_date: minusOneDay(ymd(end)),
                });
            },
            custom_popup_html: (task) => `<div class="gantt-popup"><strong>${escapeHtml(task.name)}</strong><br>${task.start} → ${task.end}</div>`,
        });
    } else {
        gantt.refresh(bars);
    }
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

function setView(mode) {
    if (gantt) gantt.change_view_mode(mode);
}

defineExpose({ setView, refresh: render });

onMounted(() => nextTick(render));
watch(() => [props.tasks, props.milestones], render, { deep: true });
watch(() => props.viewMode, (mode) => setView(mode));
onBeforeUnmount(() => {
    gantt = null;
});
</script>

<template>
    <div class="gantt-wrap overflow-x-auto rounded-lg bg-white ring-1 ring-slate-200">
        <div ref="container" class="min-w-full" />
    </div>
</template>
