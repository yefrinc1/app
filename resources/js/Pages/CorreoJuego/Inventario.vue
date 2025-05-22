<script setup>
import { defineProps } from "vue";
import { Link, Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { ref } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const searchQuery = ref('');

const props = defineProps({
    correos: Object,
    search: String,
});

const form = useForm({});

searchQuery.value = props.search;

const buscarJuego = () => {
    form.get(route('consultar-inventario', { search: searchQuery.value }), {
        preserveScroll: true,
    });
};

const irAPagina = (url) => {
    form.get(url, {
        preserveScroll: false,
        preserveState: true,
    });
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
                        <div class="flex space-x-4 w-3/6">
                            <TextInput v-model="searchQuery" type="text" placeholder="Buscar juego..." class="block w-full" />
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
                                    <th class="px-4 py-2 text-left">Correo</th>
                                    <th class="px-4 py-2 text-left">Contraseña</th>
                                    <th class="px-4 py-2 text-left">Juego</th>
                                    <th class="px-4 py-2 text-left">Primaria PS4</th>
                                    <th class="px-4 py-2 text-left">Primaria PS5</th>
                                    <th class="px-4 py-2 text-left">Secundaria</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(correo, i) in props.correos.data" :key="correo.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ i + 1 }}</td>
                                    <td class="px-4 py-2">{{ correo.correo }}</td>
                                    <td class="px-4 py-2">{{ correo.contrasena }}</td>
                                    <td class="px-4 py-2">{{ correo.juego }}</td>
                                    <td class="px-4 py-2">{{ correo.primaria_ps4 }}</td>
                                    <td class="px-4 py-2">{{ correo.primaria_ps5 }}</td>
                                    <td class="px-4 py-2">{{ correo.secundaria }}</td>
                                </tr>
                                <tr v-if="props.correos.data.length === 0">
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
                            <template v-for="(link, index) in props.correos.links" :key="index">

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
