<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import FormSelect from './Components/FormSelect.vue';
import Pagination from './Components/Pagination.vue';
import SectionCard from './Components/SectionCard.vue';

const props = defineProps({ pedidos: Object, filtros: Object });
const filtros = reactive({
    buscar: props.filtros?.buscar ?? '', estado: props.filtros?.estado ?? '',
    estado_pago: props.filtros?.estado_pago ?? '', estado_financiero: props.filtros?.estado_financiero ?? '',
    estado_entrega: props.filtros?.estado_entrega ?? '', fecha: props.filtros?.fecha ?? '',
});
const consultar = () => router.get(route('pedidos.index'), filtros, { preserveState: true, replace: true });
const limpiar = () => {
    Object.assign(filtros, { buscar: '', estado: '', estado_pago: '', estado_financiero: '', estado_entrega: '', fecha: '' });
    consultar();
};
const dinero = (valor, moneda = 'COP') => new Intl.NumberFormat('es-CO', { style: 'currency', currency: moneda, maximumFractionDigits: 0 }).format(Number(valor || 0));
const fechaHora = (valor) => new Date(valor).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' });
const etiqueta = (estado) => String(estado || '').replaceAll('_', ' ');
const color = (estado) => ({
    pagado: 'bg-green-100 text-green-800', completado: 'bg-green-100 text-green-800',
    parcial: 'bg-amber-100 text-amber-800', en_proceso: 'bg-blue-100 text-blue-800',
    cancelado: 'bg-red-100 text-red-800', pendiente: 'bg-gray-100 text-gray-800',
    reembolsado: 'bg-purple-100 text-purple-800', reembolso_parcial: 'bg-orange-100 text-orange-800',
    pago_parcial: 'bg-amber-100 text-amber-800', borrador: 'bg-gray-100 text-gray-800',
}[estado] ?? 'bg-gray-100 text-gray-800');
</script>

