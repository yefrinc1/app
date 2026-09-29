<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    error: { type: String, default: '' },
    icon: { type: String, default: 'fa-solid fa-chevron-down' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue', 'change']);
</script>

<template>
    <div>
        <InputLabel :for="id" :value="label" />
        <div class="relative">
            <select
                :id="id"
                :value="modelValue"
                :required="required"
                :disabled="disabled"
                class="mt-1 block w-full appearance-none rounded-xl border border-red-500/30 bg-gradient-to-r from-white via-red-50 to-amber-50 px-4 py-2.5 pr-10 font-semibold text-gray-800 shadow-md transition-all duration-300 hover:border-red-500/50 focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-red-500/20 focus:shadow-lg focus:shadow-red-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                @input="$emit('update:modelValue', $event.target.value)"
                @change="$emit('change', $event)"
            >
                <slot />
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center pt-1 text-amber-500">
                <i :class="icon"></i>
            </div>
        </div>
        <InputError class="mt-2" :message="error" />
    </div>
</template>
