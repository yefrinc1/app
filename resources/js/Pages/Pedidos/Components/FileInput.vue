<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    accept: { type: String, default: 'image/*,.pdf' },
    fileName: { type: String, default: '' },
});

defineEmits(['change']);
</script>

<template>
    <div>
        <InputLabel :for="id" :value="label" />
        <label :for="id" class="mt-1 flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-dashed border-red-300 bg-red-50/60 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-red-500 hover:bg-red-50">
            <i class="fa-solid fa-paperclip text-red-600"></i>
            <span class="truncate">{{ fileName || 'Seleccionar comprobante' }}</span>
        </label>
        <input :id="id" type="file" :accept="accept" :required="required" class="sr-only" @change="$emit('change', $event)" />
        <InputError class="mt-2" :message="error" />
    </div>
</template>
