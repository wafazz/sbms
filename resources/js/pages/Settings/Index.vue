<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useSettingsStore } from '../../stores/settings.js';
import { useToastStore } from '../../stores/toast.js';
import { useAuthStore } from '../../stores/auth.js';
import { errorMessage, fieldErrors } from '../../api/errors.js';
import FormInput from '../../components/FormInput.vue';
import Modal from '../../components/Modal.vue';

const settings = useSettingsStore();
const toast = useToastStore();
const auth = useAuthStore();

const loading = ref(true);
const formOpen = ref(false);
const submitting = ref(false);
const errors = ref({});
const logoPreview = ref(null);
const logoFile = ref(null);

const form = reactive({
    name: '',
    address: '',
    tax_number: '',
    default_tax_rate: 0,
    bank_name: '',
    bank_account: '',
    bank_swift: '',
});

const fields = [
    ['name', 'Company name'],
    ['address', 'Address'],
    ['tax_number', 'Tax number'],
    ['default_tax_rate', 'Default tax rate (%)'],
    ['bank_name', 'Bank'],
    ['bank_account', 'Account number'],
    ['bank_swift', 'SWIFT / BIC'],
];

async function load() {
    try {
        await settings.fetch();
        logoPreview.value = settings.company?.logo_url ?? null;
    } catch {
        toast.push(errorMessage(null, 'Could not load company settings.'), 'error');
    } finally {
        loading.value = false;
    }
}

function openEdit() {
    errors.value = {};
    logoFile.value = null;
    const company = settings.company ?? {};
    Object.keys(form).forEach((k) => (form[k] = company[k] ?? (k === 'default_tax_rate' ? 0 : '')));
    logoPreview.value = company.logo_url ?? null;
    formOpen.value = true;
}

function onLogo(event) {
    const file = event.target.files?.[0];
    logoFile.value = file ?? null;
    logoPreview.value = file ? URL.createObjectURL(file) : settings.company?.logo_url ?? null;
}

async function save() {
    submitting.value = true;
    errors.value = {};
    try {
        const body = new FormData();
        Object.entries(form).forEach(([key, value]) => body.append(key, value ?? ''));
        if (logoFile.value) body.append('logo', logoFile.value);

        await settings.save(body);
        toast.push('Company details saved. They appear on new PDFs.');
        formOpen.value = false;
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

onMounted(load);
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Company settings</h2>
                <p class="mt-1 text-sm text-slate-600">These details and the logo appear on every invoice PDF.</p>
            </div>
            <button
                v-if="auth.isAdmin"
                type="button"
                class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                :disabled="loading"
                @click="openEdit"
            >
                Edit details
            </button>
        </div>

        <p v-if="!auth.isAdmin" class="rounded-md bg-slate-100 px-4 py-3 text-sm text-slate-700">Only administrators can change these settings.</p>

        <div v-if="loading" class="h-64 animate-pulse rounded-lg bg-slate-200" aria-busy="true" />

        <section v-else-if="settings.company" class="space-y-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-40 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50">
                    <img v-if="settings.company.logo_url" :src="settings.company.logo_url" alt="Company logo" class="max-h-14 max-w-full object-contain" />
                    <span v-else class="text-xs text-slate-400">No logo</span>
                </div>
                <p class="text-lg font-semibold text-slate-900">{{ settings.company.name }}</p>
            </div>

            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div v-for="[key, label] in fields" :key="key" :class="key === 'address' ? 'sm:col-span-2' : ''">
                    <dt class="text-slate-500">{{ label }}</dt>
                    <dd class="mt-0.5 whitespace-pre-line font-medium text-slate-900">{{ settings.company[key] || '—' }}</dd>
                </div>
            </dl>
        </section>

        <Modal
            :open="formOpen"
            title="Company details"
            description="Shown on every invoice PDF. Bank details appear in the payment section."
            size="lg"
            :busy="submitting"
            @close="formOpen = false"
        >
            <form id="settings-form" class="space-y-5" novalidate @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <FormInput id="s-name" v-model="form.name" label="Company name" :error="errors.name" required />
                    </div>
                    <div class="sm:col-span-2">
                        <FormInput id="s-address" v-model="form.address" label="Address" :error="errors.address" />
                    </div>
                    <FormInput id="s-tax-no" v-model="form.tax_number" label="Tax number" :error="errors.tax_number" />
                    <FormInput id="s-tax-rate" v-model="form.default_tax_rate" label="Default tax rate (%)" type="number" :error="errors.default_tax_rate" required />
                    <FormInput id="s-bank" v-model="form.bank_name" label="Bank name" :error="errors.bank_name" />
                    <FormInput id="s-account" v-model="form.bank_account" label="Account number" :error="errors.bank_account" />
                    <FormInput id="s-swift" v-model="form.bank_swift" label="SWIFT / BIC" :error="errors.bank_swift" />
                </div>

                <div class="border-t border-slate-100 pt-5">
                    <label for="s-logo" class="block text-sm font-medium text-slate-700">Logo (PNG or JPG, up to 2 MB)</label>
                    <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div class="flex h-16 w-40 shrink-0 items-center justify-center overflow-hidden rounded-md border border-dashed border-slate-300 bg-slate-50">
                            <img v-if="logoPreview" :src="logoPreview" alt="Logo preview" class="max-h-14 max-w-full object-contain" />
                            <span v-else class="text-xs text-slate-400">No logo</span>
                        </div>
                        <input id="s-logo" type="file" accept="image/png,image/jpeg" class="text-sm" :aria-invalid="!!errors.logo" @change="onLogo" />
                    </div>
                    <p v-if="errors.logo" class="mt-2 text-sm text-rose-600">{{ errors.logo }}</p>
                </div>
            </form>

            <template #footer>
                <button type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="submitting" @click="formOpen = false">Cancel</button>
                <button type="submit" form="settings-form" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60" :disabled="submitting">
                    {{ submitting ? 'Saving…' : 'Save settings' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
