<script setup>
import { defineProps } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { ref, watch } from "vue";
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    resumen_mensual: Object,
});

const form = useForm({});

const irAPagina = (url) => {
    form.get(url, {
        preserveScroll: false,
        preserveState: true,
    });
};

</script>

<template>

    <Head title="📝 Resumen Mensual" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">📝 Resumen Mensual</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Periodo</th>
                                    <th class="px-4 py-2 text-left">Cantidad</th>
                                    <th class="px-4 py-2 text-left">Ingresos</th>
                                    <th class="px-4 py-2 text-left">Egresos</th>
                                    <th class="px-4 py-2 text-left">Utilidad Neta</th>
                                    <th class="px-4 py-2 text-left">Margen Ganancia</th>
                                    <th class="px-4 py-2 text-left">ROI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(resumen, i) in props.resumen_mensual.data" :key="resumen.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ resumen.periodo }}</td>
                                    <td class="px-4 py-2">{{ resumen.cantidad }}</td>
                                    <td class="px-4 py-2">{{ resumen.ingresos }}</td>
                                    <td class="px-4 py-2">{{ resumen.egresos }}</td>
                                    <td class="px-4 py-2">{{ resumen.utilidad_neta }}</td>
                                    <td class="px-4 py-2">{{ resumen.margen_ganancia }}%</td>
                                    <td class="px-4 py-2">{{ resumen.roi }}%</td>
                                </tr>
                                <tr v-if="props.resumen_mensual.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="12">
                                        No hay resumen registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="mt-4">
                        <div class="flex justify-center space-x-2">
                            <template v-for="(link, index) in props.resumen_mensual.links" :key="index">

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
