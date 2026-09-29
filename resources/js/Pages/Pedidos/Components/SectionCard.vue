<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: '' },
});
</script>

<template>
    <section class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
        <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-bold text-gray-900">
                    <i v-if="icon" :class="icon"></i>
                    {{ title }}
                </h2>
                <p v-if="description" class="mt-1 text-sm text-gray-600">{{ description }}</p>
            </div>
            <slot name="actions" />
        </header>
        <slot />
    </section>
</template>
