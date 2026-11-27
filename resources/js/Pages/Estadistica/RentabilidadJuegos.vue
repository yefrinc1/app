<script setup>
import { computed, ref, watch } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { Bar, Doughnut } from 'vue-chartjs';
import { ArcElement, BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import axios from "axios";

ChartJS.register(ArcElement, BarElement, CategoryScale, Legend, LinearScale, Tooltip);

const searchQuery = ref('');

const props = defineProps({
    juegos: Object,
    search: String,
    modelValue: String,
    resumen: Object,
    graficos: Object,
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

watch(
    () => props.modelValue,
    (nuevoValor) => {
        searchQuery.value = nuevoValor;
    }
);

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
    searchQuery.value = nombreJuego;
    sugerencias.value = [];
    juegoEncontrado.value = true;
    emit("update:modelValue", nombreJuego);
};

const formatoCop = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
};

const formatearFecha = (fecha) => {
    if (!fecha) return 'Sin fecha';

    if (typeof fecha === 'string' && fecha.includes('-')) {
        const soloFecha = fecha.split(' ')[0]; // quita la hora
        const [year, month, day] = soloFecha.split('-');

        return `${day}/${month}/${year.slice(2)}`;
    }

    return fecha;
};

const claseDiferencia = (diferencia) => {
    if (diferencia > 0) {
        return 'bg-green-100 text-green-800';
    }

    if (diferencia < 0) {
        return 'bg-red-100 text-red-800';
    }

    return 'bg-gray-100 text-gray-800';
};

const iconoDiferencia = (diferencia) => {
    if (diferencia > 0) {
        return 'fa-solid fa-arrow-trend-up';
    }

    if (diferencia < 0) {
        return 'fa-solid fa-arrow-trend-down';
    }

    return 'fa-solid fa-minus';
};

const etiquetasComparativo = computed(() => props.graficos.comparativo.map((item) => item.juego));
const comparativoData = computed(() => ({
    labels: etiquetasComparativo.value,
    datasets: [
        { label: 'Invertido', data: props.graficos.comparativo.map((item) => item.comprado), backgroundColor: '#f59e0b', borderRadius: 6 },
        { label: 'Vendido', data: props.graficos.comparativo.map((item) => item.vendido), backgroundColor: '#16a34a', borderRadius: 6 },
    ],
}));
const rentabilidadData = computed(() => ({
    labels: props.graficos.rentabilidad.map((item) => item.juego),
    datasets: [{
        label: 'Utilidad acumulada',
        data: props.graficos.rentabilidad.map((item) => item.diferencia),
        backgroundColor: props.graficos.rentabilidad.map((item) => item.diferencia >= 0 ? '#16a34a' : '#dc2626'),
        borderRadius: 6,
    }],
}));
const distribucionData = computed(() => ({
    labels: ['Rentables', 'En equilibrio', 'Por recuperar'],
    datasets: [{
        data: [props.graficos.distribucion.rentables, props.graficos.distribucion.equilibrio, props.graficos.distribucion.por_recuperar],
        backgroundColor: ['#16a34a', '#94a3b8', '#dc2626'],
    }],
}));
const opcionesGrafico = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { callback: (valor) => `$${Number(valor).toLocaleString('es-CO')}` } } },
};
const opcionesDona = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
</script>

