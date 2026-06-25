<script setup>
import { defineProps } from "vue";
import { Link, Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { ref, watch } from "vue";
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const searchQuery = ref('');

const props = defineProps({
    correos: Object,
    search: String,
    modelValue: String,
});

const form = useForm({});

searchQuery.value = props.search == null ? '' : props.search;
const sugerencias = ref([]);
const juegoEncontrado = ref();
const emit = defineEmits(["update:modelValue"]);

const buscarJuego = () => {
    form.get(route('consultar-inventario', { search: searchQuery.value }), {
        preserveScroll: true,
    });
};

// Observa cambios en modelValue y actualiza searchQuery
watch(
    () => props.modelValue,
    (nuevoValor) => {
        searchQuery.value = nuevoValor;
    }
);

// Función para buscar correos en el backend
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

    <Head title="🧰 Consultar Inventario" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🧰 Consultar Inventario</h2>
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
                                    <th class="px-4 py-2 text-left">Cuenta</th>
                                    <th class="px-4 py-2 text-left">Juego</th>
                                    <th class="px-4 py-2 text-left">Licencias</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(correo, i) in props.correos.data" :key="correo.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 text-gray-700 text-sm font-bold">
                                            {{ i + 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="bg-gray-50 rounded-lg p-2 min-w-[220px]">
                                            <div class="flex items-center gap-2 text-md">
                                                <i class="fa-solid fa-envelope text-blue-500"></i>
                                                <span class="truncate">{{ correo.correo }}</span>
                                            </div>

                                            <div class="flex items-center gap-2 text-md text-gray-600 mt-1">
                                                <i class="fa-solid fa-key text-amber-500"></i>
                                                <span>{{ correo.contrasena }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="bg-gray-50 rounded-lg p-2 min-w-[220px]">
                                            <div class="flex items-center gap-2 text-md">
                                                <i class="fa-solid fa-gamepad text-purple-500"></i>

                                                <span class="font-semibold text-gray-900">
                                                    {{ correo.juego }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            <span class="inline-flex items-center bg-gradient-to-r from-blue-100 to-blue-200 text-blue-900 px-3 py-1 rounded-lg text-sm font-bold shadow-sm">
                                                PS4: {{ correo.primaria_ps4 }}
                                            </span>

                                            <span class="inline-flex items-center bg-gradient-to-r from-indigo-100 to-indigo-200 text-indigo-900 px-3 py-1 rounded-lg text-sm font-bold shadow-sm">
                                                PS5: {{ correo.primaria_ps5 }}
                                            </span>

                                            <span class="inline-flex items-center bg-gradient-to-r from-green-100 to-green-200 text-green-900 px-3 py-1 rounded-lg text-sm font-bold shadow-sm">
                                                SEC: {{ correo.secundaria }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="props.correos.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="4">
                                        No hay juegos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="mt-4">
                        <div class="flex flex-wrap justify-center gap-2">
                            <template v-for="(link, index) in props.correos.links" :key="index">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    v-html="link.label"
                                    class="px-3 py-2 border rounded-md text-xs font-semibold transition ease-in-out duration-150"
                                    :class="link.active
                                        ? 'bg-gray-800 text-white border-gray-800'
                                        : 'bg-gray-200 text-gray-700 border-transparent hover:bg-gray-300'"
                                />
                            </template>
                        </div>
                    </div>

                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>
