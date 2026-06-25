<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({
    focus: () => input.value.focus()
});
</script>

<template>
<input
    v-model="model"
    ref="input"
    class="
        w-full
        rounded-xl
        border
        border-red-500/40

        bg-gradient-to-r
        from-white
        via-red-50
        to-amber-50

        px-4
        py-2.5

        shadow-md
        transition-all
        duration-300

        focus:outline-none
        focus:border-amber-500

        focus:ring-4
        focus:ring-red-500/20

        focus:shadow-lg
        focus:shadow-red-500/20
    "
/>
</template>