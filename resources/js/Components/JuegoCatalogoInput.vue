<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const model = defineModel({ type: String, default: '' });

const props = defineProps({
    inputId: {
        type: String,
        default: 'juego',
    },
    error: {
        type: String,
        default: '',
    },
});

const sugerencias = ref([]);
const juegoValido = ref(null);
const juegoSeleccionado = ref(false);
const imagenJuegoSeleccionado = ref(null);
const emit = defineEmits(['juego-seleccionado']);

const buscarJuegos = async () => {
    juegoSeleccionado.value = false;

    if (!model.value || model.value.trim().length < 1) {
        sugerencias.value = [];
        juegoValido.value = null;
        return;
    }

    try {
        const { data } = await axios.get(route('buscar-juegos-catalogo'), {
            params: { nombre: model.value },
        });

        sugerencias.value = data.juegos;
        juegoValido.value = data.exact_match;

        if (data.exact_match) {
            juegoSeleccionado.value = true;
            sugerencias.value = [];
        }
    } catch (error) {
        console.error('Error buscando juegos del catálogo:', error);
        sugerencias.value = [];
        juegoValido.value = false;
    }
};

const seleccionarJuego = (juego) => {
    model.value = juego.nombre;
    imagenJuegoSeleccionado.value = juego.url_imagen;
    sugerencias.value = [];
    juegoValido.value = true;
    juegoSeleccionado.value = true;

    emit('juego-seleccionado', juego.nombre);
};

watch(model, (valor) => {
    if (!valor || valor.trim().length < 1) {
        sugerencias.value = [];
        juegoValido.value = null;
        juegoSeleccionado.value = false;
    }
});

onMounted(() => {
    if (model.value?.trim()) {
        buscarJuegos();
    }
});
</script>

<template>
    <div class="relative overflow-visible">
        <div class="flex items-start gap-3 overflow-visible">
            <div class="relative z-30 flex-1 overflow-visible">
                <div class="relative">
                    <TextInput
                        :id="inputId"
                        v-model="model"
                        type="text"
                        class="mt-1 block w-full pr-11"
                        autocomplete="off"
                        @input="buscarJuegos"
                    />

                    <div
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2"
                    >
                        <i
                            v-if="juegoValido === true"
                            class="fa-solid fa-circle-check text-green-500"
                        ></i>

                        <i
                            v-else-if="juegoValido === false && model.trim() !== ''"
                            class="fa-solid fa-circle-xmark text-red-500"
                        ></i>

                        <i
                            v-else
                            class="fa-solid fa-magnifying-glass text-amber-500"
                        ></i>
                    </div>
                </div>

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 translate-y-2 scale-95"
                    enter-to-class="opacity-100 translate-y-0 scale-100"
                    leave-active-class="transition ease-in duration-100"
                    leave-from-class="opacity-100 translate-y-0 scale-100"
                    leave-to-class="opacity-0 translate-y-2 scale-95"
                >
                    <ul
                        v-if="model !== '' && sugerencias.length > 0 && !juegoSeleccionado"
                        class="
                            absolute
                            left-0
                            right-0
                            top-full
                            z-50

                            mt-2
                            max-h-72
                            overflow-y-auto

                            rounded-2xl
                            border
                            border-red-100

                            bg-white/95
                            backdrop-blur-md

                            shadow-2xl
                            shadow-red-500/10

                            ring-1
                            ring-red-500/10
                        "
                    >
                        <li
                            v-for="juego in sugerencias"
                            :key="juego.id"
                            @click="seleccionarJuego(juego)"
                            class="
                                group
                                flex
                                cursor-pointer
                                items-center
                                gap-3

                                border-b
                                border-red-50

                                px-3
                                py-2.5

                                text-sm
                                font-semibold
                                text-gray-700

                                transition-all
                                duration-200

                                last:border-b-0

                                hover:bg-gradient-to-r
                                hover:from-red-50
                                hover:to-amber-50
                                hover:text-red-700
                            "
                        >
                            <div
                                class="
                                    flex
                                    h-10
                                    w-10
                                    shrink-0
                                    items-center
                                    justify-center
                                    overflow-hidden

                                    rounded-xl

                                    border
                                    border-red-100

                                    bg-gradient-to-br
                                    from-red-50
                                    to-amber-50

                                    shadow-sm
                                "
                            >
                                <img
                                    v-if="juego.url_imagen"
                                    :src="juego.url_imagen"
                                    :alt="juego.nombre"
                                    class="h-full w-full object-cover"
                                />

                                <i
                                    v-else
                                    class="fa-solid fa-gamepad text-red-400"
                                ></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate">
                                    {{ juego.nombre }}
                                </p>
                            </div>

                            <i
                                class="
                                    fa-solid
                                    fa-chevron-right
                                    text-xs
                                    text-amber-500
                                    opacity-0
                                    transition-all
                                    duration-200
                                    group-hover:opacity-100
                                    group-hover:translate-x-1
                                "
                            ></i>
                        </li>
                    </ul>
                </Transition>
            </div>

            <div
                v-if="juegoValido === true && imagenJuegoSeleccionado"
                class="
                    mt-1
                    flex
                    h-12
                    w-12
                    shrink-0
                    items-center
                    justify-center
                    overflow-hidden

                    rounded-2xl

                    border
                    border-red-200

                    bg-gradient-to-br
                    from-red-50
                    to-amber-50

                    shadow-lg
                    shadow-red-500/15
                "
            >
                <img
                    :src="imagenJuegoSeleccionado"
                    :alt="model"
                    class="h-full w-full object-cover"
                />
            </div>

            <div
                v-else-if="juegoValido === false && model.trim() !== ''"
                class="
                    mt-1
                    flex
                    h-12
                    w-12
                    shrink-0
                    items-center
                    justify-center

                    rounded-2xl

                    border
                    border-red-200

                    bg-red-50

                    text-red-600

                    shadow-md
                    shadow-red-500/10
                "
                title="Juego no encontrado en el catálogo"
            >
                <i class="fa-solid fa-circle-xmark text-xl"></i>
            </div>
        </div>

        <InputError class="mt-2" :message="error" />
    </div>
</template>