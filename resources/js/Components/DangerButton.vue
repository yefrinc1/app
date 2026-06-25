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

            text-white

            bg-gradient-to-r
            from-red-600
            via-red-500
            to-amber-500

            shadow-lg
            shadow-red-500/25

            transition-all
            duration-300

            hover:-translate-y-0.5
            hover:shadow-xl
            hover:shadow-red-500/35

            active:translate-y-0
            active:scale-[0.98]

            focus:outline-none
            focus:ring-4
            focus:ring-red-500/20
            focus:ring-offset-2

            before:absolute
            before:inset-0
            before:bg-white/0
            hover:before:bg-white/10
            before:transition-all
            before:duration-300
        "
    >
        <span class="relative z-10 flex items-center gap-2">
            <slot />
        </span>
    </button>
</template>