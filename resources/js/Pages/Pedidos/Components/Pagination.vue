<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ links: { type: Array, default: () => [] } });
</script>

<template>
    <div v-if="links.length > 3" class="mt-4">
        <div class="flex flex-wrap justify-center gap-2">
            <template v-for="(link, index) in links" :key="index">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    v-html="link.label"
                    class="rounded-md border px-3 py-2 text-xs font-semibold transition duration-150 ease-in-out"
                    :class="link.active
                        ? 'border-gray-800 bg-gray-800 text-white'
                        : 'border-transparent bg-gray-200 text-gray-700 hover:bg-gray-300'"
                />
            </template>
        </div>
    </div>
</template>
