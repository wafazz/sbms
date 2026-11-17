<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { useToastStore } from '../stores/toast.js';
import { errorMessage, fieldErrors } from '../api/errors.js';

const auth = useAuthStore();
const toast = useToastStore();
const route = useRoute();
const router = useRouter();

// Served from public/, so bind it at runtime. A static src would be treated as a module import.
const illustration = '/images/login-illustration.jpg';

const form = reactive({ email: '', password: '', remember: false });
const errors = ref({});
const formError = ref('');
const submitting = ref(false);

async function submit() {
    submitting.value = true;
    errors.value = {};
    formError.value = '';

    try {
        await auth.login({ ...form });
        toast.push('Welcome back.');
        const target = typeof route.query.redirect === 'string' ? route.query.redirect : '/';
        router.push(target);
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = fieldErrors(error);
        } else {
            formError.value = errorMessage(error);
        }
    } finally {
        submitting.value = false;
    }
}

const inputClass = (hasError) =>
    `mt-2 block w-full rounded-md border bg-white px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 transition focus:outline-none focus:ring-2 ${
        hasError
            ? 'border-rose-400 focus:ring-rose-200'
            : 'border-slate-300 focus:border-violet-500 focus:ring-violet-200'
    }`;
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-violet-700 px-4 py-10">
        <!-- Background shapes -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-24 left-1/3 h-72 w-72 rotate-12 rounded-3xl bg-violet-500/40" />
            <div class="absolute -bottom-32 -left-20 h-96 w-96 -rotate-12 rounded-3xl bg-fuchsia-500/30" />
            <div class="absolute bottom-0 right-0 h-64 w-2/3 bg-gradient-to-l from-violet-600/60 to-transparent" />
        </div>

        <div class="relative z-10 flex w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">
            <!-- Illustration (desktop only) -->
            <div class="hidden flex-1 items-center justify-center bg-white p-10 lg:flex">
                <img
                    :src="illustration"
                    alt="Person working at a computer"
                    class="w-full max-w-md"
                    width="402"
                    height="362"
                />
            </div>

            <!-- Form card -->
            <div class="flex w-full flex-col justify-center p-8 sm:p-12 lg:w-[420px] lg:shrink-0">
                <h1 class="text-3xl font-bold text-slate-900">Login</h1>
                <p class="mt-2 text-sm text-slate-500">Sign in with the account your administrator created.</p>

                <div v-if="formError" role="alert" class="mt-6 rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ formError }}
                </div>

                <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-800">Email Address</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            placeholder="you@example.com"
                            :class="inputClass(!!errors.email)"
                            :aria-invalid="!!errors.email"
                            :aria-describedby="errors.email ? 'email-error' : null"
                            required
                        />
                        <p v-if="errors.email" id="email-error" class="mt-1 text-sm text-rose-600">{{ errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-800">Password</label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            :class="inputClass(!!errors.password)"
                            :aria-invalid="!!errors.password"
                            :aria-describedby="errors.password ? 'password-error' : null"
                            required
                        />
                        <p v-if="errors.password" id="password-error" class="mt-1 text-sm text-rose-600">{{ errors.password }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500" />
                        Remember me
                    </label>

                    <button
                        type="submit"
                        class="w-full rounded-md bg-violet-600 px-4 py-3 text-sm font-semibold uppercase tracking-wide text-white shadow-md shadow-violet-600/30 transition hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 disabled:opacity-60"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Signing in…' : 'Login' }}
                    </button>
                </form>

                <p class="mt-8 text-center text-xs text-slate-400">No public sign-up. Ask an administrator for access.</p>
            </div>
        </div>
    </div>
</template>
