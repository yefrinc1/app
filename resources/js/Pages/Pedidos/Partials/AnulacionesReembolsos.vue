<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import JuegoCatalogoInput from '@/Components/JuegoCatalogoInput.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import FileInput from '../Components/FileInput.vue';
import FormSelect from '../Components/FormSelect.vue';
import FormTextarea from '../Components/FormTextarea.vue';
import {
    confirmarOperacion, mostrarAdvertencia, mostrarCarga, mostrarErrores,
    mostrarRespuesta, pedirTexto, seleccionarJuegoReembolso, verComprobante,
} from '@/Utils/alertas';

const props = defineProps({
    pedido: { type: Object, required: true },
    can: { type: Function, required: true },
});

const entregaAnular = ref(null);
const entregaResolver = ref(null);
const modoResolucion = ref('');
const formularioReembolso = ref(null);
const anulacionForm = useForm({ tipo: 'no_reutilizable', motivo: '' });
const abrirAnulacion = (detalle, entrega) => { entregaAnular.value = { detalle, entrega }; anulacionForm.reset(); };
const anular = async () => {
    const confirmado = await confirmarOperacion({
        titulo: '¿Anular y eliminar esta venta?',
        texto: anulacionForm.tipo === 'reutilizable'
            ? 'La venta se eliminará definitivamente de la tabla de ventas y la licencia y el código volverán al inventario.'
            : 'La venta se eliminará definitivamente de la tabla de ventas, pero la cuenta continuará consumida.',
        confirmButtonText: 'Sí, anular y eliminar',
        icon: 'warning',
    });
    if (!confirmado) return;

    anulacionForm.post(route('pedidos.entregas.anular', entregaAnular.value.entrega.id), {
        preserveScroll: true,
        onStart: () => mostrarCarga('Anulando y eliminando venta…'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo anular la venta'),
        onSuccess: (page) => {
            entregaAnular.value = null;
            anulacionForm.reset();
            mostrarRespuesta(page, 'Venta anulada y eliminada correctamente.');
        },
    });
};

const reembolsoForm = useForm({
    pedido_pago_id: '', pedido_detalle_id: '', pedido_entrega_anulada_id: '', cantidad: '', valor: '', moneda: props.pedido.moneda,
    metodo: '', referencia: '', comprobante: null, motivo: '',
});
const reemplazoForm = useForm({
    juego: '', tipo_cuenta: '', consola: '', precio_unitario: '', observaciones: '',
});
const errorReembolso = ref('');
const pagosAprobados = computed(() => (props.pedido.pagos ?? []).filter((pago) => pago.estado === 'aprobado'));
const pagadoBruto = computed(() => pagosAprobados.value.reduce((total, pago) => total + Number(pago.valor_bruto || 0), 0));
const reembolsosComprometidos = computed(() => (props.pedido.reembolsos ?? [])
    .filter((reembolso) => ['pendiente', 'aprobado'].includes(reembolso.estado))
    .reduce((total, reembolso) => total + Number(reembolso.valor || 0), 0));
const disponibleReembolso = computed(() => Math.max(0, pagadoBruto.value - reembolsosComprometidos.value));
const cantidadDisponibleDetalle = (detalle) => {
    const entregasActivas = (detalle.entregas ?? []).filter((entrega) => entrega.estado !== 'anulada').length;
    const reemplazadas = (detalle.entregas ?? []).filter((entrega) => Boolean(entrega.resolucion)).length;
    const comprometidas = (props.pedido.reembolsos ?? [])
        .filter((reembolso) => ['pendiente', 'aprobado'].includes(reembolso.estado)
            && Number(reembolso.pedido_detalle_id) === Number(detalle.id))
        .reduce((total, reembolso) => total + Number(reembolso.cantidad || 0), 0);
    return Math.max(0, Number(detalle.cantidad) - entregasActivas - reemplazadas - comprometidas);
};
const cantidadAnulada = (detalle) => (detalle.entregas ?? [])
    .filter((entrega) => entrega.estado === 'anulada').length;
const etiquetaDetalleReembolso = (detalle) => {
    const anuladas = cantidadAnulada(detalle);
    return `${detalle.juego} · disponibles para reembolso: ${cantidadDisponibleDetalle(detalle)}`
        + (anuladas ? ` · anuladas: ${anuladas}` : '');
};
const cambiarDetalle = () => {
    const detalle = props.pedido.detalles.find((item) => Number(item.id) === Number(reembolsoForm.pedido_detalle_id));
    const anuladaSinResolver = (detalle?.entregas ?? []).find((entrega) => entrega.estado === 'anulada'
        && !entrega.resolucion && !entrega.reembolso_resolucion);
    reembolsoForm.pedido_entrega_anulada_id = anuladaSinResolver?.id || '';
    reembolsoForm.cantidad = reembolsoForm.pedido_detalle_id ? 1 : '';
};
const abrirResolver = (detalle, entrega) => {
    entregaResolver.value = { detalle, entrega };
    modoResolucion.value = '';
    reemplazoForm.reset();
    reemplazoForm.tipo_cuenta = detalle.tipo_cuenta;
    reemplazoForm.consola = detalle.consola;
    reemplazoForm.precio_unitario = Number(detalle.subtotal || 0) / Math.max(1, Number(detalle.cantidad || 1));
};
const reemplazarJuego = async () => {
    const confirmado = await confirmarOperacion({
        titulo: '¿Crear el juego de reemplazo?',
        texto: 'La unidad anulada quedará resuelta y el nuevo juego entrará pendiente de revisión de inventario.',
        confirmButtonText: 'Sí, crear reemplazo',
    });
    if (!confirmado) return;

    reemplazoForm.post(route('pedidos.entregas.resolver.reemplazo', entregaResolver.value.entrega.id), {
        preserveScroll: true,
        onStart: () => mostrarCarga('Creando reemplazo…', 'Recalculando el pedido y la diferencia de precio.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo crear el reemplazo'),
        onSuccess: (page) => {
            entregaResolver.value = null;
            modoResolucion.value = '';
            reemplazoForm.reset();
            mostrarRespuesta(page, 'Reemplazo creado correctamente.');
        },
    });
};
const prepararReembolsoAnulacion = async () => {
    const { detalle, entrega } = entregaResolver.value;
    const valorUnitario = Number(entrega.anulacion?.valor_anulado || 0)
        || (Number(detalle.subtotal || 0) / Math.max(1, Number(detalle.cantidad || 1)));

    reembolsoForm.pedido_entrega_anulada_id = entrega.id;
    reembolsoForm.pedido_detalle_id = detalle.id;
    reembolsoForm.cantidad = 1;
    reembolsoForm.valor = Math.min(valorUnitario, disponibleReembolso.value);
    reembolsoForm.motivo = `Reembolso de ${detalle.juego} por anulación de la entrega #${entrega.id}.`;
    if (!reembolsoForm.pedido_pago_id && pagosAprobados.value.length === 1) {
        reembolsoForm.pedido_pago_id = pagosAprobados.value[0].id;
    }

    entregaResolver.value = null;
    modoResolucion.value = '';
    await nextTick();
    formularioReembolso.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};
const registrarReembolso = async () => {
    errorReembolso.value = '';

    if (disponibleReembolso.value <= 0) {
        errorReembolso.value = pagosAprobados.value.length
            ? 'No queda saldo disponible para reembolsar en este pedido.'
            : 'No hay pagos aprobados. Primero debes aprobar al menos un pago del pedido.';
        mostrarAdvertencia('No se puede registrar', errorReembolso.value);
        return;
    }

    if (Number(reembolsoForm.valor || 0) > disponibleReembolso.value) {
        errorReembolso.value = `El valor supera el saldo disponible para reembolsar (${dinero(disponibleReembolso.value)}).`;
        mostrarAdvertencia('Valor inválido', errorReembolso.value);
        return;
    }

    const confirmado = await confirmarOperacion({
        titulo: '¿Registrar solicitud de reembolso?',
        texto: 'La solicitud quedará pendiente hasta que un usuario autorizado confirme la devolución.',
        confirmButtonText: 'Sí, registrar',
    });
    if (!confirmado) return;

    reembolsoForm.post(route('pedidos.reembolsos.store', props.pedido.id), {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => {
            errorReembolso.value = '';
            mostrarCarga('Subiendo reembolso…', 'Guardando los datos y el comprobante.');
        },
        onError: (errors) => {
            errorReembolso.value = Object.values(errors).flat().filter(Boolean).join(' ')
                || 'No fue posible registrar el reembolso. Revisa los datos e inténtalo otra vez.';
            mostrarErrores(errors, 'No se pudo registrar el reembolso');
        },
        onSuccess: (page) => {
            reembolsoForm.reset();
            reembolsoForm.moneda = props.pedido.moneda;
            errorReembolso.value = '';
            mostrarRespuesta(page, 'Reembolso registrado y pendiente de aprobación.');
        },
    });
};
const aprobar = async (reembolso) => {
    const confirmado = await confirmarOperacion({
        titulo: '¿Aprobar reembolso?',
        texto: `Confirma que ya devolviste ${dinero(reembolso.valor, reembolso.moneda)} al cliente.`,
        confirmButtonText: 'Sí, aprobar reembolso',
        icon: 'warning',
    });
    if (!confirmado) return;

    router.patch(route('pedidos.reembolsos.aprobar', reembolso.id), {}, {
        preserveScroll: true,
        onStart: () => mostrarCarga('Aprobando reembolso…'),
        onSuccess: (page) => mostrarRespuesta(page, 'Reembolso aprobado correctamente.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo aprobar el reembolso'),
    });
};
const rechazar = async (reembolso) => {
    const motivo = await pedirTexto({ titulo: 'Rechazar reembolso', etiqueta: 'Motivo del rechazo', confirmButtonText: 'Rechazar' });
    if (!motivo) return;

    router.patch(route('pedidos.reembolsos.rechazar', reembolso.id), { motivo_rechazo: motivo }, {
        preserveScroll: true,
        onStart: () => mostrarCarga('Rechazando reembolso…'),
        onSuccess: (page) => mostrarRespuesta(page, 'Reembolso rechazado.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo rechazar el reembolso'),
    });
};
const relacionarJuego = async (reembolso) => {
    const seleccion = await seleccionarJuegoReembolso(props.pedido.detalles.map((detalle) => ({
        id: detalle.id,
        nombre: `${detalle.juego} · ${detalle.tipo_cuenta} ${detalle.consola}`,
        disponible: cantidadDisponibleDetalle(detalle),
    })));
    if (!seleccion) return;

    router.patch(route('pedidos.reembolsos.relacionar-detalle', reembolso.id), seleccion, {
        preserveScroll: true,
        onStart: () => mostrarCarga('Relacionando juego…'),
        onSuccess: (page) => mostrarRespuesta(page, 'El juego quedó relacionado con el reembolso.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo relacionar el juego'),
    });
};
const dinero = (valor, moneda = props.pedido.moneda) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: moneda, maximumFractionDigits: 0 }).format(Number(valor || 0));
const color = (estado) => ({ aprobado: 'bg-green-100 text-green-700', rechazado: 'bg-red-100 text-red-700', pendiente: 'bg-amber-100 text-amber-700' }[estado] || 'bg-gray-100 text-gray-700');
</script>

