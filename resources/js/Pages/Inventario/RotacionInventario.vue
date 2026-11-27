<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bar, Doughnut } from 'vue-chartjs';
import { ArcElement, BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip } from 'chart.js';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import FormSelect from '@/Pages/Pedidos/Components/FormSelect.vue';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';
import SectionCard from '@/Pages/Pedidos/Components/SectionCard.vue';

ChartJS.register(ArcElement, BarElement, CategoryScale, Legend, LinearScale, Tooltip);

const props = defineProps({
    resultados: Object,
    resumen: Object,
    graficos: Object,
    periodo: Object,
    filtros: Object,
});

const form = reactive({
    periodo: props.filtros.periodo ?? '90',
    juego: props.filtros.juego ?? '',
    estado: props.filtros.estado ?? '',
    orden: props.filtros.orden ?? 'valor_inventario',
    direccion: props.filtros.direccion ?? 'desc',
});

const estados = {
    alta: { texto: 'Rotación alta', clase: 'bg-green-100 text-green-800 ring-green-200' },
    media: { texto: 'Rotación media', clase: 'bg-blue-100 text-blue-800 ring-blue-200' },
    lenta: { texto: 'Rotación lenta', clase: 'bg-amber-100 text-amber-800 ring-amber-200' },
    detenida: { texto: 'Inventario detenido', clase: 'bg-red-100 text-red-800 ring-red-200' },
    nuevo: { texto: 'Inventario nuevo', clase: 'bg-purple-100 text-purple-800 ring-purple-200' },
};
const estadoInfo = (estado) => estados[estado] ?? estados.detenida;
const moneda = (valor) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(valor || 0));
const fecha = (valor) => valor ? new Date(valor).toLocaleDateString('es-CO', { dateStyle: 'medium' }) : 'Sin ventas';
const cobertura = (valor) => valor === null ? 'Sin salida en el período' : `${valor} días`;

const params = () => Object.fromEntries(Object.entries(form).filter(([, valor]) => valor !== '' && valor !== null));
let temporizador;
const consultar = (inmediato = false) => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => router.get(route('inventario.rotacion.index'), params(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    }), inmediato ? 0 : 350);
};
const limpiar = () => {
    Object.assign(form, { periodo: '90', juego: '', estado: '', orden: 'valor_inventario', direccion: 'desc' });
    consultar(true);
};
const ordenar = (columna) => {
    form.direccion = form.orden === columna && form.direccion === 'desc' ? 'asc' : 'desc';
    form.orden = columna;
    consultar(true);
};
const iconoOrden = (columna) => form.orden === columna ? (form.direccion === 'asc' ? '↑' : '↓') : '↕';
const exportar = () => {
    window.location.href = route('inventario.rotacion.exportar', params());
};

const coloresEstado = { alta: '#16a34a', media: '#2563eb', lenta: '#f59e0b', detenida: '#dc2626', nuevo: '#9333ea' };
const capitalEstadoData = computed(() => ({
    labels: props.graficos.capital_estado.map((item) => estadoInfo(item.estado).texto),
    datasets: [{
        data: props.graficos.capital_estado.map((item) => item.valor),
        backgroundColor: props.graficos.capital_estado.map((item) => coloresEstado[item.estado]),
    }],
}));
const capitalDetenidoData = computed(() => ({
    labels: props.graficos.capital_detenido.map((item) => item.juego),
    datasets: [{
        label: 'Valor en inventario',
        data: props.graficos.capital_detenido.map((item) => item.valor),
        backgroundColor: props.graficos.capital_detenido.map((item) => coloresEstado[item.estado]),
        borderRadius: 6,
    }],
}));
const ventasInventarioData = computed(() => ({
    labels: props.graficos.ventas_inventario.map((item) => item.juego),
    datasets: [
        { label: 'Ventas', data: props.graficos.ventas_inventario.map((item) => item.ventas), backgroundColor: '#dc2626', borderRadius: 5 },
        { label: 'Cupos actuales', data: props.graficos.ventas_inventario.map((item) => item.cupos), backgroundColor: '#f59e0b', borderRadius: 5 },
    ],
}));
const opcionesGrafico = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } };
const opcionesDineroHorizontal = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, ticks: { callback: (valor) => `$${Number(valor).toLocaleString('es-CO')}` } } },
};
const opcionesDona = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
</script>

