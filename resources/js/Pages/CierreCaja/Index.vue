<script setup>
import { computed, onBeforeUnmount, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Bar, Line } from 'vue-chartjs';
import { BarElement, CategoryScale, Chart as ChartJS, Legend, LineElement, LinearScale, PointElement, Tooltip } from 'chart.js';
import Swal from 'sweetalert2';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import FormSelect from '@/Pages/Pedidos/Components/FormSelect.vue';
import SectionCard from '@/Pages/Pedidos/Components/SectionCard.vue';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';

ChartJS.register(BarElement, CategoryScale, Legend, LineElement, LinearScale, PointElement, Tooltip);

const props = defineProps({ cierre_cajas: Object, filtros: Object, resumen: Object, comparacion: Object, graficos: Object, periodo: Object, mensaje: String });
const page = usePage();
const puedeGestionar = computed(() => page.props.auth?.permissions?.includes('finanzas.cerrar.caja'));
const form = useForm({});
const filtros = reactive({
    periodo: props.filtros?.periodo || '90', desde: props.filtros?.desde || props.periodo?.desde || '',
    hasta: props.filtros?.hasta || props.periodo?.hasta || '', resultado: props.filtros?.resultado || '', buscar: props.filtros?.buscar || '',
});
let temporizadorBusqueda = null;

if (props.mensaje) Swal.fire({ title: props.mensaje, icon: 'success' });

const dinero = (valor) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(valor || 0));
const fecha = (valor) => {
    if (!valor) return '—';
    const [year, month, day] = valor.substring(0, 10).split('-');
    return `${day}/${month}/${year}`;
};
const etiquetaPeriodo = (valor) => {
    if (!valor) return '';
    if (/^\d{4}-\d{2}$/.test(valor)) {
        const [year, month] = valor.split('-');
        return new Intl.DateTimeFormat('es-CO', { month: 'short', year: '2-digit' }).format(new Date(Number(year), Number(month) - 1, 1));
    }
    return fecha(valor);
};
const consultar = () => {
    clearTimeout(temporizadorBusqueda);
    const parametros = { ...filtros };
    if (parametros.periodo !== 'personalizado') { delete parametros.desde; delete parametros.hasta; }
    Object.keys(parametros).forEach((key) => parametros[key] === '' && delete parametros[key]);
    router.get(route('cierre-caja.index'), parametros, { preserveState: true, preserveScroll: true, replace: true });
};
const consultarFechas = () => {
    if (filtros.periodo === 'personalizado' && filtros.desde && filtros.hasta) consultar();
};
const consultarBusqueda = () => {
    clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = setTimeout(consultar, 350);
};
onBeforeUnmount(() => clearTimeout(temporizadorBusqueda));
const limpiar = () => {
    Object.assign(filtros, { periodo: '90', desde: '', hasta: '', resultado: '', buscar: '' });
    router.get(route('cierre-caja.index'), {}, { preserveState: true, preserveScroll: true, replace: true });
};
const eliminarCierre = (cierre) => {
    Swal.fire({ title: '¿Eliminar este cierre?', text: `Se eliminará el cierre del ${fecha(cierre.fecha)}. Esta acción no se puede deshacer.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' }).then((resultado) => {
        if (!resultado.isConfirmed) return;
        form.delete(route('cierre-caja.destroy', cierre.id), { preserveScroll: true, onSuccess: () => Swal.fire({ title: 'Cierre eliminado', icon: 'success' }), onError: () => Swal.fire({ title: 'No fue posible eliminar el cierre', icon: 'error' }) });
    });
};
const variacionTexto = (valor) => `${Number(valor || 0) > 0 ? '+' : ''}${Number(valor || 0).toLocaleString('es-CO')}% vs. período anterior`;
const ajusteCierre = (cierre) => Number(cierre.saldo_final || 0) - (Number(cierre.saldo_inicial || 0) + Number(cierre.ingresos || 0) - Number(cierre.egresos || 0));
const resultadoCierre = (cierre) => Number(cierre.saldo_final || 0) - Number(cierre.saldo_inicial || 0);
const flujoCierre = (cierre) => Number(cierre.ingresos || 0) - Number(cierre.egresos || 0);
const etiquetas = computed(() => (props.graficos?.evolucion || []).map((item) => etiquetaPeriodo(item.periodo)));
const datosFlujo = computed(() => ({ labels: etiquetas.value, datasets: [
    { label: 'Ingresos', data: props.graficos.evolucion.map((item) => Number(item.ingresos)), backgroundColor: '#16a34a', borderRadius: 6 },
    { label: 'Egresos', data: props.graficos.evolucion.map((item) => Number(item.egresos)), backgroundColor: '#dc2626', borderRadius: 6 },
] }));
const datosSaldo = computed(() => ({ labels: etiquetas.value, datasets: [{ label: 'Saldo al cierre', data: props.graficos.evolucion.map((item) => Number(item.saldo_final)), borderColor: '#b91c1c', backgroundColor: 'rgba(185, 28, 28, .12)', fill: true, tension: .3, pointRadius: 3 }] }));
const opcionesGrafica = { responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' }, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${dinero(context.raw)}` } } }, scales: { y: { beginAtZero: true, ticks: { callback: (value) => new Intl.NumberFormat('es-CO', { notation: 'compact' }).format(value) } }, x: { grid: { display: false } } } };
</script>

