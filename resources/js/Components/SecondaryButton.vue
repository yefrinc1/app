<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="
            relative
            overflow-hidden

            inline-flex
            items-center

            px-5
            py-2.5

            rounded-xl

            bg-gradient-to-r
            from-gray-900
            via-slate-900
            to-gray-900

            text-white
            font-bold
            text-sm

            border
            border-red-500/20

            shadow-lg
            shadow-black/30

            transition-all
            duration-300

            hover:border-amber-500/40
            hover:shadow-red-500/10

            focus:outline-none
            focus:ring-4
            focus:ring-red-500/10
        "
    >
        <span
            class="
                absolute
                top-0
                left-0
                right-0

                h-[2px]

                bg-gradient-to-r
                from-red-600
                to-amber-500
            "
        ></span>

        <span class="relative">
            <slot />
        </span>
    </button>
</template>