<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useInvoicesStore } from '../../stores/invoices.js';
import { useClientsStore } from '../../stores/clients.js';
import { useProjectsStore } from '../../stores/projects.js';
import { useToastStore } from '../../stores/toast.js';
import { errorMessage, fieldErrors } from '../../api/errors.js';
import { calculate } from '../../support/money.js';
import Modal from '../../components/Modal.vue';
import FormInput from '../../components/FormInput.vue';

/**
 * Create or edit a draft invoice in a dialog. Totals are a live preview; the server recalculates on save.
 * projectId: pre-selects a project (create from a project). invoiceId: edit an existing draft.
 */
const props = defineProps({
    open: { type: Boolean, required: true },
    invoiceId: { type: Number, default: null },
    projectId: { type: Number, default: null },
});
const emit = defineEmits(['close', 'saved']);

const invoices = useInvoicesStore();
const clients = useClientsStore();
const projects = useProjectsStore();
const toast = useToastStore();

const isEdit = computed(() => Boolean(props.invoiceId));
const loading = ref(false);
const submitting = ref(false);
const errors = ref({});
const locked = ref(false);
const invoiceStatus = ref('');
const clientOptions = ref([]);
const projectOptions = ref([]);

const blankItem = () => ({ description: '', quantity: 1, unit_price: 0 });

const form = reactive({
    client_id: '',
    project_id: '',
    issue_date: '',
    due_date: '',
    tax_rate: 0,
    discount: 0,
    notes: '',
    terms: '',
    items: [blankItem()],
});

const preview = computed(() =>
    calculate(form.items.map((i) => ({ quantity: i.quantity, unit_price: i.unit_price })), form.discount, form.tax_rate),
);
const fmt = (v) => Number(v).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) return;
        errors.value = {};
        locked.value = false;
        loading.value = true;

        try {
            if (!clientOptions.value.length) {
                clientOptions.value = (await clients.options()).map((c) => ({ value: c.id, label: c.name }));
            }
            if (!projectOptions.value.length) {
                await projects.fetch({ per_page: 100 });
                projectOptions.value = [
                    { value: '', label: 'No project' },
                    ...projects.rows.map((p) => ({ value: p.id, label: `${p.code} · ${p.name}` })),
                ];
            }

            if (isEdit.value) {
                const inv = await invoices.show(props.invoiceId);
                locked.value = inv.status.value !== 'draft';
                invoiceStatus.value = inv.status.label.toLowerCase();
                Object.assign(form, {
                    client_id: inv.client?.id ?? '',
                    project_id: inv.project?.id ?? '',
                    issue_date: inv.issue_date,
                    due_date: inv.due_date,
                    tax_rate: Number(inv.tax_rate),
                    discount: Number(inv.discount),
                    notes: inv.notes ?? '',
                    terms: inv.terms ?? '',
                    items: inv.items.map((i) => ({ description: i.description, quantity: Number(i.quantity), unit_price: Number(i.unit_price) })),
                });
            } else {
                const project = props.projectId ? projects.rows.find((p) => p.id === props.projectId) : null;
                Object.assign(form, {
                    client_id: project?.client?.id ?? '',
                    project_id: props.projectId ?? '',
                    issue_date: new Date().toISOString().slice(0, 10),
                    due_date: '',
                    tax_rate: 0,
                    discount: 0,
                    notes: '',
                    terms: '',
                    items: [blankItem()],
                });
            }
        } catch {
            toast.push(errorMessage(null, 'Could not load the invoice form.'), 'error');
            emit('close');
        } finally {
            loading.value = false;
        }
    },
);

function addItem() {
    form.items.push(blankItem());
}

function removeItem(index) {
    if (form.items.length > 1) form.items.splice(index, 1);
}

function payload() {
    return {
        client_id: form.client_id === '' ? null : Number(form.client_id),
        project_id: form.project_id === '' ? null : Number(form.project_id),
        issue_date: form.issue_date,
        due_date: form.due_date,
        tax_rate: Number(form.tax_rate),
        discount: Number(form.discount),
        notes: form.notes || null,
        terms: form.terms || null,
        items: form.items.map((i) => ({ description: i.description, quantity: Number(i.quantity), unit_price: Number(i.unit_price) })),
    };
}

