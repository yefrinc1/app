<script setup>
import { computed, nextTick, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import {
    Chart as ChartJS, ArcElement, BarElement, CategoryScale, Filler, Legend,
    LinearScale, LineElement, PointElement, Title, Tooltip,
} from 'chart.js';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Modal from '@/Components/Modal.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import FormSelect from '@/Pages/Pedidos/Components/FormSelect.vue';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';
import SectionCard from '@/Pages/Pedidos/Components/SectionCard.vue';

ChartJS.register(ArcElement, BarElement, CategoryScale, Filler, Legend, LinearScale, LineElement, PointElement, Title, Tooltip);

const props = defineProps({
    resultados: Object,
    planCompras: Array,
    resumen: Object,
    graficos: Object,
    periodo: Object,
    filtros: Object,
});

const form = reactive({
    periodo: props.filtros.periodo ?? '30', desde: props.filtros.desde ?? '', hasta: props.filtros.hasta ?? '',
    consola: props.filtros.consola ?? '', licencia: props.filtros.licencia ?? '', juego: props.filtros.juego ?? '',
    estado: props.filtros.estado ?? '', reposicion: Boolean(props.filtros.reposicion),
    dias_objetivo: String(props.filtros.dias_objetivo ?? 30), seguridad: String(props.filtros.seguridad ?? 20),
    orden: props.filtros.orden ?? 'ventas_totales', direccion: props.filtros.direccion ?? 'desc',
});
const tab = ref('analisis');
const cargandoDetalle = ref(false);
const detalle = ref(null);
const modalAbierto = ref(false);
const cantidadesPlan = reactive({});

const paramsLimpios = () => {
    const params = Object.fromEntries(Object.entries(form).filter(([, valor]) => valor !== '' && valor !== false && valor !== null));
    if (form.reposicion) params.reposicion = 1;
    return params;
};
let temporizador;
const consultar = (inmediato = false) => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => router.get(route('inventario.analisis.index'), paramsLimpios(), {
        preserveState: true, preserveScroll: true, replace: true,
    }), inmediato ? 0 : 350);
};
const cambiarReposicion = (valor) => {
    form.reposicion = Boolean(valor);
    consultar(true);
};
const limpiar = () => {
    Object.assign(form, { periodo: '30', desde: '', hasta: '', consola: '', licencia: '', juego: '', estado: '', reposicion: false, dias_objetivo: '30', seguridad: '20', orden: 'ventas_totales', direccion: 'desc' });
    consultar(true);
};
const ordenar = (columna) => {
    form.direccion = form.orden === columna && form.direccion === 'desc' ? 'asc' : 'desc';
    form.orden = columna;
    consultar(true);
};
const iconoOrden = (columna) => form.orden === columna ? (form.direccion === 'asc' ? '↑' : '↓') : '↕';

const estados = {
    agotado: { texto: 'Agotado', clase: 'bg-red-100 text-red-800 ring-red-200' },
    critico: { texto: 'Crítico', clase: 'bg-orange-100 text-orange-800 ring-orange-200' },
    bajo: { texto: 'Bajo', clase: 'bg-yellow-100 text-yellow-800 ring-yellow-200' },
    saludable: { texto: 'Saludable', clase: 'bg-green-100 text-green-800 ring-green-200' },
    excedente: { texto: 'Excedente', clase: 'bg-blue-100 text-blue-800 ring-blue-200' },
    sin_demanda: { texto: 'Sin demanda', clase: 'bg-gray-100 text-gray-700 ring-gray-200' },
};
const estadoInfo = (estado) => estados[estado] ?? estados.sin_demanda;
const moneda = (valor) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(valor || 0);
const cobertura = (valor) => valor === null ? 'Sin demanda registrada' : `${valor} días`;

