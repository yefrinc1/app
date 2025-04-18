<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { ref, watch } from "vue";
import axios from "axios";

const props = defineProps({
    modelValue: String,
    resultado_consulta: Object,
    filtros: Object,
});

const form = useForm({
    juego: "",
    cliente: "",
    correo: "",
    fecha: "",
});

// Emitir cambios al padre
const emit = defineEmits(["update:modelValue"]);

// Variables reactivas
form.juego = ref(props.modelValue || "");
const sugerencias = ref([]);
const juegoEncontrado = ref();
const sectionConsultar = ref(false);

if (props.resultado_consulta && props.resultado_consulta.length > 0) {
    // Código para manejar cuando hay resultados
    form.juego = props.filtros.juego
    form.cliente = props.filtros.cliente
    form.correo = props.filtros.correo
    form.fecha = props.filtros.fecha
    sectionConsultar.value = true;
    window.location.href = '#section-resultado';
}

const formatFecha = (fecha) => {
  const date = new Date(fecha);
  return new Intl.DateTimeFormat('es-CO', {
    day: '2-digit',
    month: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
  }).format(date);
};

// Observa cambios en modelValue y actualiza searchQuery
watch(
    () => props.modelValue,
    (nuevoValor) => {
        form.juego = nuevoValor;
    }
);

// Función para buscar correos en el backend
const buscarJuegos = async () => {
    form.errors.juego = '';
    juegoEncontrado.value = false;

    if (form.juego.length < 1) {
        sugerencias.value = [];
        return;
    }

    try {
        const { data } = await axios.get(route("buscar-juegos"), {
            params: { juego: form.juego },
        });

        sugerencias.value = data;

        if (sugerencias.value.length == 0) {
            form.errors.juego = 'No se encontró ningún juego';
        } else {
            if (sugerencias.value[0]['juego'] == form.juego) {
                juegoEncontrado.value = true;
            }
        }
    } catch (error) {
        console.error("Error buscando juegos:", error);
    }
};

// Función para seleccionar un correo
const seleccionarJuego = async (nombreJuego) => {
    form.juego = nombreJuego; // Asigna el valor seleccionado
    sugerencias.value = []; // Oculta la lista
    juegoEncontrado.value = true;
    emit("update:modelValue", nombreJuego); // Actualiza el v-model
};

</script>

<template>
    <Head title="🛍️ Consultar Ventas" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🛍️ Consultar Ventas</h2>
        </template>

        <template #contenido-pagina>       
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Información de las ventas</h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Datos para consultar una venta
                        </p>
                    </header>
                    <form @submit.prevent="form.get(route('ventas.index'))" class="mt-6 space-y-6 w-full md:w-1/2">
                        <div>
                            <InputLabel for="juego" value="Juego" />

                            <div class="relative">
                                <TextInput
                                    id="juego"
                                    ref="juego"
                                    v-model="form.juego"
                                    @input="buscarJuegos"
                                    type="text"
                                    class="mt-1 w-full"
                                    autocomplete="off"
                                />

                                <ul
                                    v-if="form.juego !== '' && sugerencias.length !== 0 && juegoEncontrado == false"
                                    class="absolute bg-white border border-gray-300 w-full mt-1 rounded-md shadow-md"
                                >
                                    <li
                                        v-for="Datosjuego in sugerencias"
                                        :key="Datosjuego.id"
                                        @click="seleccionarJuego(Datosjuego.juego)"
                                        class="p-2 cursor-pointer bg-white hover:bg-gray-100 text-black"
                                    >
                                        {{ Datosjuego.juego }}
                                    </li>
                                </ul>
                            </div>
                        
                            <InputError class="mt-2" :message="form.errors.juego" />
                        </div>

                        <div>
                            <InputLabel for="cliente" value="Cliente" />

                            <div class="relative w-full">
                                <TextInput
                                    id="cliente"
                                    ref="cliente"
                                    v-model="form.cliente"
                                    type="text"
                                    class="mt-1 w-full"
                                    autocomplete="off"
                                />
                            </div>
                        
                            <InputError class="mt-2" :message="form.errors.cliente" />
                        </div>

                        <div>
                            <InputLabel for="correo" value="Correo" />

                            <div class="relative w-full">
                                <TextInput
                                    id="correo"
                                    ref="correo"
                                    v-model="form.correo"
                                    type="text"
                                    class="mt-1 w-full"
                                    autocomplete="off"
                                />
                            </div>
                        
                            <InputError class="mt-2" :message="form.errors.correo" />
                        </div>

                        <div>
                            <InputLabel for="fecha" value="Fecha" />

                            <div class="relative w-full">
                                <TextInput
                                    id="fecha"
                                    ref="fecha"
                                    v-model="form.fecha"
                                    type="date"
                                    class="mt-1 w-full"
                                    autocomplete="off"
                                />
                            </div>
                        
                            <InputError class="mt-2" :message="form.errors.fecha" />
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton>Consultar ventas</PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>

            <div v-if="sectionConsultar" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg" id="section-resultado">
                <section>
                    <h2 class="text-lg font-medium text-gray-900 mb-6">
                        Resultado de las ventas ({{ resultado_consulta.length }})
                    </h2>
                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">#</th>
                                    <th class="px-4 py-2 text-left">Fecha</th>
                                    <th class="px-4 py-2 text-left">Juego</th>
                                    <th class="px-4 py-2 text-left">Correo</th>
                                    <th class="px-4 py-2 text-left">Cliente</th>
                                    <th class="px-4 py-2 text-center">Cuenta</th>
                                    <th class="px-4 py-2 text-center">Consola</th>
                                    <th class="px-4 py-2 text-center">Forma Pago</th>
                                    <th class="px-4 py-2 text-center">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(venta, i)  in resultado_consulta" :key="venta.id" class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ i + 1 }}</td>
                                    <td class="px-4 py-2">{{ formatFecha(venta.created_at) }}</td>
                                    <td class="px-4 py-2">{{ venta.correo_juego.juego }}</td>
                                    <td class="px-4 py-2">{{ venta.correo_juego.correo }}</td>
                                    <td class="px-4 py-2">{{ venta.cliente }}</td>
                                    <td class="px-4 py-2">{{ venta.tipo_cuenta }}</td>
                                    <td class="px-4 py-2">{{ venta.consola }}</td>
                                    <td class="px-4 py-2">{{ venta.medio_pago }}</td>
                                    <td class="px-4 py-2">{{ venta.precio }}</td>
                                </tr>
                                <!-- Si no hay datos en la consulta -->
                                <tr v-if="resultado_consulta.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="8">
                                        No hay ventas registradas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>