<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useInvoicesStore } from '../../stores/invoices.js';
import { useAuthStore } from '../../stores/auth.js';
import { useToastStore } from '../../stores/toast.js';
import { errorMessage, fieldErrors } from '../../api/errors.js';
import { http } from '../../api/http.js';
import StatusBadge from '../../components/StatusBadge.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import FormInput from '../../components/FormInput.vue';
import Modal from '../../components/Modal.vue';
import InvoiceFormModal from './InvoiceFormModal.vue';

const route = useRoute();
const router = useRouter();
const invoices = useInvoicesStore();
const auth = useAuthStore();
const toast = useToastStore();

const loading = ref(true);
const notFound = ref(false);
const busy = ref(false);
const confirm = reactive({ open: false, action: null, title: '', message: '', label: '', danger: false });

const editOpen = ref(false);
const notesOpen = ref(false);
const notes = reactive({ notes: '', terms: '' });

const paymentOpen = ref(false);
const payment = reactive({ amount: '', payment_date: new Date().toISOString().slice(0, 10), method: 'bank_transfer', reference: '', notes: '' });
const paymentErrors = ref({});

const methodOptions = [
    { value: 'bank_transfer', label: 'Bank transfer' },
    { value: 'cash', label: 'Cash' },
    { value: 'card', label: 'Card' },
    { value: 'other', label: 'Other' },
];

const invoice = computed(() => invoices.current);
const canPay = computed(() => invoice.value && ['sent', 'partially_paid', 'overdue'].includes(invoice.value.status.value));
const canEdit = computed(() => invoice.value?.status.value === 'draft');
const canCancel = computed(() => invoice.value && ['draft', 'sent', 'partially_paid', 'overdue'].includes(invoice.value.status.value) && Number(invoice.value.amount_paid) === 0);
const canDelete = computed(() => auth.isAdmin && invoice.value?.status.value === 'draft');

