<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { Chart as ChartJS, ArcElement, BarElement, CategoryScale, Legend, LinearScale, Tooltip, Title, Filler, registerables } from 'chart.js';
import { Bar, Doughnut } from 'vue-chartjs';
import { http } from '../api/http.js';
import { errorMessage } from '../api/errors.js';
import { useToastStore } from '../stores/toast.js';
import StatusBadge from '../components/StatusBadge.vue';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Legend, Tooltip, Title, Filler, ...registerables);

const toast = useToastStore();
const loading = ref(true);
const failed = ref(false);
const data = ref(null);

const money = (v) => Number(v ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const cards = computed(() => {
    const c = data.value?.cards;
    if (!c) return [];
    return [
        { label: 'Active projects', value: c.active_projects },
        { label: 'Overdue projects', value: c.overdue_projects, tone: c.overdue_projects > 0 ? 'rose' : null },
        { label: 'Invoiced this month', value: `MYR ${money(c.invoiced_this_month)}` },
        { label: 'Paid this month', value: `MYR ${money(c.paid_this_month)}` },
        { label: 'Outstanding balance', value: `MYR ${money(c.outstanding_balance)}`, hint: 'Sent, partially paid and overdue' },
        { label: 'Overdue invoices', value: c.overdue_invoices, tone: c.overdue_invoices > 0 ? 'rose' : null },
    ];
});

const revenueChart = computed(() => ({
    labels: data.value?.revenue.labels ?? [],
    datasets: [
        { label: 'Invoiced', data: (data.value?.revenue.invoiced ?? []).map(Number), backgroundColor: '#c4b5fd', borderRadius: 4 },
        { label: 'Paid', data: (data.value?.revenue.paid ?? []).map(Number), backgroundColor: '#4f46e5', borderRadius: 4 },
    ],
}));

const revenueOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { callback: (v) => Number(v).toLocaleString('en-MY') } } },
};

const statusChart = computed(() => {
    const rows = data.value?.projects_by_status ?? [];
    return {
        labels: rows.map((r) => r.label),
        datasets: [{
            data: rows.map((r) => r.count),
            backgroundColor: ['#f59e0b', '#10b981', '#fb923c', '#64748b', '#f43f5e'],
            borderWidth: 0,
        }],
    };
});

