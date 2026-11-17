<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter, RouterLink, RouterView } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useToastStore } from '../stores/toast.js';
import { useProjectsStore } from '../stores/projects.js';
import { useInvoicesStore } from '../stores/invoices.js';
import NavIcon from '../components/NavIcon.vue';
import NavSubmenu from '../components/NavSubmenu.vue';

const auth = useAuthStore();
const toast = useToastStore();
const projects = useProjectsStore();
const invoices = useInvoicesStore();
const route = useRoute();
const router = useRouter();

const sidebarOpen = ref(false);
const userMenuOpen = ref(false);
const openMenus = reactive({ projects: false, invoices: false });

const pageTitle = computed(() => route.meta.title ?? '');

// One ordered list drives the sidebar. Projects and Invoices are expandable sections.
const plainItems = {
    dashboard: { name: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
    clients: { name: 'clients', label: 'Clients', icon: 'clients' },
    settings: { name: 'settings', label: 'Settings', icon: 'settings' },
    users: { name: 'users', label: 'Users', icon: 'users' },
};

const navOrder = computed(() => [
    { kind: 'plain', key: 'dashboard' },
    { kind: 'section', key: 'projects' },
    { kind: 'plain', key: 'clients' },
    { kind: 'section', key: 'invoices' },
    { kind: 'plain', key: 'settings' },
    ...(auth.isAdmin ? [{ kind: 'plain', key: 'users' }] : []),
]);

const sections = computed(() => ({
    projects: {
        label: 'Projects',
        icon: 'projects',
        routeName: 'projects',
        store: projects,
        active: route.name?.toString().startsWith('project') ?? false,
    },
    invoices: {
        label: 'Invoices',
        icon: 'invoices',
        routeName: 'invoices',
        store: invoices,
        active: route.name?.toString().startsWith('invoice') ?? false,
    },
}));

const listStatusFilter = computed(() => (typeof route.query.status === 'string' ? route.query.status : null));

// A section opens by itself while the user is inside it.
watch(() => sections.value.projects.active, (on) => { if (on) openMenus.projects = true; }, { immediate: true });
watch(() => sections.value.invoices.active, (on) => { if (on) openMenus.invoices = true; }, { immediate: true });

// Refresh every badge on navigation, so the numbers stay close to the truth.
watch(() => route.fullPath, () => {
    projects.fetchStatusCounts();
    invoices.fetchStatusCounts();
});

onMounted(() => {
    projects.fetchStatusCounts();
    invoices.fetchStatusCounts();
});

function sectionIsOnList(section) {
    return route.name === section.routeName;
}

async function signOut() {
    userMenuOpen.value = false;

    try {
        await auth.logout();
        toast.push('You have been signed out.', 'info');
    } finally {
        router.push({ name: 'login' });
    }
}
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 text-slate-100 transition-transform duration-200 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            aria-label="Main navigation"
        >
            <div class="flex h-16 items-center px-6 text-lg font-semibold tracking-tight">BMS</div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <template v-for="entry in navOrder" :key="entry.key">
                    <RouterLink
                        v-if="entry.kind === 'plain'"
                        :to="{ name: plainItems[entry.key].name }"
                        class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white"
                        active-class="bg-slate-800 text-white"
                        @click="sidebarOpen = false"
                    >
                        <NavIcon :name="plainItems[entry.key].icon" />
                        {{ plainItems[entry.key].label }}
                    </RouterLink>

                    <NavSubmenu
                        v-else
                        :label="sections[entry.key].label"
                        :icon="sections[entry.key].icon"
                        :route-name="sections[entry.key].routeName"
                        :statuses="sections[entry.key].store.statusCounts"
                        :total="sections[entry.key].store.totalCount"
                        :open="openMenus[entry.key]"
                        :section-active="sections[entry.key].active"
                        :active-status="sectionIsOnList(sections[entry.key]) ? listStatusFilter : null"
                        :all-active="sectionIsOnList(sections[entry.key]) && !listStatusFilter"
                        @toggle="openMenus[entry.key] = !openMenus[entry.key]"
                        @navigate="sidebarOpen = false"
                    />
                </template>
            </nav>
        </aside>

        <!-- Main column -->
        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
                        aria-label="Open navigation"
                        :aria-expanded="sidebarOpen"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M3 5h14a1 1 0 000-2H3a1 1 0 000 2zm14 4H3a1 1 0 000 2h14a1 1 0 000-2zm0 6H3a1 1 0 000 2h14a1 1 0 000-2z" />
                        </svg>
                    </button>
                    <h1 class="text-lg font-semibold text-slate-900">{{ pageTitle }}</h1>
                </div>

                <div class="relative">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-100"
                        :aria-expanded="userMenuOpen"
                        aria-haspopup="menu"
                        @click="userMenuOpen = !userMenuOpen"
                    >
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">
                            {{ auth.user?.name?.charAt(0) }}
                        </span>
                        <span class="hidden sm:inline">{{ auth.user?.name }}</span>
                    </button>

                    <div
                        v-if="userMenuOpen"
                        role="menu"
                        class="absolute right-0 mt-2 w-56 rounded-md border border-slate-200 bg-white py-1 shadow-lg"
                    >
                        <div class="border-b border-slate-100 px-4 py-2">
                            <p class="truncate text-sm font-medium text-slate-900">{{ auth.user?.name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ auth.user?.email }}</p>
                        </div>
                        <button
                            type="button"
                            role="menuitem"
                            class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                            @click="signOut"
                        >
                            Sign out
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6">
                <RouterView />
            </main>
        </div>
    </div>
</template>
