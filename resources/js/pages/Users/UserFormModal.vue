<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useUsersStore } from '../../stores/users.js';
import { useToastStore } from '../../stores/toast.js';
import { errorMessage, fieldErrors } from '../../api/errors.js';
import Modal from '../../components/Modal.vue';
import FormInput from '../../components/FormInput.vue';

const props = defineProps({
    open: { type: Boolean, required: true },
    user: { type: Object, default: null }, // null = create
});
const emit = defineEmits(['close', 'saved']);

const users = useUsersStore();
const toast = useToastStore();
const submitting = ref(false);
const errors = ref({});
const form = reactive({ name: '', email: '', password: '', role: 'staff', is_active: true });

const isEdit = computed(() => Boolean(props.user?.id));

const roleOptions = [
    { value: 'staff', label: 'Staff' },
    { value: 'admin', label: 'Admin' },
];

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        errors.value = {};
        Object.assign(form, {
            name: props.user?.name ?? '',
            email: props.user?.email ?? '',
            password: '',
            role: props.user?.role ?? 'staff',
            is_active: props.user?.is_active ?? true,
        });
    },
);

function payload() {
    const data = { name: form.name, email: form.email, role: form.role, is_active: form.is_active };
    if (form.password) data.password = form.password;
    return data;
}

async function save() {
    submitting.value = true;
    errors.value = {};
    try {
        if (isEdit.value) {
            await users.update(props.user.id, payload());
            toast.push('User updated.');
        } else {
            await users.create({ ...payload(), password: form.password });
            toast.push('User added.');
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
        :title="isEdit ? 'Edit user' : 'Add user'"
        :description="isEdit ? 'Change the role, status or details. Leave the password blank to keep it.' : 'Create an account. Share the initial password securely.'"
        size="md"
        :busy="submitting"
        @close="emit('close')"
    >
        <form id="user-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
            <div class="sm:col-span-2">
                <FormInput id="u-name" v-model="form.name" label="Name" :error="errors.name" required />
            </div>
            <div class="sm:col-span-2">
                <FormInput id="u-email" v-model="form.email" label="Email" type="email" :error="errors.email" required />
            </div>
            <FormInput id="u-role" v-model="form.role" label="Role" :options="roleOptions" :error="errors.role" required />
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600" />
                    Active (can sign in)
                </label>
            </div>
            <div class="sm:col-span-2">
                <FormInput
                    id="u-password"
                    v-model="form.password"
                    :label="isEdit ? 'New password (optional)' : 'Initial password'"
                    type="password"
                    :error="errors.password"
                    :required="!isEdit"
                />
            </div>
        </form>

        <template #footer>
            <button type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="submitting" @click="emit('close')">Cancel</button>
            <button type="submit" form="user-form" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60" :disabled="submitting">
                {{ submitting ? 'Saving…' : isEdit ? 'Save changes' : 'Add user' }}
            </button>
        </template>
    </Modal>
</template>