<template>
    <Head title="Rotación de inventario" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">🔄 Rotación de inventario</h2>
                <p class="mt-1 text-sm font-normal text-gray-500">Identifica inventario con salida rápida, lenta o detenido y el capital comprometido.</p>
            </div>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <SectionCard title="Filtros de rotación" description="Compara las ventas del período con los cupos disponibles actualmente." icon="fa-solid fa-filter text-red-500">
                    <template #actions>
                        <div class="rounded-xl border border-red-100 bg-gradient-to-r from-white via-red-50 to-amber-50 px-4 py-3">
                            <p class="text-sm text-gray-500">Período analizado</p>
                            <p class="font-bold text-gray-900">{{ periodo.desde }} — {{ periodo.hasta }}</p>
                        </div>
                    </template>
                    <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="consultar(true)">
                        <FormSelect id="periodo-rotacion" v-model="form.periodo" label="Período de ventas" icon="fa-solid fa-calendar-days" @change="consultar(true)">
                            <option value="30">Últimos 30 días</option><option value="60">Últimos 60 días</option><option value="90">Últimos 90 días</option><option value="180">Últimos 180 días</option><option value="365">Último año</option>
                        </FormSelect>
                        <FormSelect id="estado-rotacion" v-model="form.estado" label="Clasificación" icon="fa-solid fa-gauge-high" @change="consultar(true)">
                            <option value="">Todas las clasificaciones</option><option v-for="(info, clave) in estados" :key="clave" :value="clave">{{ info.texto }}</option>
                        </FormSelect>
                        <div><InputLabel for="juego-rotacion" value="Videojuego" /><TextInput id="juego-rotacion" v-model="form.juego" class="mt-1 block w-full" placeholder="Buscar título..." @input="consultar()" /></div>
                        <div class="flex flex-col gap-3 sm:flex-row lg:col-span-3">
                            <PrimaryButton type="submit" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-magnifying-glass mr-2"></i>Consultar</PrimaryButton>
                            <SecondaryButton type="button" class="w-full justify-center sm:w-auto" @click="limpiar"><i class="fa-solid fa-eraser mr-2"></i>Limpiar</SecondaryButton>
                            <SecondaryButton type="button" class="w-full justify-center sm:ml-auto sm:w-auto" @click="exportar"><i class="fa-solid fa-file-csv mr-2"></i>Exportar CSV</SecondaryButton>
                        </div>
                    </form>
                </SectionCard>

                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-xl border border-gray-100 bg-white p-5 shadow"><p class="text-sm text-gray-500">Valor del inventario</p><p class="mt-1 text-2xl font-black text-gray-900">{{ moneda(resumen.valor_inventario) }}</p><p class="mt-1 text-xs text-gray-400">{{ resumen.cuentas }} cuentas · {{ resumen.cupos }} cupos</p></article>
                    <article class="rounded-xl border border-red-100 bg-red-50 p-5"><p class="text-sm font-semibold text-red-700">Capital en riesgo</p><p class="mt-1 text-2xl font-black text-red-900">{{ moneda(resumen.capital_riesgo) }}</p><p class="mt-1 text-xs text-red-600">Inventario lento o detenido</p></article>
                    <article class="rounded-xl border border-amber-100 bg-amber-50 p-5"><p class="text-sm font-semibold text-amber-700">Inventarios detenidos</p><p class="mt-1 text-2xl font-black text-amber-900">{{ resumen.detenidos }}</p><p class="mt-1 text-xs text-amber-600">Sin salida o con más de 120 días de cobertura</p></article>
                    <article class="rounded-xl border border-green-100 bg-green-50 p-5"><p class="text-sm font-semibold text-green-700">Rotación alta</p><p class="mt-1 text-2xl font-black text-green-900">{{ resumen.rotacion_alta }}</p><p class="mt-1 text-xs text-green-600">Hasta 30 días de cobertura</p></article>
                </section>

                <section class="grid gap-5 xl:grid-cols-2">
                    <SectionCard title="Capital por clasificación" description="Distribución del valor actual del inventario." icon="fa-solid fa-chart-pie text-red-500"><div class="mx-auto h-72 max-w-md"><Doughnut :data="capitalEstadoData" :options="opcionesDona" /></div></SectionCard>
                    <SectionCard title="Mayor capital con salida lenta" description="Juegos lentos o detenidos que concentran más dinero." icon="fa-solid fa-money-bill-trend-up text-amber-500"><div class="h-72"><Bar :data="capitalDetenidoData" :options="opcionesDineroHorizontal" /></div></SectionCard>
                    <SectionCard title="Ventas frente a inventario" description="Top de ventas comparado con los cupos que quedan disponibles." icon="fa-solid fa-scale-balanced text-red-500" class="xl:col-span-2"><div class="h-80"><Bar :data="ventasInventarioData" :options="opcionesGrafico" /></div></SectionCard>
                </section>

                <section class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                    <p class="font-black"><i class="fa-solid fa-circle-info mr-2"></i>Cómo se clasifica</p>
                    <p class="mt-1">Alta: hasta 30 días de cobertura · Media: 31–60 · Lenta: 61–120 · Detenida: más de 120 días o sin ventas · Nuevo: sin ventas y con antigüedad máxima de 30 días.</p>
                </section>

                <SectionCard title="Detalle de rotación" description="Ordena los resultados para encontrar inventario detenido o capital inmovilizado." icon="fa-solid fa-table-list text-red-500">
                    <div class="overflow-x-auto">
                        <table class="min-w-[1450px] w-full border border-gray-200 text-sm">
                            <thead class="bg-gray-100 text-left text-xs uppercase tracking-wide text-gray-600"><tr class="border-b">
                                <th class="px-4 py-3">Videojuego</th>
                                <th class="cursor-pointer px-3 py-3" @click="ordenar('cuentas')">Inventario {{ iconoOrden('cuentas') }}</th>
                                <th class="cursor-pointer px-3 py-3" @click="ordenar('ventas')">Ventas {{ iconoOrden('ventas') }}</th>
                                <th class="px-3 py-3">Velocidad</th>
                                <th class="cursor-pointer px-3 py-3" @click="ordenar('cobertura')">Cobertura {{ iconoOrden('cobertura') }}</th>
                                <th class="cursor-pointer px-3 py-3" @click="ordenar('antiguedad_promedio')">Antigüedad {{ iconoOrden('antiguedad_promedio') }}</th>
                                <th class="px-3 py-3">Última venta</th>
                                <th class="cursor-pointer px-3 py-3" @click="ordenar('valor_inventario')">Capital {{ iconoOrden('valor_inventario') }}</th>
                                <th class="px-3 py-3">Clasificación</th><th class="px-3 py-3"></th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="fila in resultados.data" :key="fila.juego" class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><div class="flex min-w-[250px] items-center gap-3"><img v-if="fila.imagen" :src="fila.imagen" class="h-11 w-11 rounded-lg object-cover" /><div v-else class="flex h-11 w-11 items-center justify-center rounded-lg bg-red-50 text-red-600"><i class="fa-solid fa-gamepad"></i></div><p class="font-semibold text-gray-900">{{ fila.juego }}</p></div></td>
                                    <td class="px-3 py-3"><b>{{ fila.cuentas }} cuentas</b><div class="text-xs text-gray-400">PS4 {{ fila.cupos_ps4 }} · PS5 {{ fila.cupos_ps5 }}</div></td>
                                    <td class="px-3 py-3"><b>{{ fila.ventas }}</b><div class="text-xs text-gray-400">PS4 {{ fila.ventas_ps4 }} · PS5 {{ fila.ventas_ps5 }}</div></td>
                                    <td class="px-3 py-3"><b>{{ fila.velocidad_diaria }}</b> por día<div class="text-xs text-gray-400">Índice {{ fila.indice_rotacion }}</div></td>
                                    <td class="px-3 py-3 font-semibold">{{ cobertura(fila.cobertura) }}</td>
                                    <td class="px-3 py-3"><b>{{ fila.antiguedad_promedio }} días</b><div class="text-xs text-gray-400">Más antigua: {{ fecha(fila.cuenta_mas_antigua) }}</div></td>
                                    <td class="px-3 py-3">{{ fecha(fila.ultima_venta) }}<div v-if="fila.dias_sin_venta !== null" class="text-xs text-gray-400">Hace {{ fila.dias_sin_venta }} días</div></td>
                                    <td class="px-3 py-3"><b>{{ moneda(fila.valor_inventario) }}</b><div class="text-xs text-gray-400">Prom. {{ moneda(fila.costo_promedio) }}</div></td>
                                    <td class="px-3 py-3"><span :class="estadoInfo(fila.estado).clase" class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset">{{ estadoInfo(fila.estado).texto }}</span></td>
                                    <td class="px-3 py-3"><Link :href="route('inventario.analisis.index', { juego: fila.juego })" class="inline-flex whitespace-nowrap rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-500">Ver demanda</Link></td>
                                </tr>
                                <tr v-if="resultados.data.length === 0"><td colspan="10" class="px-6 py-16 text-center text-gray-500"><i class="fa-solid fa-box-open mb-3 block text-4xl text-gray-300"></i>No hay inventario que coincida con los filtros.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="resultados.links" />
                </SectionCard>
            </div>
        </template>
    </LayoutPageHeader>
</template>
