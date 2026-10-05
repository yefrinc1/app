<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { confirmarOperacion, mostrarCarga, mostrarErrores, mostrarRespuesta } from '@/Utils/alertas';

const props = defineProps({
    pedido: { type: Object, required: true },
    can: { type: Function, required: true },
});

const copiando = ref(null);
const entregasActivas = (detalle) => detalle.entregas?.filter((entrega) => entrega.estado !== 'anulada') || [];
const cantidadAnulada = (detalle) => (detalle.entregas ?? []).filter((entrega) => entrega.estado === 'anulada').length;
const cantidadReembolsada = (detalle) => (props.pedido.reembolsos ?? [])
    .filter((reembolso) => reembolso.estado === 'aprobado' && Number(reembolso.pedido_detalle_id) === Number(detalle.id))
    .reduce((suma, reembolso) => suma + Number(reembolso.cantidad || 0), 0);
const cantidadReemplazada = (detalle) => (detalle.entregas ?? []).filter((entrega) => Boolean(entrega.resolucion)).length;
const cantidadPorAtender = (detalle) => Math.max(0, Number(detalle.cantidad) - cantidadReembolsada(detalle) - cantidadReemplazada(detalle));
const cantidadPendienteInventario = (detalle) => Math.max(
    0,
    cantidadPorAtender(detalle) - entregasActivas(detalle).length - cantidadAnulada(detalle)
);
const solicitadas = computed(() => props.pedido.detalles.reduce((suma, detalle) => suma + cantidadPorAtender(detalle), 0));
const asignadas = computed(() => props.pedido.detalles.reduce((suma, detalle) => suma + entregasActivas(detalle).length, 0));
const entregadas = computed(() => props.pedido.detalles.reduce((suma, detalle) => suma + entregasActivas(detalle).filter((entrega) => entrega.estado === 'entregada').length, 0));
const pendientesInventario = computed(() => props.pedido.detalles.reduce((suma, detalle) => suma + cantidadPendienteInventario(detalle), 0));
const pedidoCerrado = computed(() => ['cancelado', 'reembolsado'].includes(props.pedido.estado) || props.pedido.estado_financiero === 'reembolsado');
const puedeGenerar = computed(() => ['pagado', 'reembolso_parcial'].includes(props.pedido.estado_financiero) && !pedidoCerrado.value && pendientesInventario.value > 0);
const puedeCompletar = computed(() => asignadas.value === solicitadas.value && entregadas.value < solicitadas.value && !pedidoCerrado.value);
const etiquetaEstado = (estado) => ({
    pendiente_revision: 'Pendiente de revisión',
    pendiente_inventario: 'Pendiente de inventario',
    anulado: 'Anulado',
    parcial_anulado: 'Parcialmente anulado',
    reemplazado: 'Reemplazado',
    resuelto: 'Resuelto',
}[estado] ?? String(estado || '').replaceAll('_', ' '));

const generar = async () => {
    const confirmado = await confirmarOperacion({
        titulo: '¿Generar ventas disponibles?',
        texto: 'El sistema buscará cuentas y códigos para todos los juegos pendientes.',
        confirmButtonText: 'Sí, buscar inventario',
    });
    if (!confirmado) return;

    router.post(route('pedidos.generar-entregas', props.pedido.id), {}, {
        preserveScroll: true,
        onStart: () => mostrarCarga('Buscando inventario…', 'Estamos revisando cuentas y códigos disponibles.'),
        onSuccess: (page) => mostrarRespuesta(page, 'Las ventas disponibles fueron generadas.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo generar inventario'),
    });
};

const confirmarEntrega = async (entrega) => {
    const confirmado = await confirmarOperacion({
        titulo: '¿Confirmar entrega?',
        texto: 'Confirma únicamente si ya enviaste los datos al cliente.',
        confirmButtonText: 'Sí, ya fue entregado',
    });
    if (!confirmado) return;

    router.patch(route('pedidos.entregas.confirmar', entrega.id), {}, {
        preserveScroll: true,
        onStart: () => mostrarCarga('Confirmando entrega…'),
        onSuccess: (page) => mostrarRespuesta(page, 'Entrega confirmada correctamente.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo confirmar la entrega'),
    });
};

const completar = async () => {
    const confirmado = await confirmarOperacion({
        titulo: '¿Completar pedido?',
        texto: 'Todas las cuentas generadas quedarán marcadas como entregadas.',
        confirmButtonText: 'Sí, completar pedido',
    });
    if (!confirmado) return;

    router.patch(route('pedidos.completar', props.pedido.id), {}, {
        preserveScroll: true,
        onStart: () => mostrarCarga('Completando pedido…'),
        onSuccess: (page) => mostrarRespuesta(page, 'Pedido completado correctamente.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo completar el pedido'),
    });
};

const textoEntrega = (detalle, entrega) => {
    const cuenta = entrega.correo_juego;
    const codigo = entrega.codigo_verificacion?.codigo;

    const lineas = [
        '🛑 RECOMENDACIONES IMPORTANTES',
        '',
        '1️⃣ No modifiques el usuario, la contraseña ni ningún dato de la cuenta para no perder la garantía.',
        '2️⃣ No inicies sesión como invitado; agrega la cuenta como un usuario normal en la consola.',
        '3️⃣ Esta cuenta funciona en una sola consola. No la abras en PC, celular ni en otra consola; úsala únicamente en la consola donde instalarás el juego.',
        '',
        `🎮 JUEGO: ${detalle.juego}`,
        `🎯 TIPO DE CUENTA: ${detalle.tipo_cuenta} ${detalle.consola}`,
        '',
        `👤 USUARIO: ${cuenta?.correo || ''}`,
        `🔒 CONTRASEÑA: ${cuenta?.contrasena || ''}`,
    ];

    if (codigo) lineas.push(`🔑 CÓDIGO DE VERIFICACIÓN: ${codigo}`);

    return lineas.join('\n');
};

