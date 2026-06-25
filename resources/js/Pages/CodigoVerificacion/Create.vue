<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { useForm, Head } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { ref, watch } from "vue";
import axios from "axios";
import Swal from 'sweetalert2';

const props = defineProps({
    modelValue: String,
    correo: String
});

const form = useForm({
    correo: props.correo,
    codigo: "",
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

// Emitir cambios al padre
const emit = defineEmits(["update:modelValue"]);

// Variables reactivas
form.correo = ref(props.correo || "");
const sugerencias = ref([]);
const contrasena = ref();
const codigoVF = ref();
const habilitarContrasena = ref(false);

// Observa cambios en modelValue y actualiza searchQuery
watch(
    () => props.modelValue,
    (nuevoValor) => {
        form.correo = nuevoValor;
    }
);

// Función para buscar correos en el backend
const buscarCorreos = async () => {
    habilitarContrasena.value = false;
    form.errors.correo = '';

    if (form.correo.length < 1) {
        sugerencias.value = [];
        return;
    }

    try {
        const { data } = await axios.get(route("buscar-correos"), {
            params: { q: form.correo },
        });
        sugerencias.value = data;

        if (sugerencias.value.length == 0) {
            form.errors.correo = 'No se encontró ningún correo';
        }
    } catch (error) {
        console.error("Error buscando correos:", error);
    }
};

// Función para seleccionar un correo
const seleccionarCorreo = async (correo) => {
    form.correo = correo.correo; // Asigna el valor seleccionado
    contrasena.value = correo.contrasena;
    sugerencias.value = []; // Oculta la lista
    emit("update:modelValue", correo.correo); // Actualiza el v-model

    try {
        const { data } = await axios.get(route("consultar-codigo"), {
                params: { correo: form.correo },
        });
        
        codigoVF.value = data;
        habilitarContrasena.value = true;
    } catch (error) {
        console.error("Error buscando codigo:", error);
    }
};

if (form.correo && form.correo !== null && form.correo !== "") {
    (async () => {
        try {
            const { data } = await axios.get(route("buscar-correos"), {
                params: { q: form.correo },
            });
            sugerencias.value = data;
            if (sugerencias.value.length > 0) {
                // Buscar el objeto que coincida con el correo
                const encontrado = sugerencias.value.find(c => c.correo === form.correo);
                if (encontrado) {
                    seleccionarCorreo(encontrado);
                }
            } else {
                form.errors.correo = 'No se encontró ningún correo';
            }
        } catch (error) {
            console.error("Error buscando correos:", error);
        }
    })();
}

// Función para manejar el envío manualmente
const submitForm = () => {
    form.post(route("codigo-verificacion.store"), {
        onSuccess: () => {
            swalWithTailwind.fire({
                title: 'Codigos creados correctamente',
                icon: "success",
            });
            form.reset();
            habilitarContrasena.value = false;
            codigoVF.value = "";
            contrasena.value = "";
        },
        onError: (errors) => {
            console.log("Errores en el formulario:", errors);
        },
    });
};

</script>

<template>
    <Head title="Crear Codigo Verificacion" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🔨 Crear Codigos Verificación</h2>
        </template>

        <template #contenido-pagina>       
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Información codigos verificación</h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Datos para la creación de codigos para los correos
                        </p>
                    </header>
                    <form @submit.prevent="submitForm" class="mt-6 space-y-6 w-full md:w-1/2">
                        <div>
                            <InputLabel for="correo" :value="$t('Email')" />

                            <div class="relative w-full">
                                <TextInput
                                    id="correo"
                                    ref="correo"
                                    v-model="form.correo"
                                    @input="buscarCorreos"
                                    type="text"
                                    class="mt-1 w-full"
                                    autocomplete="off"
                                    required
                                />

                                <ul
                                    v-if="form.correo !== '' && sugerencias.length !== 0"
                                    class="absolute bg-white border border-gray-300 w-full mt-1 rounded-md shadow-md"
                                >
                                    <li
                                        v-for="correo in sugerencias"
                                        :key="correo.id"
                                        @click="seleccionarCorreo(correo)"
                                        class="p-2 cursor-pointer bg-white hover:bg-gray-100 text-black"
                                    >
                                        {{ correo.correo }}
                                    </li>
                                </ul>
                            </div>
                        
                            <InputError class="mt-2" :message="form.errors.correo" />
                        </div>

                        <div v-if="habilitarContrasena" class="flex items-center gap-4 w-full">
                            <div class="w-full">
                                <InputLabel :value="$t('Password')" />
                                <TextInput
                                    v-model="contrasena"
                                    type="text"
                                    class="mt-1 w-full"
                                    disabled="true"
                                />
                            </div>

                            <div class="w-1/3">
                                <InputLabel value="Codigo" />
                                <TextInput
                                    v-model="codigoVF"
                                    type="text"
                                    class="mt-1 w-full"
                                    disabled="true"
                                />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="codigo" value="Codigos" />

                            <div class="relative">
                                <textarea
                                    id="codigo"
                                    ref="codigo"
                                    rows="4"
                                    v-model="form.codigo"
                                    required
                                    class="
                                        mt-1
                                        block
                                        w-full

                                        rounded-xl

                                        border
                                        border-red-500/30

                                        bg-gradient-to-r
                                        from-white
                                        via-red-50
                                        to-amber-50

                                        px-4
                                        py-3
                                        pr-12

                                        text-gray-800
                                        font-medium

                                        shadow-md

                                        transition-all
                                        duration-300

                                        hover:border-red-500/50

                                        focus:outline-none
                                        focus:border-amber-500

                                        focus:ring-4
                                        focus:ring-red-500/20

                                        focus:shadow-lg
                                        focus:shadow-red-500/20

                                        resize-y
                                    "
                                ></textarea>

                                <div
                                    class="
                                        pointer-events-none
                                        absolute
                                        top-4
                                        right-4

                                        flex
                                        items-center
                                        justify-center

                                        h-8
                                        w-8

                                        rounded-lg

                                        bg-gradient-to-r
                                        from-red-500
                                        to-amber-500

                                        text-white

                                        shadow-md
                                    "
                                >
                                    <i class="fa-solid fa-key"></i>
                                </div>
                            </div>


                            <InputError :message="form.errors.codigo" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">{{ $t("Save") }}</PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>