const statusOptions = { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' } } };

const hasStatusData = computed(() => (data.value?.projects_by_status ?? []).some((r) => r.count > 0));

async function load() {
    loading.value = true;
    failed.value = false;
    try {
        const { data: body } = await http.get('/dashboard');
        data.value = body.data;
    } catch (error) {
        failed.value = true;
        toast.push(errorMessage(error, 'Could not load the dashboard.'), 'error');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-500">As of {{ data?.as_of ?? '…' }}. Refreshes every minute.</p>
            <button type="button" class="text-sm font-medium text-brand-700 hover:underline" @click="load">Refresh</button>
        </div>

        <div v-if="failed" class="rounded-lg bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
            <p class="text-sm text-slate-700">The dashboard could not be loaded.</p>
            <button type="button" class="mt-3 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white" @click="load">Try again</button>
        </div>

        <template v-else>
            <!-- Summary cards -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <template v-if="loading">
                    <div v-for="n in 6" :key="`sk-${n}`" class="h-24 animate-pulse rounded-lg bg-slate-200" aria-hidden="true" />
                </template>
                <template v-else>
                    <div
                        v-for="card in cards"
                        :key="card.label"
                        class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200"
                    >
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums" :class="card.tone === 'rose' ? 'text-rose-600' : 'text-slate-900'">{{ card.value }}</p>
                        <p v-if="card.hint" class="mt-1 text-xs text-slate-500">{{ card.hint }}</p>
                    </div>
                </template>
            </div>

            <!-- Charts -->
            <div class="grid gap-6 lg:grid-cols-3">
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
                    <h2 class="text-sm font-semibold text-slate-900">Revenue, last 12 months</h2>
                    <div class="mt-4 h-72">
                        <Bar v-if="data" :data="revenueChart" :options="revenueOptions" />
                        <div v-else class="h-full animate-pulse rounded bg-slate-100" />
                    </div>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-sm font-semibold text-slate-900">Projects by status</h2>
                    <div class="mt-4 h-72">
                        <Doughnut v-if="data && hasStatusData" :data="statusChart" :options="statusOptions" />
                        <p v-else-if="data" class="flex h-full items-center justify-center text-sm text-slate-500">No projects yet.</p>
                        <div v-else class="h-full animate-pulse rounded bg-slate-100" />
                    </div>
                </section>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Deadlines -->
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200 lg:col-span-1">
                    <h2 class="text-sm font-semibold text-slate-900">Upcoming deadlines (14 days)</h2>
                    <ul v-if="data?.deadlines.within_14_days.length" class="mt-4 divide-y divide-slate-100">
                        <li v-for="(item, i) in data.deadlines.within_14_days" :key="`${item.type}-${i}`" class="flex items-start justify-between gap-3 py-2.5 text-sm">
                            <div class="min-w-0">
                                <p class="truncate text-slate-800">
                                    <span class="mr-1 text-xs uppercase" :class="item.type === 'milestone' ? 'text-amber-600' : 'text-brand-700'">{{ item.type }}</span>
                                    {{ item.title }}
                                </p>
                                <RouterLink v-if="item.project_id" :to="{ name: 'project-show', params: { id: item.project_id } }" class="text-xs text-slate-500 hover:underline">{{ item.project_code }}</RouterLink>
                            </div>
                            <span class="shrink-0 text-xs tabular-nums" :class="data.deadlines.within_7_days.some((d) => d.date === item.date && d.title === item.title) ? 'font-semibold text-rose-600' : 'text-slate-500'">{{ item.date }}</span>
                        </li>
                    </ul>
                    <p v-else-if="data" class="mt-4 text-sm text-slate-500">Nothing due in the next 14 days.</p>
                    <div v-else class="mt-4 h-40 animate-pulse rounded bg-slate-100" />
                </section>

                <!-- Recent invoices -->
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">Recent invoices</h2>
                        <RouterLink :to="{ name: 'invoices' }" class="text-xs text-brand-700 hover:underline">View all</RouterLink>
                    </div>
                    <ul v-if="data?.recent_invoices.length" class="mt-4 divide-y divide-slate-100">
                        <li v-for="inv in data.recent_invoices" :key="inv.id" class="py-2.5 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <RouterLink :to="{ name: 'invoice-show', params: { id: inv.id } }" class="font-mono text-xs text-brand-700 hover:underline">{{ inv.invoice_number }}</RouterLink>
                                <StatusBadge :status="inv.status" :label="inv.status_label" />
                            </div>
                            <div class="mt-1 flex justify-between text-xs text-slate-500">
                                <span class="truncate">{{ inv.client }}</span>
                                <span class="tabular-nums">MYR {{ money(inv.total) }}</span>
                            </div>
                        </li>
                    </ul>
                    <p v-else-if="data" class="mt-4 text-sm text-slate-500">No invoices yet.</p>
                    <div v-else class="mt-4 h-40 animate-pulse rounded bg-slate-100" />
                </section>

                <!-- Recent activity -->
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 class="text-sm font-semibold text-slate-900">Recent project activity</h2>
                    <ol v-if="data?.recent_activity.length" class="mt-4 space-y-3">
                        <li v-for="a in data.recent_activity" :key="a.id" class="border-l-2 border-brand-100 pl-3 text-sm">
                            <p class="text-slate-800">{{ a.description }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                <RouterLink v-if="a.project_id" :to="{ name: 'project-show', params: { id: a.project_id } }" class="hover:underline">{{ a.project_code }}</RouterLink>
                                · {{ a.user ?? 'System' }}
                            </p>
                        </li>
                    </ol>
                    <p v-else-if="data" class="mt-4 text-sm text-slate-500">No activity yet.</p>
                    <div v-else class="mt-4 h-40 animate-pulse rounded bg-slate-100" />
                </section>
            </div>
        </template>
    </div>
</template>