<template>
    <section class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
        <div>
            <h2 class="font-black text-gray-900">↩️ Anulaciones y reembolsos</h2>
            <p class="text-sm text-gray-500">Anula la venta, elimina su fila original y conserva toda la auditoría del pedido.</p>
        </div>

        <div
            v-if="!can('pedidos.anular') && !can('reembolsos.crear') && !can('reembolsos.revisar')"
            class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-900"
        >
            <i class="fa-solid fa-lock mr-2"></i>
            Tu usuario no tiene permisos para administrar anulaciones o reembolsos. Un administrador debe ejecutar el seeder de permisos y verificar tu rol.
        </div>

        <div v-if="can('pedidos.anular')" class="mt-5">
            <h3 class="font-bold text-gray-900">Ventas del pedido</h3>
            <div class="mt-3 grid gap-3 lg:grid-cols-2">
                <template v-for="detalle in pedido.detalles" :key="detalle.id">
                    <div v-for="entrega in detalle.entregas" :key="entrega.id" class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-bold">{{ detalle.juego }} · {{ entrega.venta_id ? `Venta #${entrega.venta_id}` : 'Venta eliminada' }}</p>
                                <p class="text-sm text-gray-500">{{ detalle.tipo_cuenta }} {{ detalle.consola }}</p>
                            </div>
                            <DangerButton v-if="entrega.estado !== 'anulada'" type="button" @click="abrirAnulacion(detalle, entrega)"><i class="fa-solid fa-trash mr-2"></i>Anular</DangerButton>
                            <div v-else class="flex flex-wrap justify-end gap-2">
                                <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-700">Anulada</span>
                                <span v-if="entrega.resolucion" class="rounded-full bg-blue-100 px-2 py-1 text-xs font-bold text-blue-700">
                                    Reemplazada por {{ entrega.resolucion.reemplazo_detalle?.juego }}
                                </span>
                                <span v-else-if="entrega.reembolso_resolucion" class="rounded-full bg-purple-100 px-2 py-1 text-xs font-bold text-purple-700">
                                    Reembolso {{ entrega.reembolso_resolucion.estado }}
                                </span>
                                <PrimaryButton v-else type="button" class="bg-amber-500 hover:bg-amber-600 focus:bg-amber-600 active:bg-amber-700" @click="abrirResolver(detalle, entrega)"><i class="fa-solid fa-screwdriver-wrench mr-2"></i>Resolver anulación</PrimaryButton>
                            </div>
                        </div>
                        <p v-if="entrega.motivo_anulacion" class="mt-2 text-sm text-red-700">{{ entrega.motivo_anulacion }}</p>
                    </div>
                </template>
            </div>
        </div>

        <div class="mt-6 border-t pt-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h3 class="font-bold text-gray-900">Reembolsos</h3><p class="text-sm text-gray-500">Aprobados: {{ dinero(pedido.total_reembolsado) }}</p></div>
            </div>

            <div v-if="pedido.reembolsos?.length" class="mt-3 space-y-3">
                <div v-for="reembolso in pedido.reembolsos" :key="reembolso.id" class="flex flex-col justify-between gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 md:flex-row md:items-center">
                    <div><div class="flex items-center gap-2"><strong>{{ dinero(reembolso.valor, reembolso.moneda) }}</strong><span class="rounded-full px-2 py-1 text-xs font-bold" :class="color(reembolso.estado)">{{ reembolso.estado }}</span></div><p class="text-sm text-gray-500">{{ reembolso.metodo }} · {{ reembolso.motivo }}</p><p v-if="reembolso.detalle" class="mt-1 text-sm font-semibold text-purple-700">{{ reembolso.detalle.juego }} · {{ reembolso.cantidad }} unidad(es)</p></div>
                    <div class="flex flex-wrap gap-2"><SecondaryButton v-if="can('reembolsos.revisar') && reembolso.estado !== 'rechazado' && !reembolso.pedido_detalle_id" type="button" @click="relacionarJuego(reembolso)"><i class="fa-solid fa-link mr-2"></i>Relacionar juego</SecondaryButton><SecondaryButton v-if="reembolso.comprobante_path" type="button" @click="verComprobante(route('pedidos.reembolsos.comprobante', reembolso.id), 'Comprobante del reembolso')"><i class="fa-solid fa-eye mr-2"></i>Comprobante</SecondaryButton><PrimaryButton v-if="can('reembolsos.revisar') && reembolso.estado === 'pendiente'" type="button" @click="aprobar(reembolso)"><i class="fa-solid fa-check mr-2"></i>Aprobar</PrimaryButton><DangerButton v-if="can('reembolsos.revisar') && reembolso.estado === 'pendiente'" type="button" @click="rechazar(reembolso)"><i class="fa-solid fa-xmark mr-2"></i>Rechazar</DangerButton></div>
                </div>
            </div>

            <div v-if="pedido.estado_financiero === 'reembolsado'" class="mt-4 rounded-xl border border-purple-300 bg-purple-50 p-4 text-sm font-semibold text-purple-800">
                El reembolso total ya fue aprobado. El pedido quedó cerrado y no admite nuevas solicitudes.
            </div>

            <form v-else-if="can('reembolsos.crear')" ref="formularioReembolso" class="mt-4 rounded-2xl border border-red-100 bg-gradient-to-br from-white via-red-50 to-amber-50 p-4 shadow-md" @submit.prevent="registrarReembolso">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h4 class="font-bold">Registrar reembolso</h4>
                    <p class="text-sm font-semibold text-gray-600">Disponible: {{ dinero(disponibleReembolso) }}</p>
                </div>
                <div v-if="!pagosAprobados.length" class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm font-semibold text-amber-800">
                    Este pedido no tiene pagos aprobados. Aprueba un pago antes de registrar el reembolso.
                </div>
                <div v-if="errorReembolso" class="mt-3 rounded-xl border border-red-300 bg-red-50 p-3 text-sm font-semibold text-red-700">
                    {{ errorReembolso }}
                </div>
                <div v-if="reembolsoForm.pedido_entrega_anulada_id" class="mt-3 flex items-center justify-between rounded-xl border border-purple-300 bg-purple-50 p-3 text-sm font-semibold text-purple-800">
                    <span>Resolviendo la entrega anulada #{{ reembolsoForm.pedido_entrega_anulada_id }} mediante reembolso.</span>
                    <SecondaryButton type="button" class="h-8 w-8 justify-center" @click="reembolsoForm.pedido_entrega_anulada_id = ''; reembolsoForm.pedido_detalle_id = ''; reembolsoForm.cantidad = ''"><i class="fa-solid fa-xmark"></i></SecondaryButton>
                </div>
                <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="md:col-span-2">
                        <FormSelect id="reembolso-detalle" v-model="reembolsoForm.pedido_detalle_id" label="Juego relacionado" :error="reembolsoForm.errors.pedido_detalle_id" icon="fa-solid fa-gamepad" @change="cambiarDetalle">
                            <option value="">Reembolso general, sin descontar un juego</option>
                            <option v-for="detalle in pedido.detalles" :key="detalle.id" :value="detalle.id" :disabled="cantidadDisponibleDetalle(detalle) <= 0">
                                {{ etiquetaDetalleReembolso(detalle) }}
                            </option>
                        </FormSelect>
                        <p class="mt-1 text-xs text-gray-500">También puedes seleccionar una unidad cuya venta ya fue anulada, incluso si se marcó como no reutilizable.</p>
                    </div>
                    <div v-if="reembolsoForm.pedido_detalle_id">
                        <InputLabel for="reembolso-cantidad" value="Cantidad reembolsada" /><TextInput id="reembolso-cantidad" v-model="reembolsoForm.cantidad" required type="number" min="1" class="mt-1 block w-full" /><InputError class="mt-2" :message="reembolsoForm.errors.cantidad" />
                    </div>
                    <FormSelect id="reembolso-pago" v-model="reembolsoForm.pedido_pago_id" label="Pago relacionado" :error="reembolsoForm.errors.pedido_pago_id" icon="fa-solid fa-credit-card"><option value="">Opcional</option><option v-for="pago in pagosAprobados" :key="pago.id" :value="pago.id">#{{ pago.id }} · {{ pago.metodo_pago }} · {{ dinero(pago.valor_bruto, pago.moneda) }}</option></FormSelect>
                    <div><InputLabel for="reembolso-valor" value="Valor" /><TextInput id="reembolso-valor" v-model="reembolsoForm.valor" required type="number" min="0.01" step="0.01" :max="disponibleReembolso" class="mt-1 block w-full" /><InputError class="mt-2" :message="reembolsoForm.errors.valor" /></div>
                    <div><InputLabel for="reembolso-metodo" value="Método de devolución" /><TextInput id="reembolso-metodo" v-model="reembolsoForm.metodo" required class="mt-1 block w-full" /><InputError class="mt-2" :message="reembolsoForm.errors.metodo" /></div>
                    <div><InputLabel for="reembolso-referencia" value="Referencia" /><TextInput id="reembolso-referencia" v-model="reembolsoForm.referencia" class="mt-1 block w-full" /></div>
                    <FileInput id="reembolso-comprobante" label="Comprobante" :file-name="reembolsoForm.comprobante?.name" :error="reembolsoForm.errors.comprobante" @change="reembolsoForm.comprobante = $event.target.files[0] ?? null" />
                    <FormTextarea id="reembolso-motivo" v-model="reembolsoForm.motivo" label="Motivo del reembolso" :error="reembolsoForm.errors.motivo" placeholder="Mínimo 5 caracteres" :minlength="5" required class="md:col-span-2" />
                    <div class="flex items-end"><PrimaryButton class="w-full justify-center" :disabled="reembolsoForm.processing || disponibleReembolso <= 0"><i class="fa-solid fa-floppy-disk mr-2"></i>{{ reembolsoForm.processing ? 'Registrando…' : 'Registrar' }}</PrimaryButton></div>
                </div>
            </form>
        </div>

        <Modal :show="Boolean(entregaAnular)" @close="entregaAnular = null">
            <form v-if="entregaAnular" class="p-6" @submit.prevent="anular">
                <h2 class="text-lg font-black">Anular y eliminar venta #{{ entregaAnular.entrega.venta_id }}</h2>
                <p class="text-sm text-gray-500">{{ entregaAnular.detalle.juego }} · {{ entregaAnular.detalle.tipo_cuenta }} {{ entregaAnular.detalle.consola }}</p>
                <FormSelect id="tipo-anulacion" v-model="anulacionForm.tipo" label="¿La cuenta puede volver a venderse?" class="mt-5" icon="fa-solid fa-rotate-left">
                    <option value="no_reutilizable">No reutilizable: conservar inventario consumido</option>
                    <option value="reutilizable">Reutilizable: devolver licencia y código</option>
                </FormSelect>
                <div v-if="anulacionForm.tipo === 'reutilizable'" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-800">Selecciona esta opción únicamente si las credenciales no fueron utilizadas o confirmaste que siguen siendo seguras.</div>
                <FormTextarea id="motivo-anulacion" v-model="anulacionForm.motivo" label="Motivo" :error="anulacionForm.errors.motivo" :rows="4" required class="mt-4" />
                <div class="mt-3 rounded-xl border border-red-300 bg-red-50 p-3 text-sm font-semibold text-red-800">La fila original será eliminada obligatoriamente de la tabla de ventas. El pedido conservará la entrega anulada, el motivo y los datos de auditoría.</div>
                <div class="mt-5 flex gap-2"><DangerButton class="flex-1 justify-center py-3" :disabled="anulacionForm.processing"><i class="fa-solid fa-trash mr-2"></i>Anular y eliminar</DangerButton><SecondaryButton type="button" @click="entregaAnular = null">Cancelar</SecondaryButton></div>
            </form>
        </Modal>

        <Modal :show="Boolean(entregaResolver)" @close="entregaResolver = null">
            <div v-if="entregaResolver" class="p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black">Resolver anulación</h2>
                        <p class="text-sm text-gray-500">{{ entregaResolver.detalle.juego }} · Entrega #{{ entregaResolver.entrega.id }}</p>
                    </div>
                    <SecondaryButton type="button" class="h-8 w-8 justify-center" @click="entregaResolver = null"><i class="fa-solid fa-xmark"></i></SecondaryButton>
                </div>

                <div v-if="!modoResolucion" class="mt-5 grid gap-4 md:grid-cols-2">
                    <button type="button" class="rounded-2xl border-2 border-blue-200 bg-blue-50 p-5 text-left hover:border-blue-500" @click="modoResolucion = 'reemplazo'">
                        <span class="block text-2xl">🔄</span>
                        <strong class="mt-2 block text-blue-900">Reemplazar juego</strong>
                        <span class="mt-1 block text-sm text-blue-700">Conserva el pago, agrega el juego correcto y recalcula cualquier diferencia.</span>
                    </button>
                    <button type="button" class="rounded-2xl border-2 border-purple-200 bg-purple-50 p-5 text-left hover:border-purple-500" @click="prepararReembolsoAnulacion">
                        <span class="block text-2xl">💸</span>
                        <strong class="mt-2 block text-purple-900">Registrar reembolso</strong>
                        <span class="mt-1 block text-sm text-purple-700">Relaciona la devolución de dinero con esta entrega anulada.</span>
                    </button>
                </div>

                <form v-else class="mt-5" @submit.prevent="reemplazarJuego">
                    <SecondaryButton type="button" class="mb-4" @click="modoResolucion = ''"><i class="fa-solid fa-arrow-left mr-2"></i>Cambiar opción</SecondaryButton>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel for="juego-reemplazo" value="Juego de reemplazo" />
                            <JuegoCatalogoInput v-model="reemplazoForm.juego" input-id="juego-reemplazo" :error="reemplazoForm.errors.juego" />
                        </div>
                        <FormSelect id="reemplazo-cuenta" v-model="reemplazoForm.tipo_cuenta" label="Tipo de cuenta" :error="reemplazoForm.errors.tipo_cuenta" icon="fa-solid fa-key" required><option value="">Seleccionar</option><option>Primaria</option><option>Secundaria</option></FormSelect>
                        <FormSelect id="reemplazo-consola" v-model="reemplazoForm.consola" label="Consola" :error="reemplazoForm.errors.consola" icon="fa-brands fa-playstation" required><option value="">Seleccionar</option><option>PS4</option><option>PS5</option></FormSelect>
                        <div><InputLabel for="reemplazo-precio" value="Precio del reemplazo" /><TextInput id="reemplazo-precio" v-model="reemplazoForm.precio_unitario" required type="number" min="0" step="0.01" class="mt-1 block w-full" /><InputError class="mt-2" :message="reemplazoForm.errors.precio_unitario" /></div>
                        <div><InputLabel for="reemplazo-observaciones" value="Observaciones" /><TextInput id="reemplazo-observaciones" v-model="reemplazoForm.observaciones" class="mt-1 block w-full" placeholder="Opcional" /></div>
                    </div>
                    <div class="mt-4 rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-800">Si el nuevo precio es mayor, deberás aprobar el valor faltante antes de generar la venta. Si es menor, quedará una diferencia disponible para reembolso.</div>
                    <PrimaryButton class="mt-5 w-full justify-center bg-blue-600 py-3 hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800" :disabled="reemplazoForm.processing"><i class="fa-solid fa-rotate mr-2"></i>{{ reemplazoForm.processing ? 'Creando reemplazo…' : 'Crear reemplazo' }}</PrimaryButton>
                </form>
            </div>
        </Modal>
    </section>
</template>
