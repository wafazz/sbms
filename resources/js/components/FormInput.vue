<script setup>
defineProps({
    modelValue: { type: [String, Number, Boolean], default: '' },
    label: { type: String, required: true },
    id: { type: String, required: true },
    type: { type: String, default: 'text' },
    error: { type: String, default: '' },
    options: { type: Array, default: null }, // [{ value, label }] renders a select
    autocomplete: { type: String, default: null },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const fieldClass = (hasError) =>
    `mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 ${
        hasError
            ? 'border-rose-400 focus:ring-rose-200'
            : 'border-slate-300 focus:border-brand-600 focus:ring-brand-50'
    }`;
</script>

<template>
    <div>
        <label :for="id" class="block text-sm font-medium text-slate-700">
            {{ label }}<span v-if="required" class="text-rose-600"> *</span>
        </label>

        <select
            v-if="options"
            :id="id"
            :value="modelValue"
            :class="fieldClass(!!error)"
            :aria-invalid="!!error"
            :aria-describedby="error ? `${id}-error` : null"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>

        <input
            v-else
            :id="id"
            :type="type"
            :value="modelValue"
            :autocomplete="autocomplete"
            :class="fieldClass(!!error)"
            :aria-invalid="!!error"
            :aria-describedby="error ? `${id}-error` : null"
            @input="$emit('update:modelValue', type === 'checkbox' ? $event.target.checked : $event.target.value)"
        />

        <p v-if="error" :id="`${id}-error`" class="mt-1 text-sm text-rose-600">{{ error }}</p>
    </div>
</template>