const fmt = (v) => Number(v ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

async function load() {
    loading.value = true;
    notFound.value = false;
    try {
        await invoices.show(route.params.id);
    } catch (error) {
        notFound.value = error.response?.status === 404;
    } finally {
        loading.value = false;
    }
}

function ask(action, title, message, label, danger = false) {
    Object.assign(confirm, { open: true, action, title, message, label, danger });
}

async function runConfirmed() {
    busy.value = true;
    try {
        if (confirm.action === 'send') {
            await invoices.send(route.params.id);
            toast.push('Invoice sent. It is now locked, except notes and terms.');
        } else if (confirm.action === 'cancel') {
            await invoices.cancel(route.params.id);
            toast.push('Invoice cancelled.');
        } else if (confirm.action === 'delete') {
            await invoices.remove(route.params.id);
            toast.push('Draft deleted.');
            router.push({ name: 'invoices' });
            return;
        }
        confirm.open = false;
    } catch (error) {
        toast.push(errorMessage(error), 'error');
        confirm.open = false;
    } finally {
        busy.value = false;
    }
}

function openNotes() {
    Object.assign(notes, { notes: invoice.value?.notes ?? '', terms: invoice.value?.terms ?? '' });
    notesOpen.value = true;
}

async function saveNotes() {
    busy.value = true;
    try {
        await invoices.update(route.params.id, { notes: notes.notes || null, terms: notes.terms || null });
        toast.push('Notes saved.');
        notesOpen.value = false;
    } catch (error) {
        toast.push(errorMessage(error), 'error');
    } finally {
        busy.value = false;
    }
}

function openPayment() {
    paymentErrors.value = {};
    Object.assign(payment, { amount: '', payment_date: new Date().toISOString().slice(0, 10), method: 'bank_transfer', reference: '', notes: '' });
    paymentOpen.value = true;
}

async function recordPayment() {
    busy.value = true;
    paymentErrors.value = {};
    try {
        await invoices.recordPayment(route.params.id, {
            amount: Number(payment.amount),
            payment_date: payment.payment_date,
            method: payment.method,
            reference: payment.reference || null,
            notes: payment.notes || null,
        });
        toast.push('Payment recorded.');
        paymentOpen.value = false;
    } catch (error) {
        if (error.response?.status === 422) {
            paymentErrors.value = fieldErrors(error);
        } else {
            toast.push(errorMessage(error), 'error');
        }
    } finally {
        busy.value = false;
    }
}

async function downloadPdf() {
    try {
        const { data } = await http.get(`/invoices/${route.params.id}/pdf`, { responseType: 'blob' });
        const url = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (error) {
        toast.push(errorMessage(error, 'Could not create the PDF.'), 'error');
    }
}

onMounted(load);
watch(() => route.params.id, load);
</script>

<template>
    <div class="space-y-6">
        <div v-if="loading" class="h-64 animate-pulse rounded-lg bg-slate-200" aria-busy="true" />

        <div v-else-if="notFound" class="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-slate-200">
            <h2 class="text-lg font-semibold text-slate-900">Invoice not found</h2>
            <RouterLink :to="{ name: 'invoices' }" class="mt-3 inline-block text-sm text-brand-600 hover:underline">Back to invoices</RouterLink>
        </div>

        <template v-else-if="invoice">
            <div class="flex flex-col gap-4 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="font-mono text-xs text-slate-500">{{ invoice.invoice_number }}</p>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ invoice.client?.name }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-slate-600">
                        <StatusBadge :status="invoice.status.value" :label="invoice.status.label" />
                        <span>Issued {{ invoice.issue_date }}</span>
                        <span>Due {{ invoice.due_date }}</span>
                        <RouterLink v-if="invoice.project" :to="{ name: 'project-show', params: { id: invoice.project.id } }" class="text-brand-700 hover:underline">
                            {{ invoice.project.code }}
                        </RouterLink>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="downloadPdf">Download PDF</button>
                    <button v-if="canEdit" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="editOpen = true">Edit draft</button>
                    <button v-else type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="openNotes">Edit notes</button>
                    <button v-if="invoice.status.value === 'draft'" type="button" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700" @click="ask('send', 'Send invoice?', 'Once sent, the items and amounts are locked. You can still change the notes and terms.', 'Send')">Send</button>
                    <button v-if="canPay" type="button" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700" @click="openPayment">Record payment</button>
                    <button v-if="canCancel" type="button" class="rounded-md border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50" @click="ask('cancel', 'Cancel invoice?', 'The invoice stays on record as cancelled. Use this instead of deleting.', 'Cancel invoice', true)">Cancel invoice</button>
                    <button v-if="canDelete" type="button" class="rounded-md border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50" @click="ask('delete', 'Delete draft?', 'Only drafts can be deleted. This cannot be undone.', 'Delete', true)">Delete draft</button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-4 py-3 text-left">Description</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Unit</th><th class="px-4 py-3 text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in invoice.items" :key="item.id">
                                <td class="px-4 py-3 text-slate-800">{{ item.description }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ fmt(item.quantity) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ fmt(item.unit_price) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ fmt(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="ml-auto w-full max-w-xs space-y-2 p-5 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span class="tabular-nums">{{ invoice.currency }} {{ fmt(invoice.subtotal) }}</span></div>
                        <div v-if="Number(invoice.discount) > 0" class="flex justify-between"><span class="text-slate-500">Discount</span><span class="tabular-nums">−{{ fmt(invoice.discount) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Tax ({{ fmt(invoice.tax_rate) }}%)</span><span class="tabular-nums">{{ fmt(invoice.tax_amount) }}</span></div>
                        <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold"><span>Total</span><span class="tabular-nums">{{ invoice.currency }} {{ fmt(invoice.total) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Paid</span><span class="tabular-nums">{{ fmt(invoice.amount_paid) }}</span></div>
                        <div class="flex justify-between font-semibold text-slate-900"><span>Balance due</span><span class="tabular-nums">{{ invoice.currency }} {{ fmt(invoice.balance) }}</span></div>
                    </div>
                </section>

                <aside class="space-y-6">
                    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <h3 class="text-sm font-semibold text-slate-900">Payments</h3>
                        <ul v-if="invoice.payments.length" class="mt-3 divide-y divide-slate-100">
                            <li v-for="p in invoice.payments" :key="p.id" class="py-2 text-sm">
                                <div class="flex justify-between"><span class="font-medium">{{ invoice.currency }} {{ fmt(p.amount) }}</span><span class="text-slate-500">{{ p.payment_date }}</span></div>
                                <p class="text-xs text-slate-500">{{ p.method.label }}<span v-if="p.reference"> · {{ p.reference }}</span></p>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-slate-500">No payments yet.</p>
                    </section>

                    <section v-if="invoice.notes || invoice.terms" class="rounded-lg bg-white p-5 text-sm shadow-sm ring-1 ring-slate-200">
                        <div v-if="invoice.terms"><h3 class="font-semibold text-slate-900">Terms</h3><p class="mt-1 whitespace-pre-line text-slate-600">{{ invoice.terms }}</p></div>
                        <div v-if="invoice.notes" class="mt-4"><h3 class="font-semibold text-slate-900">Notes</h3><p class="mt-1 whitespace-pre-line text-slate-600">{{ invoice.notes }}</p></div>
                    </section>
                </aside>
            </div>
        </template>

        <InvoiceFormModal :open="editOpen" :invoice-id="invoice?.id ?? null" @close="editOpen = false" @saved="load" />

        <Modal :open="notesOpen" title="Edit notes and terms" description="This invoice is locked. Only the notes and terms can change." size="md" :busy="busy" @close="notesOpen = false">
            <form id="notes-form" class="space-y-4" novalidate @submit.prevent="saveNotes">
                <div>
                    <label for="n-notes" class="block text-sm font-medium text-slate-700">Notes</label>
                    <textarea id="n-notes" v-model="notes.notes" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label for="n-terms" class="block text-sm font-medium text-slate-700">Terms</label>
                    <textarea id="n-terms" v-model="notes.terms" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                </div>
            </form>
            <template #footer>
                <button type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="notesOpen = false">Cancel</button>
                <button type="submit" form="notes-form" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60" :disabled="busy">Save</button>
            </template>
        </Modal>

        <Modal :open="paymentOpen" title="Record payment" :description="`Balance due: ${invoice?.currency ?? ''} ${fmt(invoice?.balance)}`" size="md" :busy="busy" @close="paymentOpen = false">
            <form id="payment-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="recordPayment">
                <FormInput id="p-amount" v-model="payment.amount" label="Amount" type="number" :error="paymentErrors.amount" required />
                <FormInput id="p-date" v-model="payment.payment_date" label="Payment date" type="date" :error="paymentErrors.payment_date" required />
                <FormInput id="p-method" v-model="payment.method" label="Method" :options="methodOptions" :error="paymentErrors.method" required />
                <FormInput id="p-ref" v-model="payment.reference" label="Reference" :error="paymentErrors.reference" />
                <div class="sm:col-span-2">
                    <FormInput id="p-notes" v-model="payment.notes" label="Notes" :error="paymentErrors.notes" />
                </div>
            </form>
            <template #footer>
                <button type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="paymentOpen = false">Cancel</button>
                <button type="submit" form="payment-form" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60" :disabled="busy">Save payment</button>
            </template>
        </Modal>

        <ConfirmModal
            :open="confirm.open"
            :title="confirm.title"
            :message="confirm.message"
            :confirm-label="confirm.label"
            :danger="confirm.danger"
            :busy="busy"
            @confirm="runConfirmed"
            @cancel="confirm.open = false"
        />
    </div>
</template>
