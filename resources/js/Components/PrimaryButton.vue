<template>
    <button
        class="
            relative
            overflow-hidden

            inline-flex
            items-center
            justify-center

            px-5
            py-2.5

            rounded-xl

            font-bold
            text-xs
            uppercase
            tracking-wider

            text-gray-700

            bg-white

            border
            border-red-100

            shadow-md

            transition-all
            duration-300

            hover:border-amber-300

            hover:text-red-700

            hover:shadow-xl
            hover:shadow-red-500/10

            hover:bg-gradient-to-r
            hover:from-red-50
            hover:to-amber-50

            active:scale-[0.98]

            focus:outline-none
            focus:ring-4
            focus:ring-red-500/15
            focus:ring-offset-2
        "
    >
        <span
            class="
                absolute
                inset-x-0
                top-0

                h-0.5

                bg-gradient-to-r
                from-red-600
                via-red-500
                to-amber-500
            "
        ></span>

        <span class="relative z-10">
            <slot />
        </span>
    </button>
</template>