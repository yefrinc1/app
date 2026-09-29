<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '../Components/Pagination.vue';
import SectionCard from '../Components/SectionCard.vue';
import { confirmarOperacion, mostrarCarga, mostrarErrores, mostrarRespuesta, pedirTexto, verComprobante } from '@/Utils/alertas';

defineProps({ pagos: Object });
const dinero = (valor, moneda) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: moneda, maximumFractionDigits: 0 }).format(valor);
const fechaHora = (valor) => valor ? new Date(valor).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) : 'Sin fecha registrada';
const aprobar = async (pago) => {
    const confirmado = await confirmarOperacion({ titulo: '¿Aprobar pago?', texto: `Se aprobará el pago de ${dinero(pago.valor_bruto, pago.moneda)}.`, confirmButtonText: 'Sí, aprobar pago' });
    if (!confirmado) return;
    router.patch(route('pedidos.pagos.aprobar', pago.id), {}, { preserveScroll: true, onStart: () => mostrarCarga('Aprobando pago…'), onSuccess: (page) => mostrarRespuesta(page, 'Pago aprobado correctamente.'), onError: (errores) => mostrarErrores(errores, 'No se pudo aprobar el pago') });
};
const rechazar = async (pago) => {
    const motivo = await pedirTexto({ titulo: 'Rechazar pago', etiqueta: 'Motivo del rechazo', confirmButtonText: 'Rechazar' });
    if (!motivo) return;
    router.patch(route('pedidos.pagos.rechazar', pago.id), { motivo_rechazo: motivo }, { preserveScroll: true, onStart: () => mostrarCarga('Rechazando pago…'), onSuccess: (page) => mostrarRespuesta(page, 'Pago rechazado.'), onError: (errores) => mostrarErrores(errores, 'No se pudo rechazar el pago') });
};
</script>

<template>
    <Head title="💳 Pagos pendientes" />
    <LayoutPageHeader>
        <template #titulo-pagina><h2 class="text-xl font-semibold leading-tight text-gray-800">💳 Pagos pendientes de aprobación</h2></template>
        <template #contenido-pagina>
            <SectionCard title="Comprobantes por revisar" description="Confirma la referencia, el valor y el archivo antes de aprobar cada pago." icon="fa-solid fa-receipt text-purple-500">
                <template #actions><span class="w-fit rounded-full bg-blue-100 px-3 py-1 text-sm font-bold text-blue-800">{{ pagos.total ?? pagos.data.length }} pendientes</span></template>
                <div class="overflow-x-auto rounded-lg shadow">
                    <table class="min-w-full border border-gray-200 text-sm">
                        <thead><tr class="border-b bg-gray-100"><th class="px-4 py-3 text-left">Pedido</th><th class="px-4 py-3 text-left">Cliente</th><th class="px-4 py-3 text-left">Pago</th><th class="px-4 py-3 text-left">Fecha</th><th class="px-4 py-3 text-left">Valor</th><th class="sticky right-0 z-10 bg-gray-100 px-4 py-3 text-center">Acciones</th></tr></thead>
                        <tbody>
                            <tr v-for="pago in pagos.data" :key="pago.id" class="border-b hover:bg-gray-100">
                                <td class="px-4 py-3"><Link :href="route('pedidos.show', pago.pedido_id)" class="font-bold text-red-600 hover:text-red-800">{{ pago.pedido?.codigo }}</Link><p class="mt-1 text-xs text-gray-500">Registrado {{ fechaHora(pago.created_at) }}</p></td>
                                <td class="px-4 py-3"><div class="min-w-[210px] rounded-lg bg-gray-50 p-2"><p class="font-semibold text-gray-900"><i class="fa-solid fa-user mr-2 text-blue-500"></i>{{ pago.pedido?.cliente?.nombre || pago.pedido?.cliente?.usuario || pago.pedido?.cliente?.telefono }}</p></div></td>
                                <td class="px-4 py-3"><span class="inline-flex items-center gap-2 rounded-lg bg-purple-100 px-3 py-1 font-bold text-purple-800"><i class="fa-solid fa-credit-card"></i>{{ pago.metodo_pago }}</span><p class="mt-1 text-xs text-gray-500">Ref: {{ pago.referencia || 'Sin referencia' }}</p></td>
                                <td class="px-4 py-3"><span class="inline-flex min-w-[170px] items-center gap-2 rounded-lg bg-slate-100 px-3 py-1 font-bold text-slate-700"><i class="fa-regular fa-clock"></i>{{ fechaHora(pago.fecha_pago) }}</span></td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-lg bg-gradient-to-r from-green-100 to-green-200 px-3 py-1 font-bold text-green-900 shadow-sm">{{ dinero(pago.valor_bruto, pago.moneda) }}</span></td>
                                <td class="sticky right-0 z-10 bg-white px-4 py-3"><div class="flex justify-center gap-2"><SecondaryButton v-if="pago.comprobante_path" type="button" class="h-8 w-8 justify-center" title="Ver comprobante" @click="verComprobante(route('pedidos.pagos.comprobante', pago.id), 'Comprobante del pago')"><i class="fa-solid fa-eye"></i></SecondaryButton><PrimaryButton type="button" class="h-8 w-8 justify-center" title="Aprobar" @click="aprobar(pago)"><i class="fa-solid fa-check"></i></PrimaryButton><DangerButton type="button" class="h-8 w-8 justify-center" title="Rechazar" @click="rechazar(pago)"><i class="fa-solid fa-xmark"></i></DangerButton></div></td>
                            </tr>
                            <tr v-if="!pagos.data.length"><td colspan="6" class="px-4 py-12 text-center"><div class="flex flex-col items-center gap-3"><div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600"><i class="fa-solid fa-circle-check text-2xl"></i></div><div><h3 class="text-lg font-bold text-gray-900">Todo está revisado</h3><p class="text-sm text-gray-500">No hay pagos pendientes de aprobación.</p></div></div></td></tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :links="pagos.links" />
            </SectionCard>
        </template>
    </LayoutPageHeader>
</template>
