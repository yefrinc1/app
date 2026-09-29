<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    rows: { type: Number, default: 3 },
    required: { type: Boolean, default: false },
    minlength: { type: [String, Number], default: undefined },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <InputLabel :for="id" :value="label" />
        <textarea
            :id="id"
            :value="modelValue"
            :rows="rows"
            :required="required"
            :minlength="minlength"
            :placeholder="placeholder"
            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm transition focus:border-red-500 focus:ring-red-500"
            @input="$emit('update:modelValue', $event.target.value)"
        ></textarea>
        <InputError class="mt-2" :message="error" />
    </div>
</template>
