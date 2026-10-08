<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import EntregasPedido from './Partials/Entregas.vue';
import CambiarJuego from './Partials/CambiarJuego.vue';
import EditarPago from './Partials/EditarPago.vue';
import AnulacionesReembolsos from './Partials/AnulacionesReembolsos.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    confirmarOperacion, mostrarAdvertencia, mostrarCarga, mostrarErrores, mostrarRespuesta,
    pedirTexto, verComprobante,
} from '@/Utils/alertas';
import FileInput from './Components/FileInput.vue';
import FormSelect from './Components/FormSelect.vue';

const props = defineProps({
    pedido: { type: Object, required: true },
    permissions: { type: Array, default: () => [] },
});
const can = (permiso) => props.permissions.includes(permiso);

const ingresarEntero = (evento, objeto, campo) => {
    const valor = evento.target.value.replace(/[^0-9]/g, '');
    evento.target.value = valor;
    objeto[campo] = valor;
};
const fechaHoraLocal = () => {
    const fecha = new Date();
    fecha.setMinutes(fecha.getMinutes() - fecha.getTimezoneOffset());
    return fecha.toISOString().slice(0, 16);
};
const pagoForm = useForm({ metodo_pago: '', valor_bruto: '', moneda: props.pedido.moneda, fecha_pago: fechaHoraLocal(), referencia: '', comprobante: null, observaciones: '' });
const registrarPago = async () => {
    if (!pagoForm.comprobante) {
        await mostrarAdvertencia('Falta el comprobante', 'Debes adjuntar el comprobante antes de registrar el método de pago.');
        return;
    }

    const confirmado = await confirmarOperacion({
        titulo: '¿Registrar este pago?',
        texto: 'El pago quedará pendiente de aprobación.',
        confirmButtonText: 'Sí, registrar pago',
    });
    if (!confirmado) return;

    pagoForm.post(route('pedidos.pagos.store', props.pedido.id), {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => mostrarCarga('Subiendo pago…', 'Guardando los datos y el comprobante.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo registrar el pago'),
        onSuccess: (page) => {
            pagoForm.reset();
            pagoForm.moneda = props.pedido.moneda;
            pagoForm.fecha_pago = fechaHoraLocal();
            mostrarRespuesta(page, 'Pago registrado y enviado a revisión.');
        },
    });
};

const liquidandoPago = ref(null);
const liquidacionForm = useForm({ valor_bruto: '', comision: 0, retencion: 0, otros_descuentos: 0, fecha_liquidacion: new Date().toISOString().slice(0, 10), comprobante: null, observaciones: '' });
const abrirLiquidacion = (pago) => {
    liquidandoPago.value = pago;
    liquidacionForm.valor_bruto = pago.valor_bruto;
    liquidacionForm.comision = 0; liquidacionForm.retencion = 0; liquidacionForm.otros_descuentos = 0;
};
const netoLiquidacion = computed(() => Math.max(0, Number(liquidacionForm.valor_bruto || 0) - Number(liquidacionForm.comision || 0) - Number(liquidacionForm.retencion || 0) - Number(liquidacionForm.otros_descuentos || 0)));
const guardarLiquidacion = async () => {
    const confirmado = await confirmarOperacion({
        titulo: '¿Guardar valor neto?',
        texto: `Se registrará un neto recibido de ${dinero(netoLiquidacion.value)}.`,
        confirmButtonText: 'Sí, guardar liquidación',
    });
    if (!confirmado) return;

    liquidacionForm.post(route('pedidos.pagos.liquidaciones.store', liquidandoPago.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => mostrarCarga('Subiendo liquidación…', 'Guardando el valor neto y su comprobante.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo registrar el neto'),
        onSuccess: (page) => {
            liquidacionForm.reset();
            liquidandoPago.value = null;
            mostrarRespuesta(page, 'Liquidación registrada correctamente.');
        },
    });
};

