<script setup>
import { computed } from 'vue';

const emit = defineEmits(['update:checked']);

const props = defineProps({
    checked: {
        type: [Array, Boolean],
        required: true,
    },
    value: {
        default: null,
    },
});

const proxyChecked = computed({
    get() {
        return props.checked;
    },

    set(val) {
        emit('update:checked', val);
    },
});
</script>

<template>
    <input
        type="checkbox"
        :value="value"
        v-model="proxyChecked"
        class="
            h-5
            w-5

            rounded-lg

            border-2
            border-red-300

            text-red-600

            bg-white

            shadow-md

            transition-all
            duration-300

            hover:scale-110
            hover:border-red-500

            checked:border-red-600
            checked:shadow-lg
            checked:shadow-red-500/30

            focus:ring-4
            focus:ring-red-500/20
            focus:ring-offset-0
        "
    />
</template>