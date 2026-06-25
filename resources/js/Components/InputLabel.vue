<script setup>
defineProps({
    value: {
        type: String,
    },
});
</script>

<template>
    <label
        class="
            inline-flex
            items-center
            gap-2

            mb-2

            text-sm
            font-bold
            text-gray-700

            tracking-wide
        "
    >
        <span
            class="
                w-1.5
                h-5

                rounded-full

                bg-gradient-to-b
                from-red-500
                via-red-500
                to-amber-500

                shadow-sm
            "
        ></span>

        <span
            class="
                bg-gradient-to-r
                from-gray-900
                to-gray-700

                bg-clip-text
                text-transparent
            "
        >
            <span v-if="value">
                {{ value }}
            </span>

            <span v-else>
                <slot />
            </span>
        </span>
    </label>
</template>