async function save() {
    if (locked.value) {
        toast.push(`This invoice is ${invoiceStatus.value} and locked. Change the notes from the invoice page.`, 'error');
        return;
    }

    submitting.value = true;
    errors.value = {};
    try {
        let result;
        if (isEdit.value) {
            result = await invoices.update(props.invoiceId, payload());
            toast.push('Invoice saved.');
        } else if (props.projectId) {
            result = await invoices.createForProject(props.projectId, payload());
            toast.push(`Invoice ${result.invoice_number} created as a draft.`);
        } else {
            result = await invoices.create(payload());
            toast.push(`Invoice ${result.invoice_number} created as a draft.`);
        }
        emit('saved', result);
        emit('close');
    } catch (error) {
        if (error.response?.status === 422) {
            errors.value = fieldErrors(error);
            const itemKey = Object.keys(error.response.data.errors ?? {}).find((k) => k.startsWith('items.'));
            if (itemKey) errors.value.items = error.response.data.errors[itemKey][0];
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
        :title="isEdit ? 'Edit draft invoice' : 'New invoice'"
        description="Totals below are a preview. The server recalculates them when you save."
        size="xl"
        :busy="submitting"
        @close="emit('close')"
    >
        <p v-if="locked" role="alert" class="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">
            This invoice is {{ invoiceStatus }} and locked. Change the notes from the invoice page.
        </p>

        <div v-if="loading" class="space-y-4" aria-busy="true">
            <div class="h-10 animate-pulse rounded bg-slate-200" />
            <div class="h-40 animate-pulse rounded bg-slate-200" />
        </div>

        <form v-else id="invoice-form" class="space-y-6" novalidate @submit.prevent="save">
            <section class="grid gap-4 sm:grid-cols-2">
                <FormInput id="i-client" v-model="form.client_id" label="Client" :options="[{ value: '', label: 'Select a client' }, ...clientOptions]" :error="errors.client_id" required />
                <FormInput id="i-project" v-model="form.project_id" label="Project (optional)" :options="projectOptions" :error="errors.project_id" />
                <FormInput id="i-issue" v-model="form.issue_date" label="Issue date" type="date" :error="errors.issue_date" required />
                <FormInput id="i-due" v-model="form.due_date" label="Due date" type="date" :error="errors.due_date" required />
            </section>

            <section class="rounded-lg border border-slate-200 p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900">Line items</h3>
                    <button type="button" class="rounded-md px-2 py-1 text-sm font-medium text-brand-700 hover:bg-brand-50" :disabled="locked" @click="addItem">+ Add line</button>
                </div>
                <p v-if="errors.items" role="alert" class="mt-2 text-sm text-rose-600">{{ errors.items }}</p>

                <!-- Mobile: stacked cards. Desktop: one row per line. -->
                <ul class="mt-3 space-y-3">
                    <li v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 items-end gap-2 rounded-md bg-slate-50 p-3 sm:bg-transparent sm:p-0">
                        <div class="col-span-12 sm:col-span-6">
                            <label :for="`item-desc-${index}`" class="block text-xs font-medium text-slate-600">Description</label>
                            <input :id="`item-desc-${index}`" v-model="item.description" type="text" :disabled="locked" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100" />
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <label :for="`item-qty-${index}`" class="block text-xs font-medium text-slate-600">Qty</label>
                            <input :id="`item-qty-${index}`" v-model.number="item.quantity" type="number" step="0.01" min="0" :disabled="locked" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm disabled:bg-slate-100" />
                        </div>
                        <div class="col-span-5 sm:col-span-3">
                            <label :for="`item-price-${index}`" class="block text-xs font-medium text-slate-600">Unit price</label>
                            <input :id="`item-price-${index}`" v-model.number="item.unit_price" type="number" step="0.01" min="0" :disabled="locked" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-right text-sm disabled:bg-slate-100" />
                        </div>
                        <div class="col-span-3 flex items-center justify-between sm:col-span-1 sm:flex-col sm:items-end">
                            <span class="text-sm tabular-nums text-slate-700">{{ preview.lines[index] ?? '0.00' }}</span>
                            <button type="button" class="text-xs text-rose-600 hover:underline disabled:opacity-40" :disabled="form.items.length === 1 || locked" @click="removeItem(index)">Remove</button>
                        </div>
                    </li>
                </ul>
            </section>

            <section class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-4">
                    <FormInput id="i-tax" v-model="form.tax_rate" label="Tax rate (%)" type="number" :error="errors.tax_rate" />
                    <FormInput id="i-discount" v-model="form.discount" label="Discount (MYR, before tax)" type="number" :error="errors.discount" />
                    <div>
                        <label for="i-notes" class="block text-sm font-medium text-slate-700">Notes</label>
                        <textarea id="i-notes" v-model="form.notes" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label for="i-terms" class="block text-sm font-medium text-slate-700">Terms</label>
                        <textarea id="i-terms" v-model="form.terms" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="rounded-lg bg-slate-50 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Summary (preview)</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular-nums">MYR {{ fmt(preview.subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="tabular-nums">−MYR {{ fmt(preview.discount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Tax ({{ form.tax_rate || 0 }}%)</dt><dd class="tabular-nums">MYR {{ fmt(preview.tax_amount) }}</dd></div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total</dt><dd class="tabular-nums">MYR {{ fmt(preview.total) }}</dd></div>
                    </dl>
                </div>
            </section>
        </form>

        <template #footer>
            <button type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="submitting" @click="emit('close')">Cancel</button>
            <button type="submit" form="invoice-form" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60" :disabled="submitting || loading || locked">
                {{ submitting ? 'Saving…' : isEdit ? 'Save draft' : 'Create draft' }}
            </button>
        </template>
    </Modal>
</template>
