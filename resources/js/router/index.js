import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { resolveAccess } from './guards.js';
import AppLayout from '../layouts/AppLayout.vue';

// Add and edit forms open in dialogs on their list or detail pages, so there are no form routes.
const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../pages/Login.vue'),
        meta: { guestOnly: true, title: 'Sign in' },
    },
    {
        path: '/',
        component: AppLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('../pages/Dashboard.vue'),
                meta: { title: 'Dashboard' },
            },
            {
                path: 'projects',
                name: 'projects',
                component: () => import('../pages/Projects/Index.vue'),
                meta: { title: 'Projects' },
            },
            {
                path: 'projects/:id(\\d+)',
                name: 'project-show',
                component: () => import('../pages/Projects/Show.vue'),
                meta: { title: 'Project' },
            },
            {
                path: 'clients',
                name: 'clients',
                component: () => import('../pages/Clients/Index.vue'),
                meta: { title: 'Clients' },
            },
            {
                path: 'invoices',
                name: 'invoices',
                component: () => import('../pages/Invoices/Index.vue'),
                meta: { title: 'Invoices' },
            },
            {
                path: 'invoices/:id(\\d+)',
                name: 'invoice-show',
                component: () => import('../pages/Invoices/Show.vue'),
                meta: { title: 'Invoice' },
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('../pages/Settings/Index.vue'),
                meta: { title: 'Settings' },
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('../pages/Users/Index.vue'),
                meta: { title: 'Users', roles: ['admin'] },
            },
            {
                path: 'forbidden',
                name: 'forbidden',
                component: () => import('../pages/Shared/Forbidden.vue'),
                meta: { title: 'Access denied' },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../pages/Shared/NotFound.vue'),
        meta: { title: 'Page not found' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialised) {
        await auth.fetchMe();
    }

    const redirect = resolveAccess(to.meta, {
        isAuthenticated: auth.isAuthenticated,
        role: auth.user?.role ?? null,
    });

    return redirect ?? true;
});

router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} · BMS` : 'BMS';
});

export default router;
