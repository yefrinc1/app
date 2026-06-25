<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2.5 text-start text-sm font-semibold text-gray-700 hover:text-red-700 hover:bg-gradient-to-r hover:from-red-50 hover:to-amber-50 focus:outline-none focus:bg-red-50 transition-all duration-200"
    >
        <slot />
    </Link>
</template>