<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Responsive dialog for every add and edit form.
 * Phones: bottom sheet. Larger screens: centred card.
 * Escape, the close button and a backdrop click all close it (unless busy or not dismissible).
 * Focus stays inside while open and returns to the trigger when closed.
 */
const props = defineProps({
    open: { type: Boolean, required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    size: { type: String, default: 'md', validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v) },
    busy: { type: Boolean, default: false },
    dismissible: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const sizeClass = {
    sm: 'sm:max-w-md',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
};

let uid = 0;
const titleId = `modal-title-${++uid}`;
const panel = ref(null);
let previouslyFocused = null;
let previousOverflow = '';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

function close() {
    if (props.busy || !props.dismissible) return;
    emit('close');
}

function trapFocus(event) {
    const nodes = panel.value ? [...panel.value.querySelectorAll(FOCUSABLE)] : [];
    if (!nodes.length) return;

    const first = nodes[0];
    const last = nodes[nodes.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    } else if (event.key === 'Tab') {
        trapFocus(event);
    }
}

function lock() {
    previouslyFocused = document.activeElement;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKeydown);
}

function unlock() {
    document.body.style.overflow = previousOverflow;
    document.removeEventListener('keydown', onKeydown);
    if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
        previouslyFocused.focus();
    }
    previouslyFocused = null;
}

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            lock();
            await nextTick();
            const firstField = panel.value?.querySelector('input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled])');
            (firstField ?? panel.value?.querySelector(FOCUSABLE))?.focus();
        } else if (previouslyFocused !== null) {
            unlock();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (props.open) unlock();
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 sm:items-center sm:p-4"
                @mousedown.self="close"
            >
                <div
                    ref="panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    class="flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-xl transition-transform duration-200 sm:max-h-[90vh] sm:rounded-2xl"
                    :class="sizeClass[size]"
                >
                    <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <h2 :id="titleId" class="text-lg font-semibold text-slate-900">{{ title }}</h2>
                            <p v-if="description" class="mt-0.5 text-sm text-slate-500">{{ description }}</p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-50"
                            aria-label="Close"
                            :disabled="busy || !dismissible"
                            @click="close"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                            </svg>
                        </button>
                    </header>

                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                        <slot />
                    </div>

                    <footer v-if="$slots.footer" class="flex shrink-0 flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:flex-row sm:justify-end sm:rounded-b-2xl sm:px-6">
                        <slot name="footer" />
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