const aprobar = async (pago) => {
    const confirmado = await confirmarOperacion({
        titulo: '¿Aprobar pago?',
        texto: `Confirma que el pago de ${dinero(pago.valor_bruto, pago.moneda)} es válido.`,
        confirmButtonText: 'Sí, aprobar pago',
    });
    if (!confirmado) return;
    router.patch(route('pedidos.pagos.aprobar', pago.id), {}, {
        preserveScroll: true,
        onStart: () => mostrarCarga('Aprobando pago…'),
        onSuccess: (page) => mostrarRespuesta(page, 'Pago aprobado correctamente.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo aprobar el pago'),
    });
};
const rechazar = async (pago) => {
    const motivo = await pedirTexto({ titulo: 'Rechazar pago', etiqueta: 'Motivo del rechazo', confirmButtonText: 'Rechazar' });
    if (!motivo) return;
    router.patch(route('pedidos.pagos.rechazar', pago.id), { motivo_rechazo: motivo }, {
        preserveScroll: true,
        onStart: () => mostrarCarga('Rechazando pago…'),
        onSuccess: (page) => mostrarRespuesta(page, 'Pago rechazado.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo rechazar el pago'),
    });
};
const cancelar = async () => {
    const motivo = await pedirTexto({ titulo: 'Cancelar pedido', etiqueta: 'Motivo de cancelación', confirmButtonText: 'Cancelar pedido', minimo: 5 });
    if (!motivo) return;
    router.patch(route('pedidos.cancelar', props.pedido.id), { motivo }, {
        preserveScroll: true,
        onStart: () => mostrarCarga('Cancelando pedido…'),
        onSuccess: (page) => mostrarRespuesta(page, 'Pedido cancelado correctamente.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo cancelar el pedido'),
    });
};

const dinero = (valor, moneda = props.pedido.moneda) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: moneda, maximumFractionDigits: 0 }).format(Number(valor || 0));
const fechaHora = (valor) => valor ? new Date(valor).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) : 'Sin fecha registrada';
const color = (estado) => ({ aprobado: 'bg-green-100 text-green-700', pagado: 'bg-green-100 text-green-700', completado: 'bg-green-100 text-green-700', rechazado: 'bg-red-100 text-red-700', cancelado: 'bg-red-100 text-red-700', parcial: 'bg-amber-100 text-amber-700', parcial_anulado: 'bg-amber-100 text-amber-700', anulado: 'bg-red-100 text-red-700', reemplazado: 'bg-blue-100 text-blue-700', resuelto: 'bg-blue-100 text-blue-700', pago_parcial: 'bg-amber-100 text-amber-700', reembolso_parcial: 'bg-orange-100 text-orange-700', reembolsado: 'bg-purple-100 text-purple-700', pendiente: 'bg-gray-100 text-gray-700', en_proceso: 'bg-blue-100 text-blue-700' }[estado] ?? 'bg-gray-100 text-gray-700');
const etiquetaEstado = (estado) => ({
    pendiente_revision: 'Pendiente de revisión',
    pendiente_inventario: 'Pendiente de inventario',
    anulado: 'Anulado',
    parcial_anulado: 'Parcialmente anulado',
    reemplazado: 'Reemplazado',
    resuelto: 'Resuelto',
    pago_parcial: 'Pago parcial',
    reembolso_parcial: 'Reembolso parcial',
    en_proceso: 'En proceso',
}[estado] ?? String(estado || '').replaceAll('_', ' '));
</script>