const copiar = async (detalle, entrega) => {
    const texto = textoEntrega(detalle, entrega);

    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(texto);
    } else {
        const area = document.createElement('textarea');
        area.value = texto;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        document.body.removeChild(area);
    }

    copiando.value = entrega.id;
    setTimeout(() => { copiando.value = null; }, 1800);
};
</script>

<template>
    <section class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div>
                <h2 class="font-black text-gray-900">📤 Ventas y entregas</h2>
                <p class="text-sm text-gray-500">
                    Generadas {{ asignadas }}/{{ solicitadas }} · Entregadas {{ entregadas }}/{{ solicitadas }}
                </p>
            </div>

            <div v-if="can('pedidos.entregar')" class="flex flex-wrap gap-2">
                <PrimaryButton
                    v-if="puedeGenerar"
                    type="button"
                    @click="generar"
                >
                    <i class="fa-solid fa-boxes-stacked mr-2"></i>Generar disponibles
                </PrimaryButton>

                <PrimaryButton
                    v-if="puedeCompletar"
                    type="button"
                    class="bg-green-600 hover:bg-green-700 focus:bg-green-700 active:bg-green-800"
                    @click="completar"
                >
                    <i class="fa-solid fa-circle-check mr-2"></i>Completar pedido
                </PrimaryButton>
            </div>
        </div>

        <div
            v-if="pedido.estado_financiero === 'reembolsado'"
            class="mt-4 rounded-xl border border-purple-200 bg-purple-50 p-4 text-sm font-semibold text-purple-800"
        >
            El pedido está cerrado por reembolso total. No se pueden generar ni confirmar entregas.
        </div>

        <div
            v-else-if="!['pagado', 'reembolso_parcial'].includes(pedido.estado_financiero)"
            class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800"
        >
            Debes aprobar el pago total antes de generar las ventas.
        </div>

        <div class="mt-5 space-y-5">
            <article v-for="detalle in pedido.detalles" :key="detalle.id" class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-black text-gray-900">{{ detalle.juego }}</h3>
                        <p class="text-sm text-gray-500">
                            {{ detalle.tipo_cuenta }} {{ detalle.consola }} · {{ entregasActivas(detalle).length }}/{{ cantidadPorAtender(detalle) }} por atender
                        </p>
                        <p v-if="cantidadReembolsada(detalle)" class="text-sm font-semibold text-purple-700">
                            {{ cantidadReembolsada(detalle) }} unidad(es) reembolsada(s)
                        </p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-700">
                        {{ etiquetaEstado(detalle.estado) }}
                    </span>
                </div>

                <div v-if="detalle.entregas?.length" class="mt-4 grid gap-3 xl:grid-cols-2">
                    <div
                        v-for="entrega in detalle.entregas"
                        :key="entrega.id"
                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-gray-900">Entrega #{{ entrega.id }}</p>
                                <p class="text-sm text-gray-600">{{ entrega.venta_id ? `Venta #${entrega.venta_id}` : 'Venta original eliminada' }}</p>
                            </div>
                            <span
                                class="rounded-full px-2 py-1 text-xs font-bold"
                                :class="entrega.estado === 'entregada' ? 'bg-green-100 text-green-700' : (entrega.estado === 'anulada' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')"
                            >
                                {{ entrega.estado }}
                            </span>
                        </div>

                        <div v-if="can('pedidos.entregar') && entrega.estado !== 'anulada'" class="mt-3 rounded-lg bg-white p-3 text-sm">
                            <p><strong>👤 Usuario:</strong> {{ entrega.correo_juego?.correo }}</p>
                            <p><strong>🔒 Contraseña:</strong> {{ entrega.correo_juego?.contrasena }}</p>
                            <p v-if="entrega.codigo_verificacion?.codigo">
                                <strong>🔑 Código de verificación:</strong> {{ entrega.codigo_verificacion.codigo }}
                            </p>
                        </div>

                        <div v-if="can('pedidos.entregar') && entrega.estado !== 'anulada'" class="mt-3 flex flex-wrap gap-2">
                            <SecondaryButton
                                type="button"
                                @click="copiar(detalle, entrega)"
                            >
                                <i class="fa-solid fa-copy mr-2"></i>{{ copiando === entrega.id ? 'Copiado ✓' : 'Copiar datos' }}
                            </SecondaryButton>
                            <PrimaryButton
                                v-if="entrega.estado === 'asignada' && can('pedidos.entregar')"
                                type="button"
                                class="bg-green-600 hover:bg-green-700 focus:bg-green-700 active:bg-green-800"
                                @click="confirmarEntrega(entrega)"
                            >
                                <i class="fa-solid fa-check mr-2"></i>Confirmar entrega
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <div
                    v-if="detalle.inventario_intentado_at && cantidadPendienteInventario(detalle) > 0"
                    class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700"
                >
                    Quedan {{ cantidadPendienteInventario(detalle) }} unidad(es) pendientes de inventario.
                </div>
            </article>
        </div>
    </section>
</template>
