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
});

const form = useForm({
    correo: "",
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

// Emitir cambios al padre
const emit = defineEmits(["update:modelValue"]);

// Variables reactivas
form.correo = ref(props.modelValue || "");
const sugerencias = ref([]);
const codigo = ref();
const codigoEncontrado = ref(false);
const ultimoCodigo = ref();
const codigoRef = ref(null); // Referencia al h2
const textoCopiado = ref(false); // Referencia al h2
const disabledButton = ref(true); // Deshabilitar botón inicialmente

// Observa cambios en modelValue y actualiza searchQuery
watch(
    () => props.modelValue,
    (nuevoValor) => {
        form.correo = nuevoValor;
    }
);

// Función para buscar correos en el backend
const buscarCorreos = async () => {
    form.errors.correo = '';

    if (form.correo.length < 1) {
        sugerencias.value = [];
        disabledButton.value = false;
        return;
    }

    try {
        const { data } = await axios.get(route("buscar-correos"), {
            params: { q: form.correo },
        });
        sugerencias.value = data;

        if (sugerencias.value.length == 1) {
            disabledButton.value = sugerencias.value[0]['correo'] != form.correo;
            sugerencias.value = sugerencias.value[0]['correo'] == form.correo ? [] : sugerencias.value;
        } else if (sugerencias.value.length == 0) {
            disabledButton.value = true;
            form.errors.correo = 'No se encontró ningún correo';
        }
    } catch (error) {
        console.error("Error buscando correos:", error);
    }
};

// Función para seleccionar un correo
const seleccionarCorreo = (correo) => {
    form.correo = correo.correo; // Asigna el valor seleccionado
    sugerencias.value = []; // Oculta la lista
    disabledButton.value = false;
    emit("update:modelValue", correo.correo); // Actualiza el v-model
};

const generarCodigo = async () => {
    textoCopiado.value = false;
    codigoEncontrado.value = false;
    try {
        const { data } = await axios.get(route("codigo-generar-disponible"), {
                params: { correo: form.correo },
        });
        
        if (data.length === 0) {
            swalWithTailwind.fire({
                title: 'El correo no tiene codigos registrados',
                icon: "error",
            });
        } else {
            if (data.mensaje) {
                swalWithTailwind.fire({
                    title: data.mensaje,
                    icon: "warning",
                });
            } else {
                codigo.value = data.codigo;
                codigoEncontrado.value = true;
                ultimoCodigo.value = data.ultimo_codigo;
                form.reset();
            }
        }
    } catch (error) {
        console.error("Error buscando codigo:", error);
    }
}

const copiarCodigo = async () => {
    if (!codigoRef.value) return;

    textoCopiado.value = false;
    const texto = codigoRef.value.innerText || codigoRef.value.textContent; // Obtener todo el texto

    // Intentar copiar con Clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(texto);
            textoCopiado.value = true;
            return;
        } catch (err) {
            console.error("Error al copiar con clipboard API:", err);
        }
    }

    // Alternativa con execCommand si Clipboard API falla
    try {
        const input = document.createElement("textarea");
        input.value = texto;
        document.body.appendChild(input);
        input.select();
        document.execCommand("copy");
        document.body.removeChild(input);
        textoCopiado.value = true;
    } catch (error) {
        console.error("Error al copiar el código:", error);
    }
};

</script>

<template>
    <Head title="Generar Código Verificación" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🪄 Generar Código Verificación</h2>
        </template>

        <template #contenido-pagina>       
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Información codigo verificación</h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Datos para generar el codigo para el correo
                        </p>
                    </header>

                    <form @submit.prevent="generarCodigo" class="mt-6 space-y-6 w-full md:w-1/2">
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

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="disabledButton">
                                Generar
                            </PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>

            <div v-if="codigoEncontrado" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <header>
                    <div class="flex items-center gap-3">
                        <h2 ref="codigoRef" class="text-lg font-medium text-gray-900">
                            🔑 Código de verificación: {{ codigo }}
                        </h2>
                        <button 
                            @click="copiarCodigo"
                            class="bg-gray-600 hover:bg-gray-700 text-white font-semibold p-2 rounded-md shadow-md transition-all duration-300 active:scale-90 flex ml-2 items-center justify-center"
                        >
                            <i class="fa-regular fa-copy text-sm"></i>
                        </button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-if="textoCopiado" class="text-sm text-gray-600">Copiado.</p>
                        </Transition>
                    </div>

                    <p v-if="ultimoCodigo" class="mt-1 text-sm text-gray-600">
                        Ya no quedan más códigos de verificación, notificar al administrador 🚨
                    </p>
                </header>
            </div>

        </template>
    </LayoutPageHeader>
</template>