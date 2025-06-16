<script setup>
import { defineProps } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { ref, watch } from "vue";
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const searchQuery = ref('');

const props = defineProps({
    juegos: Object,
    search: String,
    modelValue: String,
});

const form = useForm({});
searchQuery.value = props.search == null ? '' : props.search;
const sugerencias = ref([]);
const juegoEncontrado = ref();
const emit = defineEmits(["update:modelValue"]);

const buscarJuego = () => {
    form.get(route('estadistica-juegos', { search: searchQuery.value }), {
        preserveScroll: true,
    });
};

const irAPagina = (url) => {
    form.get(url, {
        preserveScroll: false,
        preserveState: true,
    });
};

// Observa cambios en modelValue y actualiza searchQuery
watch(
    () => props.modelValue,
    (nuevoValor) => {
        searchQuery.value = nuevoValor;
    }
);


// Función para buscar juegos en el backend
const mostrarListaJuegos = async () => {
    juegoEncontrado.value = false;

    if (searchQuery.value.length < 1) {
        sugerencias.value = [];
        return;
    }

    try {
        const { data } = await axios.get(route("buscar-juegos"), {
            params: { juego: searchQuery.value },
        });

        sugerencias.value = data;

        if (sugerencias.value.length != 0) {
            if (sugerencias.value[0]['juego'] == searchQuery.value) {
                juegoEncontrado.value = true;
            }
        }
    } catch (error) {
        console.error("Error buscando juegos:", error);
    }
};

const seleccionarJuego = async (nombreJuego) => {
    searchQuery.value = nombreJuego; // Asigna el valor seleccionado
    sugerencias.value = []; // Oculta la lista
    juegoEncontrado.value = true;
    emit("update:modelValue", nombreJuego); // Actualiza el v-model
};

</script>

<template>

    <Head title="💹 Rentabilidad Juegos" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">💹 Rentabilidad Juegos</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <!-- Filtro de búsqueda -->
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex space-x-4 w-full">
                            <div class="relative w-full md:w-1/2">
                                <TextInput
                                    id="juego"
                                    ref="juego"
                                    v-model="searchQuery"
                                    @input="mostrarListaJuegos"
                                    type="text"
                                    class="mt-1 w-full"
                                    autocomplete="off"
                                    placeholder="Buscar juego..."
                                />

                                <ul
                                    v-if="form.juego !== '' && sugerencias.length !== 0 && juegoEncontrado == false"
                                    class="absolute bg-white border border-gray-300 w-full mt-1 rounded-md shadow-md z-10"
                                >
                                    <li
                                        v-for="datosJuego in sugerencias"
                                        :key="datosJuego.id"
                                        @click="seleccionarJuego(datosJuego.juego)"
                                        class="p-2 cursor-pointer bg-white hover:bg-gray-100 text-black"
                                    >
                                        {{ datosJuego.juego }}
                                    </li>
                                </ul>
                            </div>
                            <PrimaryButton @click="buscarJuego">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </PrimaryButton>
                        </div>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">#</th>
                                    <th class="px-4 py-2 text-left">Juego</th>
                                    <th class="px-4 py-2 text-left">Comprado</th>
                                    <th class="px-4 py-2 text-left">Vendido</th>
                                    <th class="px-4 py-2 text-left">Diferencia</th>
                                    <th class="px-4 py-2 text-left">Fecha Minima</th>
                                    <th class="px-4 py-2 text-left">Fecha Maxima</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(juego, i) in props.juegos.data" :key="juego.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ i + 1 }}</td>
                                    <td class="px-4 py-2">{{ juego.juego }}</td>
                                    <td class="px-4 py-2">{{ juego.total_comprado }}</td>
                                    <td class="px-4 py-2">{{ juego.total_vendido }}</td>
                                    <td 
                                        class="px-4 py-2" 
                                        :class="{
                                            'text-green-600': juego.diferencia > 0,
                                            'text-red-600': juego.diferencia < 0
                                        }"
                                        >
                                        {{ juego.diferencia }}
                                    </td>
                                    <td class="px-4 py-2">{{ juego.fecha_minima }}</td>
                                    <td class="px-4 py-2">{{ juego.fecha_maxima }}</td>
                                </tr>
                                <tr v-if="props.juegos.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="12">
                                        No hay juegos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="mt-4">
                        <div class="flex justify-center space-x-2">
                            <template v-for="(link, index) in props.juegos.links" :key="index">

                            <button v-if="link.url"
                                @click.prevent="irAPagina(link.url + '&search=' + encodeURIComponent(searchQuery))"
                                v-html="link.label"
                                class="px-3 py-1 border rounded-md"
                                :class="link.active
                                ? 'px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150'
                                : 'px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 transition ease-in-out duration-150'"
                            />

                            </template>
                        </div>
                    </div>

                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>