<template>
    <Head title="💹 Rentabilidad Juegos" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                💹 Rentabilidad Juegos
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <!-- Header -->
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">
                                    Estadística de rentabilidad por juego
                                </h2>

                                <p class="text-sm text-gray-500 mt-1">
                                    Consulta cuánto se ha comprado, vendido y la diferencia generada por cada juego.
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-sm text-gray-500">
                                    Juegos encontrados
                                </p>

                                <p class="text-2xl font-bold text-gray-900">
                                    {{ resumen.juegos }}
                                </p>
                            </div>
                        </div>

                        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <article class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                                <p class="text-sm font-semibold text-amber-700">Inversión acumulada</p>
                                <p class="mt-1 text-2xl font-black text-amber-900">${{ formatoCop(resumen.comprado) }}</p>
                            </article>
                            <article class="rounded-xl border border-green-100 bg-green-50 p-4">
                                <p class="text-sm font-semibold text-green-700">Ventas acumuladas</p>
                                <p class="mt-1 text-2xl font-black text-green-900">${{ formatoCop(resumen.vendido) }}</p>
                            </article>
                            <article class="rounded-xl border p-4" :class="resumen.diferencia >= 0 ? 'border-emerald-100 bg-emerald-50' : 'border-red-100 bg-red-50'">
                                <p class="text-sm font-semibold" :class="resumen.diferencia >= 0 ? 'text-emerald-700' : 'text-red-700'">Utilidad acumulada</p>
                                <p class="mt-1 text-2xl font-black" :class="resumen.diferencia >= 0 ? 'text-emerald-900' : 'text-red-900'">${{ formatoCop(resumen.diferencia) }}</p>
                            </article>
                            <article class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                                <p class="text-sm font-semibold text-blue-700">Retorno sobre inversión</p>
                                <p class="mt-1 text-2xl font-black text-blue-900">{{ resumen.roi }}%</p>
                                <p class="mt-1 text-xs text-blue-600">{{ resumen.rentables }} rentables · {{ resumen.por_recuperar }} por recuperar</p>
                            </article>
                        </div>

                        <div class="mb-6 grid gap-5 xl:grid-cols-2">
                            <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                                <h3 class="font-bold text-gray-900">Inversión frente a ventas</h3>
                                <p class="mt-1 text-sm text-gray-500">Los 10 juegos con mayores ventas acumuladas.</p>
                                <div class="mt-4 h-80"><Bar :data="comparativoData" :options="opcionesGrafico" /></div>
                            </article>
                            <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                                <h3 class="font-bold text-gray-900">Juegos con mayor utilidad</h3>
                                <p class="mt-1 text-sm text-gray-500">Diferencia entre lo vendido y lo invertido.</p>
                                <div class="mt-4 h-80"><Bar :data="rentabilidadData" :options="opcionesGrafico" /></div>
                            </article>
                            <article class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm xl:col-span-2">
                                <div class="grid items-center gap-5 md:grid-cols-[minmax(0,1fr)_320px]">
                                    <div>
                                        <h3 class="font-bold text-gray-900">Estado de recuperación</h3>
                                        <p class="mt-1 text-sm leading-6 text-gray-500">Compara cuántos títulos ya recuperaron la inversión, están en equilibrio o todavía tienen saldo por recuperar.</p>
                                    </div>
                                    <div class="h-64"><Doughnut :data="distribucionData" :options="opcionesDona" /></div>
                                </div>
                            </article>
                        </div>

                        <!-- Buscador -->
                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 mb-6">
                            <div class="flex flex-col sm:flex-row gap-3">
                                <div class="relative w-full">
                                    <TextInput
                                        id="juego"
                                        ref="juego"
                                        v-model="searchQuery"
                                        @input="mostrarListaJuegos"
                                        @keyup.enter="buscarJuego"
                                        type="text"
                                        class="w-full"
                                        autocomplete="off"
                                        placeholder="Buscar juego..."
                                    />

                                    <ul
                                        v-if="searchQuery !== '' && sugerencias.length !== 0 && juegoEncontrado == false"
                                        class="absolute bg-white border border-gray-300 w-full mt-1 rounded-md shadow-md z-30 max-h-60 overflow-y-auto"
                                    >
                                        <li
                                            v-for="datosJuego in sugerencias"
                                            :key="datosJuego.id"
                                            @click="seleccionarJuego(datosJuego.juego)"
                                            class="p-3 cursor-pointer bg-white hover:bg-gray-100 text-black text-sm"
                                        >
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-gamepad text-blue-500"></i>
                                                <span>{{ datosJuego.juego }}</span>
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <PrimaryButton
                                    @click="buscarJuego"
                                    class="w-full sm:w-12 h-10 flex items-center justify-center !px-0 !py-0"
                                >
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </PrimaryButton>
                            </div>
                        </div>

                        <!-- Tabla -->
                        <div class="overflow-x-auto shadow rounded-lg">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100 border-b">
                                        <th class="px-4 py-2 text-left">#</th>
                                        <th class="px-4 py-2 text-left">Juego</th>
                                        <th class="px-4 py-2 text-left">Comprado</th>
                                        <th class="px-4 py-2 text-left">Vendido</th>
                                        <th class="px-4 py-2 text-left">Diferencia</th>
                                        <th class="px-4 py-2 text-left">ROI</th>
                                        <th class="px-4 py-2 text-left">Fecha mínima</th>
                                        <th class="px-4 py-2 text-left">Fecha máxima</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="(juego, i) in props.juegos.data"
                                        :key="juego.id"
                                        class="border-b hover:bg-gray-100"
                                    >
                                        <td class="px-4 py-2">
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-sm font-bold">
                                                #{{ i + 1 }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <div class="bg-gray-50 rounded-lg p-2 min-w-[260px]">
                                                <div class="flex items-center gap-2 text-md">
                                                    <i class="fa-solid fa-gamepad text-blue-500"></i>

                                                    <span class="font-bold text-gray-900">
                                                        {{ juego.juego }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-3 py-1 rounded-lg text-sm font-bold">
                                                <i class="fa-solid fa-cart-shopping"></i>
                                                ${{ formatoCop(juego.total_comprado) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-green-100 text-green-800 px-3 py-1 rounded-lg text-sm font-bold">
                                                <i class="fa-solid fa-money-bill-wave"></i>
                                                ${{ formatoCop(juego.total_vendido) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold',
                                                    claseDiferencia(juego.diferencia)
                                                ]"
                                            >
                                                <i :class="iconoDiferencia(juego.diferencia)"></i>
                                                ${{ formatoCop(juego.diferencia) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span :class="juego.roi >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="inline-flex rounded-lg px-3 py-1 text-sm font-bold">
                                                {{ juego.roi }}%
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap min-w-[110px] justify-center">
                                                <i class="fa-regular fa-calendar"></i>
                                                {{ formatearFecha(juego.fecha_minima) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap min-w-[110px] justify-center">
                                                <i class="fa-regular fa-calendar"></i>
                                                {{ formatearFecha(juego.fecha_maxima) }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="props.juegos.data.length === 0">
                                        <td class="px-4 py-10 text-center" colspan="8">
                                            <div class="flex flex-col items-center gap-3">
                                                <div class="w-16 h-16 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                                                    <i class="fa-solid fa-gamepad text-2xl"></i>
                                                </div>

                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">
                                                        No hay juegos registrados
                                                    </h3>

                                                    <p class="text-sm text-gray-500">
                                                        No se encontraron estadísticas con el filtro aplicado.
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
                                    v-for="(link, index) in props.juegos.links"
                                    :key="index"
                                >
                                    <button
                                        v-if="link.url"
                                        @click.prevent="irAPagina(link.url + '&search=' + encodeURIComponent(searchQuery))"
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
