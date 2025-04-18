<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { useForm, Head } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { ref, watch } from "vue";
import Modal from '@/Components/Modal.vue';
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
const confirmarModalConsultar = ref(false);
const codigos = ref();

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
        return;
    }

    try {
        const { data } = await axios.get(route("buscar-correos"), {
            params: { q: form.correo },
        });
        sugerencias.value = data;

        if (sugerencias.value.length == 1) {
            sugerencias.value = sugerencias.value[0]['correo'] == form.correo ? [] : sugerencias.value;
        } else if (sugerencias.value.length == 0) {
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
    emit("update:modelValue", correo.correo); // Actualiza el v-model
};

const abrirModalCodigos = async () => {
    try {
        const { data } = await axios.get(route("consultar-todos-codigos"), {
                params: { correo: form.correo },
        });
        
        if (data.length === 0) {
            swalWithTailwind.fire({
                title: 'El correo no tiene codigos registrados',
                icon: "warning",
            });
        } else {
            codigos.value = data;
            confirmarModalConsultar.value = true;
        }
    } catch (error) {
        console.error("Error buscando codigo:", error);
    }
};

const closeModal = () => {
    confirmarModalConsultar.value = false;
};

</script>

<template>
    <Head title="Consultar Codigos de Verificacion" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🔎 Consultar Codigos Verificación</h2>
        </template>

        <template #contenido-pagina>       
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Información codigos verificación</h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Datos para consultar los codigos para los correos
                        </p>
                    </header>
                    <div class="mt-6 space-y-6 w-full md:w-1/2">
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
                            <PrimaryButton @click="abrirModalCodigos">
                                {{ $t("Search") }}
                            </PrimaryButton>
                        </div>
                    </div>
                </section>
            </div>

            <Modal :show="confirmarModalConsultar" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Códigos de verificación</h2>

                    <div class="grid grid-cols-2 gap-4 mt-6">
                        <div v-for="(codigo, index) in codigos" :key="index" class="p-2 border rounded-md text-center">
                            <span :class="codigo.respaldo === 1 ? 'text-blue-400' : ''">
                                {{ codigo.codigo }}
                            </span>

                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal">Volver</SecondaryButton>
                    </div>
                </div>
            </Modal>

        </template>
    </LayoutPageHeader>
</template>