<template>
    <Head title="📦 Pedidos" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">📦 Pedidos</h2>
                <Link :href="route('pedidos.create')"><PrimaryButton type="button" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-plus mr-2"></i>Nuevo pedido</PrimaryButton></Link>
            </div>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <SectionCard title="Consultar pedidos" description="Filtra por cliente, código, fecha o estado del proceso." icon="fa-solid fa-magnifying-glass text-blue-500">
                    <template #actions><div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3"><p class="text-sm text-gray-500">Registros encontrados</p><p class="text-lg font-bold text-gray-900">{{ pedidos.total ?? pedidos.data.length }}</p></div></template>
                    <form class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="consultar">
                        <div class="xl:col-span-2"><InputLabel for="buscar-pedido" value="Buscar" /><TextInput id="buscar-pedido" v-model="filtros.buscar" class="mt-1 block w-full" placeholder="Código, nombre, teléfono o correo..." /></div>
                        <FormSelect id="estado-pedido" v-model="filtros.estado" label="Estado general" icon="fa-solid fa-list-check"><option value="">Todos los estados</option><option value="pendiente">Pendiente</option><option value="en_proceso">En proceso</option><option value="completado">Completado</option><option value="cancelado">Cancelado</option><option value="reembolsado">Reembolsado</option></FormSelect>
                        <FormSelect id="estado-pago" v-model="filtros.estado_pago" label="Estado del pago" icon="fa-solid fa-credit-card"><option value="">Todos</option><option value="pendiente">Pendiente</option><option value="parcial">Parcial</option><option value="pagado">Pagado</option></FormSelect>
                        <FormSelect id="estado-financiero" v-model="filtros.estado_financiero" label="Estado financiero" icon="fa-solid fa-coins"><option value="">Todos</option><option value="pendiente">Pendiente</option><option value="pago_parcial">Pago parcial</option><option value="pagado">Pagado</option><option value="reembolso_parcial">Reembolso parcial</option><option value="reembolsado">Reembolsado</option></FormSelect>
                        <FormSelect id="estado-entrega" v-model="filtros.estado_entrega" label="Estado de entrega" icon="fa-solid fa-box-open"><option value="">Todos</option><option value="pendiente">Pendiente</option><option value="parcial">Parcial</option><option value="completado">Completado</option></FormSelect>
                        <div><InputLabel for="fecha-pedido" value="Fecha" /><TextInput id="fecha-pedido" v-model="filtros.fecha" type="date" class="mt-1 block w-full" /></div>
                        <div class="flex flex-col gap-3 pt-1 sm:flex-row md:col-span-2 xl:col-span-4"><PrimaryButton class="w-full justify-center sm:w-auto"><i class="fa-solid fa-magnifying-glass mr-2"></i>Consultar</PrimaryButton><SecondaryButton type="button" class="w-full justify-center sm:w-auto" @click="limpiar"><i class="fa-solid fa-eraser mr-2"></i>Limpiar</SecondaryButton></div>
                    </form>
                </SectionCard>

                <SectionCard title="Resultado de pedidos" description="Pedidos encontrados según los filtros aplicados." icon="fa-solid fa-boxes-stacked text-red-500">
                    <div class="overflow-x-auto rounded-lg shadow">
                        <table class="min-w-full border border-gray-200 text-sm">
                            <thead><tr class="border-b bg-gray-100"><th class="px-4 py-3 text-left">Pedido</th><th class="px-4 py-3 text-left">Cliente</th><th class="px-4 py-3 text-left">Total</th><th class="px-4 py-3 text-left">Finanzas</th><th class="px-4 py-3 text-left">Entrega</th><th class="px-4 py-3 text-left">Estado</th><th class="sticky right-0 z-10 bg-gray-100 px-4 py-3 text-center">Acciones</th></tr></thead>
                            <tbody>
                                <tr v-for="pedido in pedidos.data" :key="pedido.id" class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-3"><div class="min-w-[190px] rounded-lg bg-gray-50 p-2"><p class="font-bold text-gray-900">{{ pedido.codigo }}</p><p class="mt-1 text-xs text-gray-500"><i class="fa-regular fa-clock mr-1"></i>{{ fechaHora(pedido.created_at) }}</p></div></td>
                                    <td class="px-4 py-3"><div class="min-w-[220px] rounded-lg bg-gray-50 p-2"><p class="font-semibold text-gray-900"><i class="fa-solid fa-user mr-2 text-blue-500"></i>{{ pedido.cliente?.nombre || pedido.cliente?.usuario || pedido.cliente?.telefono || 'Sin nombre' }}</p><p class="mt-1 text-xs text-gray-500">{{ pedido.cliente?.telefono || pedido.cliente?.email }}</p></div></td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-lg bg-gradient-to-r from-green-100 to-green-200 px-3 py-1 font-bold text-green-900 shadow-sm">{{ dinero(pedido.total, pedido.moneda) }}</span><p class="mt-1 text-xs text-gray-500">Pagado: {{ dinero(pedido.total_pagado, pedido.moneda) }}</p></td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-lg px-3 py-1 text-xs font-bold" :class="color(pedido.estado_financiero)">{{ etiqueta(pedido.estado_financiero) }}</span></td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-lg px-3 py-1 text-xs font-bold" :class="color(pedido.estado_entrega)">{{ etiqueta(pedido.estado_entrega) }}</span></td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-lg px-3 py-1 text-xs font-bold" :class="color(pedido.estado)">{{ etiqueta(pedido.estado) }}</span></td>
                                    <td class="sticky right-0 z-10 bg-white px-4 py-3 text-center"><Link :href="route('pedidos.show', pedido.id)"><SecondaryButton type="button" class="h-8 w-8 justify-center" title="Ver pedido"><i class="fa-solid fa-eye"></i></SecondaryButton></Link></td>
                                </tr>
                                <tr v-if="!pedidos.data.length"><td colspan="7" class="px-4 py-12 text-center"><div class="flex flex-col items-center gap-3"><div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-500"><i class="fa-solid fa-box-open text-2xl"></i></div><div><h3 class="text-lg font-bold text-gray-900">No hay pedidos</h3><p class="text-sm text-gray-500">No se encontraron registros con los filtros seleccionados.</p></div></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="pedidos.links" />
                </SectionCard>
            </div>
        </template>
    </LayoutPageHeader>
</template>
