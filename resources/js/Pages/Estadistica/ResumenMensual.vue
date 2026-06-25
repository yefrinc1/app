<script setup>
import { defineProps } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';

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

const formatoCop = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
};

const claseUtilidad = (valor) => {
    if (Number(valor) > 0) {
        return 'bg-green-100 text-green-800';
    }

    if (Number(valor) < 0) {
        return 'bg-red-100 text-red-800';
    }

    return 'bg-gray-100 text-gray-800';
};

const iconoUtilidad = (valor) => {
    if (Number(valor) > 0) {
        return 'fa-solid fa-arrow-trend-up';
    }

    if (Number(valor) < 0) {
        return 'fa-solid fa-arrow-trend-down';
    }

    return 'fa-solid fa-minus';
};

const clasePorcentaje = (valor) => {
    if (Number(valor) >= 30) {
        return 'bg-green-100 text-green-800';
    }

    if (Number(valor) >= 15) {
        return 'bg-yellow-100 text-yellow-800';
    }

    if (Number(valor) > 0) {
        return 'bg-orange-100 text-orange-800';
    }

    return 'bg-red-100 text-red-800';
};

const totalIngresos = () => {
    return props.resumen_mensual.data.reduce((total, resumen) => {
        return total + Number(resumen.ingresos || 0);
    }, 0);
};

const totalEgresos = () => {
    return props.resumen_mensual.data.reduce((total, resumen) => {
        return total + Number(resumen.egresos || 0);
    }, 0);
};

const totalUtilidad = () => {
    return props.resumen_mensual.data.reduce((total, resumen) => {
        return total + Number(resumen.utilidad_neta || 0);
    }, 0);
};

const totalVentas = () => {
    return props.resumen_mensual.data.reduce((total, resumen) => {
        return total + Number(resumen.cantidad || 0);
    }, 0);
};
</script>

<template>
    <Head title="📝 Resumen Mensual" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📝 Resumen Mensual
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <!-- Header -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">
                                    Resumen mensual del negocio
                                </h2>

                                <p class="text-sm text-gray-500 mt-1">
                                    Consulta ventas, ingresos, egresos, utilidad neta, margen de ganancia y ROI por periodo.
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-sm text-gray-500">
                                    Periodos mostrados
                                </p>

                                <p class="text-2xl font-bold text-gray-900">
                                    {{ props.resumen_mensual.data.length }}
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Cards resumen -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">
                                    Ventas
                                </p>

                                <p class="text-2xl font-bold text-gray-900">
                                    {{ totalVentas() }}
                                </p>
                            </div>

                            <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                                <i class="fa-solid fa-cart-shopping text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">
                                    Ingresos
                                </p>

                                <p class="text-2xl font-bold text-green-700">
                                    ${{ formatoCop(totalIngresos()) }}
                                </p>
                            </div>

                            <div class="w-12 h-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                <i class="fa-solid fa-arrow-trend-up text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">
                                    Egresos
                                </p>

                                <p class="text-2xl font-bold text-red-700">
                                    ${{ formatoCop(totalEgresos()) }}
                                </p>
                            </div>

                            <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center">
                                <i class="fa-solid fa-arrow-trend-down text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">
                                    Utilidad neta
                                </p>

                                <p
                                    class="text-2xl font-bold"
                                    :class="totalUtilidad() >= 0 ? 'text-green-700' : 'text-red-700'"
                                >
                                    ${{ formatoCop(totalUtilidad()) }}
                                </p>
                            </div>

                            <div
                                class="w-12 h-12 rounded-full flex items-center justify-center"
                                :class="totalUtilidad() >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
                            >
                                <i
                                    class="text-xl"
                                    :class="totalUtilidad() >= 0 ? 'fa-solid fa-sack-dollar' : 'fa-solid fa-triangle-exclamation'"
                                ></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    Detalle mensual
                                </h3>

                                <p class="text-sm text-gray-500">
                                    Información consolidada por cada periodo registrado.
                                </p>
                            </div>

                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold w-fit">
                                {{ props.resumen_mensual.data.length }} registros
                            </span>
                        </div>

                        <div class="overflow-x-auto shadow rounded-lg">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100 border-b">
                                        <th class="px-4 py-2 text-left">Periodo</th>
                                        <th class="px-4 py-2 text-left">Cantidad</th>
                                        <th class="px-4 py-2 text-left">Ingresos</th>
                                        <th class="px-4 py-2 text-left">Egresos</th>
                                        <th class="px-4 py-2 text-left">Utilidad neta</th>
                                        <th class="px-4 py-2 text-left">Margen</th>
                                        <th class="px-4 py-2 text-left">ROI</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="resumen in props.resumen_mensual.data"
                                        :key="resumen.id"
                                        class="border-b hover:bg-gray-100"
                                    >
                                        <td class="px-4 py-2">
                                            <div class="bg-gray-50 rounded-lg p-2 min-w-[140px]">
                                                <div class="flex items-center gap-2">
                                                    <i class="fa-regular fa-calendar text-blue-500"></i>

                                                    <span class="font-bold text-gray-900">
                                                        {{ resumen.periodo }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-3 py-1 rounded-lg text-sm font-bold">
                                                <i class="fa-solid fa-cart-shopping"></i>
                                                {{ resumen.cantidad }} ventas
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-gradient-to-r from-green-100 to-green-200 text-green-900 px-3 py-1 rounded-lg text-sm font-bold shadow-sm">
                                                <i class="fa-solid fa-arrow-trend-up"></i>
                                                ${{ formatoCop(resumen.ingresos) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-gradient-to-r from-red-100 to-red-200 text-red-900 px-3 py-1 rounded-lg text-sm font-bold shadow-sm">
                                                <i class="fa-solid fa-arrow-trend-down"></i>
                                                ${{ formatoCop(resumen.egresos) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold',
                                                    claseUtilidad(resumen.utilidad_neta)
                                                ]"
                                            >
                                                <i :class="iconoUtilidad(resumen.utilidad_neta)"></i>
                                                ${{ formatoCop(resumen.utilidad_neta) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap',
                                                    clasePorcentaje(resumen.margen_ganancia)
                                                ]"
                                            >
                                                <i class="fa-solid fa-percent"></i>
                                                {{ resumen.margen_ganancia }}%
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap',
                                                    clasePorcentaje(resumen.roi)
                                                ]"
                                            >
                                                <i class="fa-solid fa-chart-line"></i>
                                                {{ resumen.roi }}%
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="props.resumen_mensual.data.length === 0">
                                        <td class="px-4 py-10 text-center" colspan="7">
                                            <div class="flex flex-col items-center gap-3">
                                                <div class="w-16 h-16 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                                                    <i class="fa-solid fa-chart-pie text-2xl"></i>
                                                </div>

                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">
                                                        No hay resúmenes registrados
                                                    </h3>

                                                    <p class="text-sm text-gray-500">
                                                        Todavía no existen registros de resumen mensual.
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="mt-4">
                            <div class="flex flex-wrap justify-center gap-2">
                                <template
                                    v-for="(link, index) in props.resumen_mensual.links"
                                    :key="index"
                                >
                                    <button
                                        v-if="link.url"
                                        @click.prevent="irAPagina(link.url)"
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
            </div>
        </template>
    </LayoutPageHeader>
</template>