<template>
    <Head title="Historial de cierres" />
    <LayoutPageHeader>
        <template #titulo-pagina><div><p class="text-xs font-black uppercase tracking-wider text-red-600">Finanzas</p><h2 class="text-xl font-bold text-gray-900">Historial de cierres</h2></div></template>
        <template #contenido-pagina>
            <div class="space-y-6">
                <SectionCard title="Consulta de cierres" description="Analiza la evolución de la caja y encuentra rápidamente cualquier diferencia." icon="fa-solid fa-filter">
                    <template #actions><Link v-if="puedeGestionar" :href="route('cierre-caja.create')"><PrimaryButton><i class="fa-solid fa-cash-register mr-2"></i>Realizar cierre</PrimaryButton></Link></template>
                    <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="consultar">
                        <FormSelect id="periodo-cierres" v-model="filtros.periodo" label="Período" icon="fa-solid fa-calendar-days" @change="consultar">
                            <option value="7">Últimos 7 días</option><option value="30">Últimos 30 días</option><option value="90">Últimos 90 días</option><option value="180">Últimos 6 meses</option><option value="365">Último año</option><option value="todo">Todo el historial</option><option value="personalizado">Rango personalizado</option>
                        </FormSelect>
                        <div v-if="filtros.periodo === 'personalizado'">
                            <InputLabel for="cierres-desde" value="Desde" />
                            <TextInput id="cierres-desde" v-model="filtros.desde" type="date" class="mt-1 block w-full" @change="consultarFechas" />
                        </div>
                        <div v-if="filtros.periodo === 'personalizado'">
                            <InputLabel for="cierres-hasta" value="Hasta" />
                            <TextInput id="cierres-hasta" v-model="filtros.hasta" type="date" class="mt-1 block w-full" @change="consultarFechas" />
                        </div>
                        <FormSelect id="resultado-cierres" v-model="filtros.resultado" label="Resultado" icon="fa-solid fa-scale-balanced" @change="consultar">
                            <option value="">Todos los resultados</option><option value="positivo">Saldo aumentó</option><option value="negativo">Saldo disminuyó</option><option value="ajuste">Con ajuste o retiro</option>
                        </FormSelect>
                        <div>
                            <InputLabel for="buscar-cierres" value="Observaciones" />
                            <div class="relative mt-1">
                                <TextInput id="buscar-cierres" v-model="filtros.buscar" type="search" class="block w-full pr-11" placeholder="Ej. retiro, novedad..." @input="consultarBusqueda" />
                                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-amber-500"></i>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row lg:col-span-4">
                            <PrimaryButton type="submit" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-magnifying-glass mr-2"></i>Consultar</PrimaryButton>
                            <SecondaryButton type="button" class="w-full justify-center sm:w-auto" @click="limpiar"><i class="fa-solid fa-eraser mr-2"></i>Limpiar</SecondaryButton>
                        </div>
                    </form>
                </SectionCard>

                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <article class="rounded-2xl border border-green-100 bg-gradient-to-br from-white to-green-50 p-5 shadow-sm"><p class="text-xs font-black uppercase text-green-700">Ingresos</p><p class="mt-2 text-2xl font-black text-gray-900">{{ dinero(resumen.ingresos) }}</p><p v-if="comparacion.disponible" class="mt-2 text-xs font-semibold text-gray-500">{{ variacionTexto(comparacion.ingresos) }}</p></article>
                    <article class="rounded-2xl border border-red-100 bg-gradient-to-br from-white to-red-50 p-5 shadow-sm"><p class="text-xs font-black uppercase text-red-700">Egresos</p><p class="mt-2 text-2xl font-black text-gray-900">{{ dinero(resumen.egresos) }}</p><p v-if="comparacion.disponible" class="mt-2 text-xs font-semibold text-gray-500">{{ variacionTexto(comparacion.egresos) }}</p></article>
                    <article class="rounded-2xl border border-blue-100 bg-gradient-to-br from-white to-blue-50 p-5 shadow-sm"><p class="text-xs font-black uppercase text-blue-700">Flujo neto</p><p class="mt-2 text-2xl font-black" :class="resumen.flujo_neto >= 0 ? 'text-green-700' : 'text-red-700'">{{ dinero(resumen.flujo_neto) }}</p><p v-if="comparacion.disponible" class="mt-2 text-xs font-semibold text-gray-500">{{ variacionTexto(comparacion.flujo) }}</p></article>
                    <article class="rounded-2xl border border-amber-100 bg-gradient-to-br from-white to-amber-50 p-5 shadow-sm"><p class="text-xs font-black uppercase text-amber-700">Ajustes o retiros</p><p class="mt-2 text-2xl font-black text-gray-900">{{ dinero(resumen.ajustes) }}</p><p class="mt-2 text-xs text-gray-500">Diferencia no explicada por ingresos y egresos.</p></article>
                    <article class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-5 shadow-sm"><p class="text-xs font-black uppercase text-slate-600">Último saldo</p><p class="mt-2 text-2xl font-black text-gray-900">{{ dinero(resumen.saldo_actual) }}</p><p class="mt-2 text-xs text-gray-500">{{ resumen.cierres }} cierres analizados</p></article>
                </section>

                <section class="grid gap-6 xl:grid-cols-2">
                    <SectionCard title="Ingresos frente a egresos" description="Compara el movimiento registrado en cada cierre." icon="fa-solid fa-chart-column"><div class="h-80"><Bar v-if="graficos.evolucion.length" :data="datosFlujo" :options="opcionesGrafica" /><div v-else class="flex h-full items-center justify-center text-sm text-gray-400">No hay datos para graficar.</div></div></SectionCard>
                    <SectionCard title="Evolución del saldo" description="Muestra con cuánto dinero terminó la caja en cada período." icon="fa-solid fa-chart-line"><div class="h-80"><Line v-if="graficos.evolucion.length" :data="datosSaldo" :options="opcionesGrafica" /><div v-else class="flex h-full items-center justify-center text-sm text-gray-400">No hay datos para graficar.</div></div></SectionCard>
                </section>

                <SectionCard title="Detalle de cierres" :description="`${resumen.cierres} cierres encontrados · Promedio de ingresos ${dinero(resumen.promedio_ingresos)}`" icon="fa-solid fa-list-check">
                    <div class="overflow-x-auto rounded-xl border border-gray-200"><table class="min-w-full text-sm"><thead class="bg-gray-900 text-left text-xs uppercase tracking-wide text-white"><tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Saldos</th><th class="px-4 py-3">Ingresos / egresos</th><th class="px-4 py-3">Flujo neto</th><th class="px-4 py-3">Ajuste o retiro</th><th class="px-4 py-3">Observaciones</th><th v-if="puedeGestionar" class="px-4 py-3 text-center">Acciones</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="cierre in cierre_cajas.data" :key="cierre.id" class="hover:bg-red-50/30">
                                <td class="whitespace-nowrap px-4 py-4"><span class="rounded-lg bg-slate-100 px-3 py-1.5 font-bold text-slate-700"><i class="fa-regular fa-calendar mr-1"></i>{{ fecha(cierre.fecha) }}</span></td>
                                <td class="whitespace-nowrap px-4 py-4"><p class="text-xs text-gray-500">Inicial {{ dinero(cierre.saldo_inicial) }}</p><p class="mt-1 font-black text-gray-900">Final {{ dinero(cierre.saldo_final) }}</p><p class="mt-1 text-xs font-bold" :class="resultadoCierre(cierre) >= 0 ? 'text-green-700' : 'text-red-700'">{{ resultadoCierre(cierre) >= 0 ? '+' : '' }}{{ dinero(resultadoCierre(cierre)) }}</p></td>
                                <td class="whitespace-nowrap px-4 py-4"><p class="font-bold text-green-700"><i class="fa-solid fa-arrow-trend-up mr-1"></i>{{ dinero(cierre.ingresos) }}</p><p class="mt-1 font-bold text-red-700"><i class="fa-solid fa-arrow-trend-down mr-1"></i>{{ dinero(cierre.egresos) }}</p></td>
                                <td class="px-4 py-4"><span class="rounded-full px-3 py-1 text-xs font-black" :class="flujoCierre(cierre) >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">{{ dinero(flujoCierre(cierre)) }}</span></td>
                                <td class="px-4 py-4"><span v-if="ajusteCierre(cierre) !== 0" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800">{{ dinero(ajusteCierre(cierre)) }}</span><span v-else class="text-xs font-bold text-green-700"><i class="fa-solid fa-circle-check mr-1"></i>Cuadrado</span></td>
                                <td class="max-w-xs px-4 py-4 text-gray-600"><span v-if="cierre.observaciones">{{ cierre.observaciones }}</span><span v-else class="text-gray-400">Sin observaciones</span></td>
                                <td v-if="puedeGestionar" class="px-4 py-4 text-center"><DangerButton class="h-9 w-9 justify-center !px-0" title="Eliminar cierre" @click="eliminarCierre(cierre)"><i class="fa-solid fa-trash"></i></DangerButton></td>
                            </tr>
                            <tr v-if="!cierre_cajas.data.length"><td :colspan="puedeGestionar ? 7 : 6" class="px-4 py-14 text-center"><i class="fa-solid fa-cash-register text-3xl text-gray-300"></i><p class="mt-3 font-bold text-gray-700">No se encontraron cierres</p><p class="text-sm text-gray-400">Prueba cambiando los filtros seleccionados.</p></td></tr>
                        </tbody>
                    </table></div>
                    <Pagination :links="cierre_cajas.links" />
                </SectionCard>
            </div>
        </template>
    </LayoutPageHeader>
</template>
