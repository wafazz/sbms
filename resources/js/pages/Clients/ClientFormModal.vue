<script setup>
import { reactive, ref, watch } from 'vue';
import { useClientsStore } from '../../stores/clients.js';
import { useToastStore } from '../../stores/toast.js';
import { errorMessage, fieldErrors } from '../../api/errors.js';
import Modal from '../../components/Modal.vue';
import FormInput from '../../components/FormInput.vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    client: { type: Object, default: null }, // null = create
});
const emit = defineEmits(['close', 'saved']);

const clients = useClientsStore();
const toast = useToastStore();
const submitting = ref(false);
const errors = ref({});
const form = reactive({ name: '', email: '', phone: '', company: '', address: '', notes: '' });

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        errors.value = {};
        Object.keys(form).forEach((k) => (form[k] = props.client?.[k] ?? ''));
    },
);

async function save() {
    submitting.value = true;
    errors.value = {};
    try {
        if (props.client?.id) {
            await clients.update(props.client.id, { ...form });
            toast.push('Client updated.');
        } else {
            await clients.create({ ...form });
            toast.push('Client added.');
        }
        emit('saved');
        emit('close');
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
</script>

<template>
    <Modal
        :open="open"
        :title="client?.id ? 'Edit client' : 'Add client'"
        :description="client?.id ? 'Update the contact and company details.' : 'Create a client to link projects and invoices to.'"
        size="lg"
        :busy="submitting"
        @close="emit('close')"
    >
        <form id="client-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
            <FormInput id="c-name" v-model="form.name" label="Name" :error="errors.name" required />
            <FormInput id="c-company" v-model="form.company" label="Company" :error="errors.company" />
            <FormInput id="c-email" v-model="form.email" label="Email" type="email" :error="errors.email" />
            <FormInput id="c-phone" v-model="form.phone" label="Phone" :error="errors.phone" />
            <div class="sm:col-span-2">
                <FormInput id="c-address" v-model="form.address" label="Address" :error="errors.address" />
            </div>
            <div class="sm:col-span-2">
                <FormInput id="c-notes" v-model="form.notes" label="Notes" :error="errors.notes" />
            </div>
        </form>

        <template #footer>
            <button type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="submitting" @click="emit('close')">Cancel</button>
            <button type="submit" form="client-form" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60" :disabled="submitting">
                {{ submitting ? 'Saving…' : client?.id ? 'Save changes' : 'Add client' }}
            </button>
        </template>
    </Modal>
</template>
