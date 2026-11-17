<script setup>
defineProps({
    open: { type: Boolean, required: true },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    danger: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4" @click.self="emit('cancel')">
            <div role="dialog" aria-modal="true" :aria-label="title" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
                <p v-if="message" class="mt-2 text-sm text-slate-600">{{ message }}</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" :disabled="busy" @click="emit('cancel')">
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                        :class="danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-brand-600 hover:bg-brand-700'"
                        :disabled="busy"
                        @click="emit('confirm')"
                    >
                        {{ confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