<template>
    <Head :title="pedido.codigo" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div><div class="flex flex-wrap items-center gap-2"><h1 class="text-xl font-black text-gray-900">{{ pedido.codigo }}</h1><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="color(pedido.estado)">{{ pedido.estado }}</span></div><p class="text-sm text-gray-500">Creado {{ new Date(pedido.created_at).toLocaleString('es-CO') }}</p></div>
                <div class="flex gap-2"><Link :href="route('pedidos.index')"><SecondaryButton type="button"><i class="fa-solid fa-arrow-left mr-2"></i>Volver</SecondaryButton></Link><DangerButton v-if="can('pedidos.cancelar') && pedido.estado_financiero !== 'pagado' && !['cancelado','completado','reembolsado'].includes(pedido.estado)" type="button" @click="cancelar"><i class="fa-solid fa-ban mr-2"></i>Cancelar</DangerButton></div>
            </div>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <div v-if="pedido.estado_financiero === 'reembolsado'" class="rounded-2xl border border-purple-300 bg-purple-50 p-5 text-purple-900 shadow">
                    <p class="font-black">Pedido cerrado por reembolso total</p>
                    <p class="mt-1 text-sm">Todos los pagos aprobados fueron reembolsados. Ya no se pueden registrar pagos ni generar entregas.</p>
                </div>

                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow"><p class="text-xs font-bold uppercase text-gray-500"><i class="fa-solid fa-user mr-2 text-blue-500"></i>Cliente</p><p class="mt-2 font-black">{{ pedido.cliente?.nombre || pedido.cliente?.usuario || pedido.cliente?.telefono }}</p><p class="text-sm text-gray-500">{{ pedido.cliente?.telefono }} · {{ pedido.cliente?.email }}</p></div>
                    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow"><p class="text-xs font-bold uppercase text-gray-500"><i class="fa-solid fa-receipt mr-2 text-red-500"></i>Total</p><p class="mt-2 text-2xl font-black">{{ dinero(pedido.total) }}</p><p class="text-sm text-gray-500">Canal: {{ pedido.canal_venta }}</p></div>
                    <div class="rounded-xl border border-green-100 bg-gradient-to-br from-white to-green-50 p-5 shadow"><p class="text-xs font-bold uppercase text-gray-500">Saldo pagado vigente</p><p class="mt-2 text-2xl font-black text-green-600">{{ dinero(pedido.total_pagado) }}</p><span class="rounded-lg px-2 py-1 text-xs font-bold" :class="color(pedido.estado_pago)">{{ pedido.estado_pago }}</span></div>
                    <div class="rounded-xl border border-red-100 bg-gradient-to-br from-white to-red-50 p-5 shadow"><p class="text-xs font-bold uppercase text-gray-500">Saldo pendiente</p><p class="mt-2 text-2xl font-black text-red-600">{{ dinero(pedido.saldo_pendiente) }}</p><span class="rounded-lg px-2 py-1 text-xs font-bold" :class="color(pedido.estado_entrega)">Entrega {{ pedido.estado_entrega }}</span></div>
                    <div class="rounded-xl border border-purple-100 bg-gradient-to-br from-white to-purple-50 p-5 shadow"><p class="text-xs font-bold uppercase text-gray-500">Estado financiero</p><p class="mt-2 text-xl font-black">{{ etiquetaEstado(pedido.estado_financiero) }}</p><p class="text-sm text-gray-500">Reembolsado: {{ dinero(pedido.total_reembolsado) }}</p><span class="rounded-lg px-2 py-1 text-xs font-bold" :class="color(pedido.estado_financiero)">{{ etiquetaEstado(pedido.estado_financiero) }}</span></div>
                </section>

                <section class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <h2 class="font-black text-gray-900">🎮 Juegos solicitados</h2>
                    <div class="mt-4 overflow-x-auto rounded-lg shadow"><table class="min-w-full border border-gray-200 text-sm"><thead><tr class="border-b bg-gray-100"><th class="p-3 text-left">Juego</th><th class="p-3 text-left">Licencia</th><th class="p-3 text-left">Cantidad</th><th class="p-3 text-left">Generadas</th><th class="p-3 text-left">Valor</th><th class="p-3 text-left">Estado</th><th class="p-3 text-left">Acciones</th></tr></thead><tbody><tr v-for="detalle in pedido.detalles" :key="detalle.id" class="border-b hover:bg-gray-100"><td class="p-3"><div class="min-w-[220px] rounded-lg bg-gray-50 p-2 font-bold"><i class="fa-solid fa-gamepad mr-2 text-blue-500"></i>{{ detalle.juego }}</div></td><td class="p-3"><span class="rounded-lg bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-800">{{ detalle.tipo_cuenta }}</span> <span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold text-slate-800">{{ detalle.consola }}</span></td><td class="p-3">{{ detalle.cantidad }}</td><td class="p-3">{{ detalle.cantidad_generada }}</td><td class="p-3"><span class="rounded-lg bg-gradient-to-r from-green-100 to-green-200 px-3 py-1 font-bold text-green-900 shadow-sm">{{ dinero(detalle.subtotal) }}</span></td><td class="p-3"><span class="rounded-lg px-2 py-1 text-xs font-bold" :class="color(detalle.estado)">{{ etiquetaEstado(detalle.estado) }}</span></td><td class="p-3"><CambiarJuego :pedido="pedido" :detalle="detalle" :permitido="can('pedidos.entregar')" /></td></tr></tbody></table></div>
                </section>

                <EntregasPedido :pedido="pedido" :can="can" />

                <AnulacionesReembolsos :pedido="pedido" :can="can" />

                <section class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <div class="flex items-center justify-between"><div><h2 class="font-black text-gray-900">💳 Pagos y comprobantes</h2><p class="text-sm text-gray-500">Solo los pagos aprobados suman al pedido.</p></div></div>
                    <div class="mt-4 space-y-3">
                        <article v-for="pago in pedido.pagos" :key="pago.id" class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <div class="flex flex-col justify-between gap-3 lg:flex-row lg:items-center">
                                <div><div class="flex items-center gap-2"><strong class="rounded-lg bg-gradient-to-r from-green-100 to-green-200 px-3 py-1 text-green-900 shadow-sm">{{ dinero(pago.valor_bruto, pago.moneda) }}</strong><span class="rounded-lg px-2 py-1 text-xs font-bold" :class="color(pago.estado)">{{ pago.estado }}</span></div><p class="mt-2 text-sm text-gray-500"><i class="fa-solid fa-credit-card mr-2 text-purple-500"></i>{{ pago.metodo_pago }} · {{ pago.referencia || 'Sin referencia' }}</p><p class="text-sm font-semibold text-gray-600">📅 {{ fechaHora(pago.fecha_pago) }}</p><p v-if="pago.motivo_rechazo" class="text-sm text-red-600">{{ pago.motivo_rechazo }}</p></div>
                                <div class="flex flex-wrap gap-2"><EditarPago :pedido="pedido" :pago="pago" :permissions="permissions" /><SecondaryButton v-if="pago.comprobante_path" type="button" @click="verComprobante(route('pedidos.pagos.comprobante', pago.id), 'Comprobante del pago')"><i class="fa-solid fa-eye mr-2"></i>Comprobante</SecondaryButton><PrimaryButton v-if="can('pagos.revisar') && pago.estado === 'pendiente'" type="button" @click="aprobar(pago)"><i class="fa-solid fa-check mr-2"></i>Aprobar</PrimaryButton><DangerButton v-if="can('pagos.revisar') && pago.estado === 'pendiente'" type="button" @click="rechazar(pago)"><i class="fa-solid fa-xmark mr-2"></i>Rechazar</DangerButton><PrimaryButton v-if="can('pagos.revisar') && pago.estado === 'aprobado' && !['completado','cancelado','reembolsado'].includes(pedido.estado)" type="button" @click="abrirLiquidacion(pago)"><i class="fa-solid fa-calculator mr-2"></i>Registrar neto</PrimaryButton></div>
                            </div>
                            <div v-if="pago.liquidaciones?.length" class="mt-3 border-t pt-3"><p class="text-xs font-bold uppercase text-gray-500">Liquidaciones</p><div v-for="liq in pago.liquidaciones" :key="liq.id" class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-100 bg-white p-3 text-sm"><span>Bruto {{ dinero(liq.valor_bruto) }} · Costos {{ dinero(Number(liq.comision)+Number(liq.retencion)+Number(liq.otros_descuentos)) }}</span><div class="flex items-center gap-2"><strong class="text-green-700">Neto {{ dinero(liq.valor_neto) }}</strong><SecondaryButton v-if="liq.comprobante_path" type="button" class="px-3 py-1.5 text-xs" @click="verComprobante(route('pedidos.pagos.liquidaciones.comprobante', [pago.id, liq.id]), 'Comprobante del valor neto')"><i class="fa-solid fa-paperclip mr-2"></i>Ver adjunto</SecondaryButton></div></div></div>
                        </article>
                        <p v-if="!pedido.pagos.length" class="rounded-xl border border-dashed p-6 text-center text-sm text-gray-500">Todavía no hay pagos.</p>
                    </div>

                    <form v-if="can('pagos.subir') && pedido.estado !== 'cancelado' && pedido.estado_financiero !== 'reembolsado'" class="mt-5 min-w-0 overflow-hidden rounded-2xl border border-red-100 bg-gradient-to-br from-white via-red-50 to-amber-50 p-4 shadow-md" @submit.prevent="registrarPago">
                        <h3 class="font-bold text-gray-900"><i class="fa-solid fa-plus-circle mr-2 text-red-600"></i>Agregar pago</h3>
                        <div class="mt-4 grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-6">
                            <FormSelect id="nuevo-metodo" v-model="pagoForm.metodo_pago" label="Método" :error="pagoForm.errors.metodo_pago" icon="fa-solid fa-credit-card" required><option value="">Seleccionar</option><option>Bancolombia</option><option>Nequi</option><option>Mercado Pago</option><option>Jumpseller</option><option>Efectivo</option><option>Otro</option></FormSelect>
                            <div class="min-w-0"><InputLabel for="nuevo-valor" value="Valor bruto" /><TextInput id="nuevo-valor" :model-value="pagoForm.valor_bruto" @input="ingresarEntero($event, pagoForm, 'valor_bruto')" required type="text" inputmode="numeric" pattern="[0-9]*" class="mt-1 block w-full" /><InputError class="mt-2" :message="pagoForm.errors.valor_bruto" /></div>
                            <div class="min-w-0"><InputLabel for="nueva-fecha" value="Fecha y hora" /><TextInput id="nueva-fecha" v-model="pagoForm.fecha_pago" type="datetime-local" class="mt-1 block w-full" /><InputError class="mt-2" :message="pagoForm.errors.fecha_pago" /></div>
                            <div class="min-w-0"><InputLabel for="nueva-referencia" value="Referencia" /><TextInput id="nueva-referencia" v-model="pagoForm.referencia" class="mt-1 block w-full" /><InputError class="mt-2" :message="pagoForm.errors.referencia" /></div>
                            <div class="min-w-0 xl:col-span-2"><FileInput id="nuevo-comprobante" label="Comprobante obligatorio" :file-name="pagoForm.comprobante?.name" :error="pagoForm.errors.comprobante" required @change="pagoForm.comprobante = $event.target.files[0] ?? null" /></div>
                            <div class="min-w-0 md:col-span-2 xl:col-span-5"><InputLabel for="nueva-observacion" value="Observaciones" /><TextInput id="nueva-observacion" v-model="pagoForm.observaciones" class="mt-1 block w-full" placeholder="Opcional" /></div>
                            <div class="flex items-end"><PrimaryButton class="w-full justify-center" :disabled="pagoForm.processing"><i class="fa-solid fa-floppy-disk mr-2"></i>{{ pagoForm.processing ? 'Registrando…' : 'Registrar pago' }}</PrimaryButton></div>
                        </div>
                    </form>
                </section>

                <section class="bg-white p-4 shadow sm:rounded-lg sm:p-8"><h2 class="font-black">🕒 Historial</h2><div class="mt-4 space-y-3"><div v-for="evento in pedido.historial" :key="evento.id" class="rounded-r-lg border-l-4 border-red-500 bg-gray-50 px-4 py-3"><p class="font-semibold">{{ evento.descripcion }}</p><p class="text-xs text-gray-500">{{ evento.usuario?.name || 'Sistema' }} · {{ new Date(evento.created_at).toLocaleString('es-CO') }}</p></div></div></section>
            </div>

            <Modal :show="Boolean(liquidandoPago)" @close="liquidandoPago = null">
                <form v-if="liquidandoPago" class="min-w-0 overflow-hidden p-4 sm:p-6" @submit.prevent="guardarLiquidacion">
                    <div class="flex justify-between"><div><h2 class="text-lg font-bold text-gray-900">Liquidación real de Jumpseller</h2><p class="text-sm text-gray-500">Pago #{{ liquidandoPago.id }}</p></div><SecondaryButton type="button" class="h-8 w-8 justify-center" @click="liquidandoPago = null"><i class="fa-solid fa-xmark"></i></SecondaryButton></div>
                    <div class="mt-5 grid min-w-0 gap-4 md:grid-cols-2"><div class="min-w-0"><InputLabel for="liq-bruto" value="Valor bruto" /><TextInput id="liq-bruto" v-model="liquidacionForm.valor_bruto" type="number" required class="mt-1 block w-full" /></div><div class="min-w-0"><InputLabel for="liq-comision" value="Comisión" /><TextInput id="liq-comision" v-model="liquidacionForm.comision" type="number" min="0" class="mt-1 block w-full" /></div><div class="min-w-0"><InputLabel for="liq-retencion" value="Retención/impuestos" /><TextInput id="liq-retencion" v-model="liquidacionForm.retencion" type="number" min="0" class="mt-1 block w-full" /></div><div class="min-w-0"><InputLabel for="liq-descuentos" value="Otros descuentos" /><TextInput id="liq-descuentos" v-model="liquidacionForm.otros_descuentos" type="number" min="0" class="mt-1 block w-full" /></div><div class="min-w-0"><InputLabel for="liq-fecha" value="Fecha" /><TextInput id="liq-fecha" v-model="liquidacionForm.fecha_liquidacion" type="date" required class="mt-1 block w-full" /></div><FileInput id="liq-comprobante" label="Segundo comprobante" :file-name="liquidacionForm.comprobante?.name" @change="liquidacionForm.comprobante = $event.target.files[0] ?? null" /></div>
                    <div class="mt-5 flex items-center justify-between rounded-xl bg-green-50 p-4"><span class="font-bold text-green-800">Valor neto recibido</span><strong class="text-xl text-green-700">{{ dinero(netoLiquidacion) }}</strong></div>
                    <PrimaryButton class="mt-5 w-full justify-center py-3" :disabled="liquidacionForm.processing"><i class="fa-solid fa-floppy-disk mr-2"></i>Guardar liquidación</PrimaryButton>
                </form>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>