const tarjetas = computed(() => [
    ['Videojuegos analizados', props.resumen.juegos, 'fa-gamepad', 'text-red-600', 'bg-red-50'],
    ['Ventas del período', props.resumen.ventas, 'fa-cart-shopping', 'text-amber-600', 'bg-amber-50'],
    ['Cupos PS4', props.resumen.cupos_ps4, 'fa-cube', 'text-red-700', 'bg-red-50'],
    ['Cupos PS5', props.resumen.cupos_ps5, 'fa-cubes', 'text-amber-700', 'bg-amber-50'],
    ['Agotados', props.resumen.agotados, 'fa-circle-xmark', 'text-red-600', 'bg-red-50'],
    ['Críticos', props.resumen.criticos, 'fa-triangle-exclamation', 'text-orange-600', 'bg-orange-50'],
    ['Requieren reposición', props.resumen.reposicion, 'fa-boxes-stacked', 'text-amber-600', 'bg-amber-50'],
    ['Cuentas recomendadas', props.resumen.cuentas_recomendadas, 'fa-circle-plus', 'text-emerald-600', 'bg-emerald-50'],
]);

const opcionesGrafico = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
const topData = computed(() => ({
    labels: props.graficos.top.map((x) => x.juego),
    datasets: [{ label: 'Ventas', data: props.graficos.top.map((x) => x.ventas_totales), backgroundColor: '#dc2626', borderRadius: 6 }],
}));
const consolasData = computed(() => ({ labels: ['PS4', 'PS5'], datasets: [{ data: [props.graficos.consolas.ps4, props.graficos.consolas.ps5], backgroundColor: ['#dc2626', '#f59e0b'] }] }));
const demandaData = computed(() => ({
    labels: props.graficos.demanda_inventario.map((x) => x.juego),
    datasets: [
        { label: 'Demanda objetivo', data: props.graficos.demanda_inventario.map((x) => x.demanda), backgroundColor: '#dc2626', borderRadius: 5 },
        { label: 'Inventario', data: props.graficos.demanda_inventario.map((x) => x.inventario), backgroundColor: '#f59e0b', borderRadius: 5 },
    ],
}));
const evolucionData = computed(() => ({
    labels: props.graficos.evolucion.map((x) => x.fecha),
    datasets: [{ label: 'Ventas diarias', data: props.graficos.evolucion.map((x) => x.ventas), borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,.10)', fill: true, tension: .3 }],
}));

const plan = computed(() => props.planCompras);
const cantidadPlan = (fila) => cantidadesPlan[fila.juego] ?? fila.cuentas_recomendadas;
const inversionPlan = computed(() => plan.value.reduce((total, fila) => total + (cantidadPlan(fila) * fila.costo_unitario), 0));
const exportarPlan = () => {
    const escapar = (valor) => `"${String(valor ?? '').replaceAll('"', '""')}"`;
    const filas = [['Videojuego', 'Prioridad', 'Ventas', 'Cupos PS4', 'Cupos PS5', 'Demanda', 'Cantidad planificada', 'Costo unitario COP', 'Inversión COP']];
    plan.value.forEach((fila) => filas.push([
        fila.juego, estadoInfo(fila.estado).texto, fila.ventas_totales, fila.cupos_ps4, fila.cupos_ps5,
        fila.demanda_estimada, cantidadPlan(fila), fila.costo_unitario, cantidadPlan(fila) * fila.costo_unitario,
    ]));
    const csv = '\uFEFF' + filas.map((fila) => fila.map(escapar).join(';')).join('\r\n');
    const enlace = document.createElement('a');
    enlace.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    enlace.download = `plan-compras-${new Date().toISOString().slice(0, 10)}.csv`;
    enlace.click();
    URL.revokeObjectURL(enlace.href);
};

const abrirDetalle = async (fila) => {
    modalAbierto.value = true;
    cargandoDetalle.value = true;
    detalle.value = null;
    try {
        const { data } = await axios.get(route('inventario.analisis.show', { juego: fila.juego }), { params: paramsLimpios() });
        detalle.value = data;
        await nextTick();
    } finally {
        cargandoDetalle.value = false;
    }
};
const detalleChart = computed(() => ({
    labels: detalle.value?.diarias?.map((x) => x.fecha) ?? [],
    datasets: [{ label: 'Ventas', data: detalle.value?.diarias?.map((x) => x.ventas) ?? [], borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,.1)', fill: true, tension: .3 }],
}));
</script>

<template>
    <Head title="Análisis de demanda y reposición" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">📈 Análisis de demanda y reposición</h2>
                <p class="mt-1 text-sm font-normal text-gray-500">Decisiones de compra basadas en ventas reales e inventario actual.</p>
            </div>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <SectionCard title="Filtros de análisis" description="Consulta ventas históricas y compáralas con el inventario disponible actualmente." icon="fa-solid fa-filter text-red-500">
                    <template #actions>
                        <div class="rounded-xl border border-red-100 bg-gradient-to-r from-white via-red-50 to-amber-50 px-4 py-3">
                            <p class="text-sm text-gray-500">Período analizado</p>
                            <p class="font-bold text-gray-900">{{ periodo.desde }} — {{ periodo.hasta }}</p>
                        </div>
                    </template>
                    <form class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="consultar(true)">
                        <FormSelect id="periodo-analisis" v-model="form.periodo" label="Período" icon="fa-solid fa-calendar-days" @change="consultar(true)">
                            <option value="7">Últimos 7 días</option><option value="15">Últimos 15 días</option>
                            <option value="30">Últimos 30 días</option><option value="60">Últimos 60 días</option>
                            <option value="90">Últimos 90 días</option><option value="personalizado">Rango personalizado</option>
                        </FormSelect>
                        <div v-if="form.periodo === 'personalizado'"><InputLabel for="analisis-desde" value="Desde" /><TextInput id="analisis-desde" v-model="form.desde" type="date" class="mt-1 block w-full" @change="consultar(true)" /></div>
                        <div v-if="form.periodo === 'personalizado'"><InputLabel for="analisis-hasta" value="Hasta" /><TextInput id="analisis-hasta" v-model="form.hasta" type="date" class="mt-1 block w-full" @change="consultar(true)" /></div>
                        <FormSelect id="consola-analisis" v-model="form.consola" label="Consola" icon="fa-brands fa-playstation" @change="consultar(true)"><option value="">Todas las consolas</option><option value="PS4">PlayStation 4</option><option value="PS5">PlayStation 5</option></FormSelect>
                        <FormSelect id="licencia-analisis" v-model="form.licencia" label="Tipo de licencia" icon="fa-solid fa-key" @change="consultar(true)"><option value="">Todas las licencias</option><option value="Primaria">Primaria</option><option value="Secundaria">Secundaria</option></FormSelect>
                        <FormSelect id="estado-analisis" v-model="form.estado" label="Estado del inventario" icon="fa-solid fa-boxes-stacked" @change="consultar(true)"><option value="">Todos los estados</option><option v-for="(info, clave) in estados" :key="clave" :value="clave">{{ info.texto }}</option></FormSelect>
                        <div><InputLabel for="juego-analisis" value="Videojuego" /><TextInput id="juego-analisis" v-model="form.juego" class="mt-1 block w-full" placeholder="Buscar título..." @input="consultar()" /></div>
                        <div><InputLabel for="dias-objetivo" value="Días de abastecimiento" /><TextInput id="dias-objetivo" v-model="form.dias_objetivo" type="number" min="1" max="365" class="mt-1 block w-full" @change="consultar(true)" /></div>
                        <div><InputLabel for="porcentaje-seguridad" value="Porcentaje de seguridad" /><div class="relative"><TextInput id="porcentaje-seguridad" v-model="form.seguridad" type="number" min="0" max="100" step="5" class="mt-1 block w-full pr-10" @change="consultar(true)" /><span class="pointer-events-none absolute inset-y-0 right-4 flex items-center pt-1 font-bold text-amber-600">%</span></div></div>
                        <div class="flex items-end"><label class="flex min-h-11 items-center gap-3 rounded-xl border border-red-100 bg-red-50/50 px-4 py-2.5 text-sm font-bold text-gray-700"><Checkbox :checked="form.reposicion" @update:checked="cambiarReposicion" /> Sólo requieren reposición</label></div>
                        <div class="flex flex-col gap-3 pt-1 sm:flex-row lg:col-span-4">
                            <PrimaryButton type="submit" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-magnifying-glass mr-2"></i>Consultar</PrimaryButton>
                            <SecondaryButton type="button" class="w-full justify-center sm:w-auto" @click="limpiar"><i class="fa-solid fa-eraser mr-2"></i>Limpiar</SecondaryButton>
                        </div>
                    </form>
                </SectionCard>

                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="tarjeta in tarjetas" :key="tarjeta[0]" class="rounded-xl border border-gray-100 bg-white p-5 shadow">
                        <div class="flex items-center justify-between gap-4">
                            <div><p class="text-sm text-gray-500">{{ tarjeta[0] }}</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ tarjeta[1] }}</p></div>
                            <div :class="[tarjeta[4], tarjeta[3]]" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full"><i :class="['fa-solid', tarjeta[2], 'text-xl']"></i></div>
                        </div>
                    </article>
                </section>

                <section class="overflow-hidden rounded-2xl border border-red-100 bg-gradient-to-r from-gray-950 via-gray-900 to-red-950 p-5 text-white shadow-lg">
                    <div class="flex flex-wrap items-center justify-between gap-4"><div><p class="text-sm text-red-100">Inversión estimada para reposición</p><p class="mt-1 text-3xl font-bold">{{ moneda(resumen.inversion_estimada) }}</p></div><div class="text-right text-sm text-amber-100"><p>{{ periodo.desde }} — {{ periodo.hasta }}</p><p>{{ periodo.dias }} días analizados · margen {{ form.seguridad }}%</p></div></div>
                </section>

                <div class="flex flex-wrap gap-2 rounded-xl border border-red-100 bg-gradient-to-r from-white via-red-50 to-amber-50 p-2 shadow-sm">
                    <button v-for="opcion in [['analisis','Análisis'],['graficos','Gráficos'],['plan','Plan de compras']]" :key="opcion[0]" type="button" @click="tab = opcion[0]" :class="tab === opcion[0] ? 'bg-gradient-to-r from-red-600 to-amber-500 text-white shadow-md' : 'text-gray-600 hover:bg-white'" class="rounded-lg px-4 py-2.5 text-sm font-bold transition">{{ opcion[1] }}</button>
                </div>

                <SectionCard v-if="tab === 'analisis'" title="Ranking de demanda e inventario" description="Resultados ordenables según ventas, disponibilidad y necesidad de compra." icon="fa-solid fa-ranking-star text-red-500">
                    <div class="overflow-x-auto">
                        <table class="min-w-[1350px] w-full border border-gray-200 text-sm">
                            <thead class="bg-gray-100 text-left text-xs uppercase tracking-wide text-gray-600"><tr class="border-b">
                                <th class="px-4 py-3">Videojuego</th>
                                <th v-for="columna in [['ventas_totales','Ventas'],['cupos_ps4','Cupos PS4'],['cupos_ps5','Cupos PS5'],['demanda_estimada','Demanda'],['cuentas_recomendadas','Comprar']]" :key="columna[0]" class="cursor-pointer px-3 py-3" @click="ordenar(columna[0])">{{ columna[1] }} {{ iconoOrden(columna[0]) }}</th>
                                <th class="px-3 py-3">Prom./día</th><th class="px-3 py-3">Cobertura</th><th class="px-3 py-3">Tendencia</th><th class="px-3 py-3">Estado</th><th class="px-3 py-3"></th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="fila in resultados.data" :key="fila.juego" class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><div class="flex min-w-[230px] items-center gap-3"><img v-if="fila.imagen" :src="fila.imagen" class="h-11 w-11 rounded-lg object-cover" /><div v-else class="flex h-11 w-11 items-center justify-center rounded-lg bg-red-50 text-red-600"><i class="fa-solid fa-gamepad"></i></div><div><p class="font-semibold text-gray-900">{{ fila.juego }}</p><p class="text-xs text-gray-500">{{ fila.cuentas }} cuentas activas</p></div></div></td>
                                    <td class="px-3 py-3 font-bold text-gray-900">
                                        {{ fila.ventas_totales }} ventas
                                        <div class="text-xs font-normal text-gray-500">PS4 {{ fila.ventas_ps4 }} · PS5 {{ fila.ventas_ps5 }}</div>
                                        <div class="text-xs font-normal text-gray-400">
                                            <template v-if="fila.ventas_primaria && fila.ventas_secundaria">Primaria {{ fila.ventas_primaria }} · Secundaria {{ fila.ventas_secundaria }}</template>
                                            <template v-else-if="fila.ventas_primaria">100% Primaria</template>
                                            <template v-else-if="fila.ventas_secundaria">100% Secundaria</template>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3"><b>{{ fila.cupos_ps4 }}</b><div class="text-xs text-gray-400">P {{ fila.cupos_primarios_ps4 }} · S {{ fila.cupos_secundarios_ps4 }}</div></td>
                                    <td class="px-3 py-3"><b>{{ fila.cupos_ps5 }}</b><div class="text-xs text-gray-400">P {{ fila.cupos_primarios_ps5 }} · S {{ fila.cupos_secundarios_ps5 }}</div></td>
                                    <td class="px-3 py-3 font-semibold">{{ fila.demanda_estimada }}<div class="text-xs font-normal text-gray-400">PS4 {{ fila.ps4.objetivo }} · PS5 {{ fila.ps5.objetivo }}</div></td>
                                    <td class="px-3 py-3">
                                        <span :class="fila.cuentas_recomendadas ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'" class="rounded-lg px-2.5 py-1 font-bold">{{ fila.cuentas_recomendadas }} cuentas</span>
                                        <div class="mt-2 text-xs font-semibold text-gray-600">Comprar: PS4 {{ fila.cuentas_recomendadas_ps4 }} · PS5 {{ fila.cuentas_recomendadas_ps5 }}</div>
                                        <div class="mt-1 text-xs text-gray-400">Faltan: PS4 {{ fila.ps4.faltantes }} · PS5 {{ fila.ps5.faltantes }}</div>
                                        <div class="text-xs text-gray-400">Después: PS4 {{ fila.cupos_post_compra_ps4 }} · PS5 {{ fila.cupos_post_compra_ps5 }}</div>
                                    </td>
                                    <td class="px-3 py-3 font-semibold">{{ fila.promedio_diario }}</td>
                                    <td class="px-3 py-3 text-xs"><p>PS4: {{ cobertura(fila.ps4.cobertura) }}</p><p>PS5: {{ cobertura(fila.ps5.cobertura) }}</p></td>
                                    <td class="px-3 py-3"><span :class="fila.tendencia === 'creciente' ? 'text-green-600' : fila.tendencia === 'decreciente' ? 'text-red-600' : 'text-gray-500'" class="font-bold"><i :class="fila.tendencia === 'creciente' ? 'fa-arrow-trend-up' : fila.tendencia === 'decreciente' ? 'fa-arrow-trend-down' : 'fa-minus'" class="fa-solid mr-1"></i>{{ fila.variacion }}%</span><div class="text-xs text-gray-400">7d {{ fila.ventas_7 }} · 30d {{ fila.ventas_30 }}</div></td>
                                    <td class="px-3 py-3"><span :class="estadoInfo(fila.estado).clase" class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset">{{ estadoInfo(fila.estado).texto }}</span></td>
                                    <td class="px-3 py-3"><PrimaryButton type="button" class="whitespace-nowrap" @click="abrirDetalle(fila)"><i class="fa-solid fa-eye mr-2"></i>Detalle</PrimaryButton></td>
                                </tr>
                                <tr v-if="resultados.data.length === 0"><td colspan="11" class="px-6 py-16 text-center text-gray-500"><i class="fa-solid fa-chart-column mb-3 block text-4xl text-gray-300"></i>No hay ventas que coincidan con los filtros.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="resultados.links" />
                </SectionCard>

                <section v-else-if="tab === 'graficos'" class="grid gap-5 lg:grid-cols-2">
                    <SectionCard title="Top 10 más vendidos" description="Videojuegos con más ventas en el período." icon="fa-solid fa-ranking-star text-red-500"><div class="h-80"><Bar :data="topData" :options="opcionesGrafico" /></div></SectionCard>
                    <SectionCard title="Ventas por consola" description="Distribución entre PlayStation 4 y PlayStation 5." icon="fa-brands fa-playstation text-amber-500"><div class="mx-auto h-80 max-w-sm"><Doughnut :data="consolasData" :options="opcionesGrafico" /></div></SectionCard>
                    <SectionCard title="Demanda frente a inventario" description="Comparación entre objetivo y cupos disponibles." icon="fa-solid fa-scale-balanced text-red-500"><div class="h-80"><Bar :data="demandaData" :options="opcionesGrafico" /></div></SectionCard>
                    <SectionCard title="Evolución histórica" description="Comportamiento diario de las ventas seleccionadas." icon="fa-solid fa-chart-line text-amber-500"><div class="h-80"><Line :data="evolucionData" :options="opcionesGrafico" /></div></SectionCard>
                </section>

                <SectionCard v-else title="Plan de compras" description="Ajusta las cantidades sugeridas sin afectar directamente el inventario." icon="fa-solid fa-cart-flatbed text-red-500">
                    <template #actions><SecondaryButton type="button" @click="exportarPlan"><i class="fa-solid fa-file-excel mr-2"></i>Exportar a Excel</SecondaryButton></template>
                    <div class="overflow-x-auto rounded-lg shadow"><table class="min-w-full border border-gray-200 text-sm"><thead class="bg-gray-100 text-left text-xs uppercase text-gray-600"><tr class="border-b"><th class="px-4 py-3">Videojuego</th><th class="px-4 py-3">Prioridad</th><th class="px-4 py-3">Ventas</th><th class="px-4 py-3">Disponibilidad</th><th class="px-4 py-3">Demanda</th><th class="px-4 py-3">Cantidad planificada</th><th class="px-4 py-3">Costo unitario</th><th class="px-4 py-3">Inversión</th></tr></thead><tbody class="divide-y divide-gray-100"><tr v-for="fila in plan" :key="fila.juego" class="hover:bg-gray-100"><td class="px-4 py-3 font-semibold">{{ fila.juego }}</td><td class="px-4 py-3"><span :class="estadoInfo(fila.estado).clase" class="rounded-full px-2 py-1 text-xs font-bold ring-1">{{ estadoInfo(fila.estado).texto }}</span></td><td class="px-4 py-3">{{ fila.ventas_totales }}</td><td class="px-4 py-3">PS4 {{ fila.cupos_ps4 }} · PS5 {{ fila.cupos_ps5 }}</td><td class="px-4 py-3">{{ fila.demanda_estimada }}</td><td class="px-4 py-3"><input :value="cantidadPlan(fila)" @input="cantidadesPlan[fila.juego] = Math.max(0, Number($event.target.value))" type="number" min="0" class="w-24 rounded-xl border border-red-500/30 bg-gradient-to-r from-white via-red-50 to-amber-50 px-3 py-2 font-semibold shadow-md focus:border-amber-500 focus:ring-4 focus:ring-red-500/20" /></td><td class="px-4 py-3">{{ fila.costo_unitario ? moneda(fila.costo_unitario) : 'Sin dato' }}</td><td class="px-4 py-3 font-bold">{{ fila.costo_unitario ? moneda(cantidadPlan(fila) * fila.costo_unitario) : 'Sin dato' }}</td></tr><tr v-if="plan.length === 0"><td colspan="8" class="p-12 text-center text-gray-500">No hay videojuegos que requieran reposición.</td></tr></tbody><tfoot><tr class="bg-gradient-to-r from-red-50 via-white to-amber-50"><td colspan="7" class="px-4 py-4 text-right font-bold">Inversión planificada</td><td class="px-4 py-4 text-lg font-bold text-red-700">{{ moneda(inversionPlan) }}</td></tr></tfoot></table></div>
                </SectionCard>
            </div>
        </template>
    </LayoutPageHeader>

    <Modal :show="modalAbierto" max-width="6xl" @close="modalAbierto = false">
        <div class="max-h-[90vh] overflow-y-auto p-6">
            <div class="flex items-start justify-between"><div><h3 class="text-xl font-bold text-gray-900">{{ detalle?.fila?.juego ?? 'Cargando detalle...' }}</h3><p class="mt-1 text-sm text-gray-500">Trazabilidad de ventas, disponibilidad y recomendación.</p></div><button @click="modalAbierto = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100"><i class="fa-solid fa-xmark text-xl"></i></button></div>
            <div v-if="cargandoDetalle" class="py-20 text-center text-red-600"><i class="fa-solid fa-spinner fa-spin text-3xl"></i><p class="mt-3">Calculando detalle...</p></div>
            <div v-else-if="detalle" class="mt-6 space-y-6">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div v-for="dato in [['Ventas',detalle.fila.ventas_totales],['Demanda objetivo',detalle.fila.demanda_estimada],['Cuentas sugeridas',detalle.fila.cuentas_recomendadas],['Inversión',moneda(detalle.fila.inversion_estimada)]]" :key="dato[0]" class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-semibold uppercase text-gray-500">{{ dato[0] }}</p><p class="mt-1 text-xl font-bold">{{ dato[1] }}</p></div></div>
                <div class="grid gap-5 lg:grid-cols-2"><article class="rounded-xl border border-gray-200 p-4"><h4 class="font-bold">Ventas diarias</h4><div class="mt-3 h-64"><Line :data="detalleChart" :options="opcionesGrafico" /></div></article><article class="rounded-xl border border-gray-200 p-4"><h4 class="font-bold">Cómo se calculó</h4><div v-for="consola in ['ps4','ps5']" :key="consola" class="mt-3 rounded-lg bg-gray-50 p-3 text-sm"><p class="font-bold uppercase">{{ consola }}</p><p>{{ detalle.fila[consola].ventas }} ventas / {{ periodo.dias }} días = {{ detalle.fila[consola].promedio }} por día.</p><p>Objetivo con {{ form.seguridad }}% de seguridad: <b>{{ detalle.fila[consola].objetivo }} cupos</b>.</p><p>Disponibles: {{ detalle.fila[consola].disponibles }} · Faltantes: {{ detalle.fila[consola].faltantes }}.</p><p>Cobertura: {{ cobertura(detalle.fila[consola].cobertura) }}<span v-if="detalle.fila[consola].agotamiento"> · agotamiento estimado {{ detalle.fila[consola].agotamiento }}</span>.</p></div></article></div>
                <article class="rounded-xl border border-gray-200"><div class="border-b border-gray-100 p-4"><h4 class="font-bold">Cuentas de inventario (sin credenciales)</h4></div><div class="max-h-64 overflow-auto"><table class="min-w-full text-sm"><thead class="sticky top-0 bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-2">Cuenta</th><th class="px-4 py-2">Estado</th><th class="px-4 py-2">PS4 ocup./disp.</th><th class="px-4 py-2">PS5 ocup./disp.</th><th class="px-4 py-2">Secundaria</th></tr></thead><tbody><tr v-for="cuenta in detalle.cuentas" :key="cuenta.id" class="border-t"><td class="px-4 py-2">#{{ cuenta.id }}</td><td class="px-4 py-2">{{ cuenta.estado }}</td><td class="px-4 py-2">{{ cuenta.ocupados_ps4 }} / {{ cuenta.disponibles_ps4 }}</td><td class="px-4 py-2">{{ cuenta.ocupados_ps5 }} / {{ cuenta.disponibles_ps5 }}</td><td class="px-4 py-2">{{ cuenta.secundaria_ocupada ? 'Ocupada' : 'Libre' }}</td></tr></tbody></table></div></article>
                <article class="rounded-xl border border-gray-200"><div class="border-b p-4"><h4 class="font-bold">Últimas ventas del período</h4></div><div class="max-h-56 overflow-auto"><table class="min-w-full text-sm"><tbody class="divide-y"><tr v-for="venta in detalle.historial" :key="venta.id"><td class="px-4 py-2 text-gray-500">#{{ venta.id }}</td><td class="px-4 py-2">{{ venta.created_at }}</td><td class="px-4 py-2">{{ venta.consola }}</td><td class="px-4 py-2">{{ venta.tipo_cuenta }}</td></tr></tbody></table></div></article>
            </div>
        </div>
    </Modal>
</template>
