<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full ps-4 pe-4 py-3 border-l-4 border-amber-400 text-start text-base font-bold text-red-800 bg-gradient-to-r from-red-50 to-amber-50 shadow-sm transition-all duration-300'
        : 'block w-full ps-4 pe-4 py-3 border-l-4 border-transparent text-start text-base font-semibold text-gray-600 hover:text-red-700 hover:bg-gradient-to-r hover:from-red-50 hover:to-amber-50 hover:border-red-400 transition-all duration-300'
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>