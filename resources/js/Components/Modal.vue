<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

watch(
    () => props.show,
    () => {
        if (props.show) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = null;
        }
    }
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = null;
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
    <Teleport to="body">
        <Transition leave-active-class="duration-200">
            <div
                v-show="show"
                class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
                scroll-region
            >
                <Transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-show="show"
                        class="fixed inset-0 transform transition-all"
                        @click="close"
                    >
                        <div
                            class="
                                absolute
                                inset-0

                                bg-gradient-to-br
                                from-gray-950/90
                                via-red-950/70
                                to-amber-950/60

                                backdrop-blur-sm
                            "
                        />
                    </div>
                </Transition>

                <Transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                >
                    <div
                        v-show="show"
                        class="
                            relative
                            mb-6
                            overflow-hidden

                            rounded-3xl

                            bg-white

                            shadow-2xl
                            shadow-red-950/30

                            transform
                            transition-all

                            sm:w-full
                            sm:mx-auto

                            border
                            border-red-100
                        "
                        :class="maxWidthClass"
                    >
                        <div
                            class="
                                h-1.5
                                w-full

                                bg-gradient-to-r
                                from-red-600
                                via-red-500
                                to-amber-500
                            "
                        ></div>

                        <div class="relative">
                            <div
                                class="
                                    pointer-events-none
                                    absolute
                                    -top-24
                                    -right-24

                                    h-48
                                    w-48

                                    rounded-full

                                    bg-red-500/10
                                    blur-3xl
                                "
                            ></div>

                            <div
                                class="
                                    pointer-events-none
                                    absolute
                                    -bottom-24
                                    -left-24

                                    h-48
                                    w-48

                                    rounded-full

                                    bg-amber-500/10
                                    blur-3xl
                                "
                            ></div>

                            <slot v-if="show" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>