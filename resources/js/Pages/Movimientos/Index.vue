<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import { ArcElement, BarElement, CategoryScale, Chart as ChartJS, Legend, LineElement, LinearScale, PointElement, Tooltip } from 'chart.js';
import Swal from 'sweetalert2';
import Modal from '@/Components/Modal.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import FormTextarea from '@/Pages/Pedidos/Components/FormTextarea.vue';
import FormSelect from '@/Pages/Pedidos/Components/FormSelect.vue';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';
import SectionCard from '@/Pages/Pedidos/Components/SectionCard.vue';

ChartJS.register(ArcElement, BarElement, CategoryScale, Legend, LineElement, LinearScale, PointElement, Tooltip);

const props = defineProps({
    movimientos: Object,
    filtros: Object,
    resumen: Object,
    comparacion: Object,
    graficos: Object,
    periodo: Object,
});

const filtrosForm = reactive({
    periodo: props.filtros.periodo ?? '30',
    desde: props.filtros.desde ?? '',
    hasta: props.filtros.hasta ?? '',
    tipo: props.filtros.tipo ?? '',
    buscar: props.filtros.buscar ?? '',
});
const modalAbierto = ref(false);
const editando = ref(false);
const form = useForm({ id: null, tipo: '', descripcion: '', valor: '', observaciones: '' });

const params = () => Object.fromEntries(Object.entries(filtrosForm).filter(([, valor]) => valor !== '' && valor !== null));
let temporizador;
const consultar = (inmediato = false) => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => router.get(route('movimientos.index'), params(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    }), inmediato ? 0 : 350);
};
const limpiar = () => {
    Object.assign(filtrosForm, { periodo: '30', desde: '', hasta: '', tipo: '', buscar: '' });
    consultar(true);
};
const abrirCrear = () => {
    editando.value = false;
    form.reset();
    form.clearErrors();
    modalAbierto.value = true;
};
const abrirEditar = (movimiento) => {
    editando.value = true;
    form.clearErrors();
    Object.assign(form, {
        id: movimiento.id,
        tipo: movimiento.tipo,
        descripcion: movimiento.descripcion,
        valor: movimiento.valor,
        observaciones: movimiento.observaciones ?? '',
    });
    modalAbierto.value = true;
};
const cerrarModal = () => {
    modalAbierto.value = false;
    form.reset();
    form.clearErrors();
};
const guardar = () => {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            cerrarModal();
            Swal.fire({ title: editando.value ? 'Movimiento actualizado' : 'Movimiento creado', icon: 'success', timer: 1500, showConfirmButton: false });
        },
    };
    editando.value ? form.put(route('movimientos.update', form.id), opciones) : form.post(route('movimientos.store'), opciones);
};
const eliminar = async (movimiento) => {
    const confirmacion = await Swal.fire({
        title: '¿Eliminar movimiento?',
        text: `${movimiento.tipo}: ${movimiento.descripcion}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        reverseButtons: true,
    });
    if (!confirmacion.isConfirmed) return;
    router.delete(route('movimientos.destroy', movimiento.id), {
        preserveScroll: true,
        onSuccess: () => Swal.fire({ title: 'Movimiento eliminado', icon: 'success', timer: 1300, showConfirmButton: false }),
    });
};

const moneda = (valor) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(valor || 0));
const fecha = (valor) => valor ? new Date(valor).toLocaleDateString('es-CO', { dateStyle: 'medium' }) : '';
const claseTipo = (tipo) => ({ Ingreso: 'bg-green-100 text-green-800', Egreso: 'bg-red-100 text-red-800', 'Retiro Utilidades': 'bg-amber-100 text-amber-800' }[tipo] ?? 'bg-gray-100 text-gray-800');
const iconoTipo = (tipo) => ({ Ingreso: 'fa-arrow-trend-up', Egreso: 'fa-arrow-trend-down', 'Retiro Utilidades': 'fa-money-bill-transfer' }[tipo] ?? 'fa-circle-info');
const textoVariacion = (valor) => `${valor > 0 ? '+' : ''}${valor}%`;
const claseVariacion = (valor, inverso = false) => {
    const favorable = inverso ? valor <= 0 : valor >= 0;
    return favorable ? 'text-green-700' : 'text-red-700';
};

const evolucionData = computed(() => ({
    labels: props.graficos.evolucion.map((item) => item.periodo),
    datasets: [
        { label: 'Ingresos', data: props.graficos.evolucion.map((item) => item.ingresos), borderColor: '#16a34a', backgroundColor: '#16a34a', tension: .3 },
        { label: 'Egresos', data: props.graficos.evolucion.map((item) => item.egresos), borderColor: '#dc2626', backgroundColor: '#dc2626', tension: .3 },
        { label: 'Retiros', data: props.graficos.evolucion.map((item) => item.retiros), borderColor: '#f59e0b', backgroundColor: '#f59e0b', tension: .3 },
    ],
}));
const tiposData = computed(() => ({
    labels: ['Ingresos', 'Egresos', 'Retiros'],
    datasets: [{ data: [props.graficos.tipos.ingresos, props.graficos.tipos.egresos, props.graficos.tipos.retiros], backgroundColor: ['#16a34a', '#dc2626', '#f59e0b'] }],
}));
const conceptosData = computed(() => ({
    labels: props.graficos.conceptos.map((item) => item.descripcion),
    datasets: [{ label: 'Total', data: props.graficos.conceptos.map((item) => item.total), backgroundColor: '#dc2626', borderRadius: 6 }],
}));
const opcionesDinero = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { callback: (valor) => `$${Number(valor).toLocaleString('es-CO')}` } } },
};
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
    <Head title="Movimientos financieros" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">🔄 Movimientos financieros</h2>
                <p class="mt-1 text-sm font-normal text-gray-500">Controla el flujo de dinero y compara ingresos, egresos y retiros.</p>
            </div>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <SectionCard title="Filtros financieros" description="Analiza un período o encuentra un movimiento específico." icon="fa-solid fa-filter text-red-500">
                    <template #actions><PrimaryButton type="button" class="w-full justify-center sm:w-auto" @click="abrirCrear"><i class="fa-solid fa-plus mr-2"></i>Nuevo movimiento</PrimaryButton></template>
                    <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="consultar(true)">
                        <FormSelect id="periodo-movimientos" v-model="filtrosForm.periodo" label="Período" icon="fa-solid fa-calendar-days" @change="consultar(true)">
                            <option value="7">Últimos 7 días</option><option value="30">Últimos 30 días</option><option value="90">Últimos 90 días</option><option value="180">Últimos 180 días</option><option value="365">Último año</option><option value="todo">Todo el historial</option><option value="personalizado">Rango personalizado</option>
                        </FormSelect>
                        <div v-if="filtrosForm.periodo === 'personalizado'"><InputLabel for="movimientos-desde" value="Desde" /><TextInput id="movimientos-desde" v-model="filtrosForm.desde" type="date" class="mt-1 block w-full" @change="consultar(true)" /></div>
                        <div v-if="filtrosForm.periodo === 'personalizado'"><InputLabel for="movimientos-hasta" value="Hasta" /><TextInput id="movimientos-hasta" v-model="filtrosForm.hasta" type="date" class="mt-1 block w-full" @change="consultar(true)" /></div>
                        <FormSelect id="tipo-movimiento" v-model="filtrosForm.tipo" label="Tipo" icon="fa-solid fa-right-left" @change="consultar(true)"><option value="">Todos los tipos</option><option value="Ingreso">Ingreso</option><option value="Egreso">Egreso</option><option value="Retiro Utilidades">Retiro de utilidades</option></FormSelect>
                        <div><InputLabel for="buscar-movimiento" value="Descripción u observación" /><TextInput id="buscar-movimiento" v-model="filtrosForm.buscar" class="mt-1 block w-full" placeholder="Buscar movimiento..." @input="consultar()" /></div>
                        <div class="flex flex-col gap-3 sm:flex-row lg:col-span-4"><PrimaryButton type="submit" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-magnifying-glass mr-2"></i>Consultar</PrimaryButton><SecondaryButton type="button" class="w-full justify-center sm:w-auto" @click="limpiar"><i class="fa-solid fa-eraser mr-2"></i>Limpiar</SecondaryButton></div>
                    </form>
                </SectionCard>

                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <article class="rounded-xl border border-green-100 bg-green-50 p-5"><p class="text-sm font-semibold text-green-700">Ingresos</p><p class="mt-1 text-2xl font-black text-green-900">{{ moneda(resumen.ingresos) }}</p><p v-if="comparacion.disponible" class="mt-1 text-xs font-bold" :class="claseVariacion(comparacion.ingresos)">{{ textoVariacion(comparacion.ingresos) }} vs. período anterior</p></article>
                    <article class="rounded-xl border border-red-100 bg-red-50 p-5"><p class="text-sm font-semibold text-red-700">Egresos</p><p class="mt-1 text-2xl font-black text-red-900">{{ moneda(resumen.egresos) }}</p><p v-if="comparacion.disponible" class="mt-1 text-xs font-bold" :class="claseVariacion(comparacion.egresos, true)">{{ textoVariacion(comparacion.egresos) }} vs. período anterior</p></article>
                    <article class="rounded-xl border border-amber-100 bg-amber-50 p-5"><p class="text-sm font-semibold text-amber-700">Retiros</p><p class="mt-1 text-2xl font-black text-amber-900">{{ moneda(resumen.retiros) }}</p><p class="mt-1 text-xs text-amber-600">Utilidades retiradas</p></article>
                    <article class="rounded-xl border border-blue-100 bg-blue-50 p-5"><p class="text-sm font-semibold text-blue-700">Balance operativo</p><p class="mt-1 text-2xl font-black" :class="resumen.balance_operativo >= 0 ? 'text-blue-900' : 'text-red-700'">{{ moneda(resumen.balance_operativo) }}</p><p v-if="comparacion.disponible" class="mt-1 text-xs font-bold" :class="claseVariacion(comparacion.balance)">{{ textoVariacion(comparacion.balance) }} vs. período anterior</p></article>
                    <article class="rounded-xl border p-5" :class="resumen.saldo_neto >= 0 ? 'border-purple-100 bg-purple-50' : 'border-red-100 bg-red-50'"><p class="text-sm font-semibold" :class="resumen.saldo_neto >= 0 ? 'text-purple-700' : 'text-red-700'">Saldo después de retiros</p><p class="mt-1 text-2xl font-black" :class="resumen.saldo_neto >= 0 ? 'text-purple-900' : 'text-red-900'">{{ moneda(resumen.saldo_neto) }}</p><p class="mt-1 text-xs opacity-70">{{ resumen.movimientos }} movimientos</p></article>
                </section>

                <section class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900"><i class="fa-solid fa-circle-info mr-2"></i><b>Balance operativo</b> = ingresos − egresos. <b>Saldo después de retiros</b> también descuenta los retiros de utilidades.</section>

                <section class="grid gap-5 xl:grid-cols-2">
                    <SectionCard title="Evolución del flujo" description="Comportamiento de ingresos, egresos y retiros durante el período." icon="fa-solid fa-chart-line text-red-500" class="xl:col-span-2"><div class="h-80"><Line :data="evolucionData" :options="opcionesDinero" /></div></SectionCard>
                    <SectionCard title="Distribución del dinero" description="Participación monetaria de cada tipo de movimiento." icon="fa-solid fa-chart-pie text-amber-500"><div class="mx-auto h-72 max-w-md"><Doughnut :data="tiposData" :options="opcionesDona" /></div></SectionCard>
                    <SectionCard title="Principales conceptos de salida" description="Egresos y retiros agrupados por descripción." icon="fa-solid fa-ranking-star text-red-500"><div class="h-72"><Bar :data="conceptosData" :options="opcionesDineroHorizontal" /></div></SectionCard>
                </section>

                <SectionCard title="Registro de movimientos" :description="`${movimientos.total} movimientos coinciden con los filtros.`" icon="fa-solid fa-list text-red-500">
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-[1000px] w-full text-sm">
                            <thead class="bg-gray-100 text-left text-xs uppercase tracking-wide text-gray-600"><tr><th class="px-4 py-3">Movimiento</th><th class="px-4 py-3">Valor</th><th class="px-4 py-3">Observaciones</th><th class="px-4 py-3">Fecha</th><th class="px-4 py-3 text-right">Acciones</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="movimiento in movimientos.data" :key="movimiento.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><div class="min-w-[240px]"><span :class="claseTipo(movimiento.tipo)" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold"><i :class="iconoTipo(movimiento.tipo)" class="fa-solid"></i>{{ movimiento.tipo }}</span><p class="mt-2 font-semibold text-gray-900">{{ movimiento.descripcion }}</p></div></td>
                                    <td class="px-4 py-3 text-base font-black" :class="movimiento.tipo === 'Ingreso' ? 'text-green-700' : movimiento.tipo === 'Egreso' ? 'text-red-700' : 'text-amber-700'">{{ moneda(movimiento.valor) }}</td>
                                    <td class="px-4 py-3"><p class="max-w-sm whitespace-normal text-gray-600">{{ movimiento.observaciones || 'Sin observaciones' }}</p></td>
                                    <td class="px-4 py-3"><span class="whitespace-nowrap rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"><i class="fa-regular fa-calendar mr-1"></i>{{ fecha(movimiento.created_at) }}</span></td>
                                    <td class="px-4 py-3"><div class="flex justify-end gap-2"><SecondaryButton type="button" class="h-9 w-9 justify-center !p-0" aria-label="Editar" @click="abrirEditar(movimiento)"><i class="fa-solid fa-pen"></i></SecondaryButton><DangerButton type="button" class="h-9 w-9 justify-center !p-0" aria-label="Eliminar" @click="eliminar(movimiento)"><i class="fa-solid fa-trash"></i></DangerButton></div></td>
                                </tr>
                                <tr v-if="movimientos.data.length === 0"><td colspan="5" class="px-6 py-16 text-center text-gray-500"><i class="fa-solid fa-receipt mb-3 block text-4xl text-gray-300"></i><p class="font-bold text-gray-700">No hay movimientos en este período</p><p class="mt-1 text-sm">Prueba otro filtro o registra un movimiento.</p></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="movimientos.links" />
                </SectionCard>
            </div>

            <Modal :show="modalAbierto" @close="cerrarModal">
                <form class="p-6" @submit.prevent="guardar">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-red-600">Finanzas</p><h2 class="mt-1 text-xl font-black text-gray-900">{{ editando ? 'Editar movimiento' : 'Nuevo movimiento' }}</h2></div>
                    <div class="mt-6"><InputLabel for="movimiento-tipo" value="Tipo" /><select id="movimiento-tipo" v-model="form.tipo" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"><option value="">Selecciona un tipo</option><option value="Ingreso">Ingreso</option><option value="Egreso">Egreso</option><option value="Retiro Utilidades">Retiro de utilidades</option></select><InputError class="mt-2" :message="form.errors.tipo" /></div>
                    <div class="mt-5"><InputLabel for="movimiento-descripcion" value="Descripción" /><TextInput id="movimiento-descripcion" v-model="form.descripcion" class="mt-1 block w-full" maxlength="150" placeholder="Ej. Publicidad, compra de cuenta..." /><InputError class="mt-2" :message="form.errors.descripcion" /></div>
                    <div class="mt-5"><InputLabel for="movimiento-valor" value="Valor en COP" /><TextInput id="movimiento-valor" v-model="form.valor" type="number" min="1" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.valor" /></div>
                    <div class="mt-5">
                        <FormTextarea
                            id="movimiento-observaciones"
                            v-model="form.observaciones"
                            label="Observaciones"
                            :error="form.errors.observaciones"
                            :rows="3"
                            placeholder="Información adicional opcional"
                        />
                    </div>
                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><SecondaryButton type="button" class="justify-center" @click="cerrarModal">Cancelar</SecondaryButton><PrimaryButton type="submit" class="justify-center" :disabled="form.processing"><i class="fa-solid fa-floppy-disk mr-2"></i>{{ form.processing ? 'Guardando…' : 'Guardar movimiento' }}</PrimaryButton></div>
                </form>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>
