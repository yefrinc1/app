<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import JuegoCatalogoInput from '@/Components/JuegoCatalogoInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import FormSelect from '../Components/FormSelect.vue';
import FormTextarea from '../Components/FormTextarea.vue';
import { confirmarOperacion, mostrarCarga, mostrarErrores, mostrarRespuesta } from '@/Utils/alertas';

const props = defineProps({ pedido: Object, detalle: Object, permitido: Boolean });
const visible = ref(false);
const cargando = ref(false);
const configuracion = ref(null);
const form = useForm({ juego: '', tipo_cuenta: '', consola: '', precio_unitario: '', motivo: '', firma: '' });
const disponible = computed(() =>
    props.permitido &&
    !['cancelado', 'reembolsado', 'completado'].includes(props.pedido.estado) &&
    props.pedido.estado_financiero !== 'reembolsado' &&
    !Number(props.detalle.cantidad_generada) &&
    !props.detalle.entregas?.length &&
    ['pendiente_revision', 'pendiente_inventario'].includes(props.detalle.estado) &&
    !(props.pedido.reembolsos || []).some((r) => ['pendiente', 'aprobado'].includes(r.estado))
);
const dinero = (valor) => new Intl.NumberFormat('es-CO', {
    style: 'currency', currency: props.pedido.moneda || 'COP',
    maximumFractionDigits: props.pedido.moneda === 'USD' ? 2 : 0,
}).format(valor || 0);
const subtotalNuevo = computed(() => Math.max(0,
    Number(form.precio_unitario || 0) * Number(props.detalle.cantidad) - Number(props.detalle.descuento || 0)
));
const totalNuevo = computed(() => Math.max(0,
    Number(configuracion.value?.total || props.pedido.total) - Number(props.detalle.subtotal) + subtotalNuevo.value
));
const pagado = computed(() => (props.pedido.pagos || [])
    .filter((p) => p.estado === 'aprobado')
    .reduce((suma, p) => suma + Number(p.valor_bruto), 0));
const diferencia = computed(() => totalNuevo.value - pagado.value);
const ingresarPrecio = (evento) => {
    const valor = props.pedido.moneda === 'COP'
        ? evento.target.value.replace(/[^0-9]/g, '')
        : evento.target.value;
    evento.target.value = valor;
    form.precio_unitario = valor;
};
const abrir = async () => {
    if (cargando.value || form.processing) return;
    cargando.value = true;
    mostrarCarga('Revisando juego…', 'Comprobando que todavía no tenga ventas ni inventario generado.');
    try {
        const { data } = await axios.get(route('pedidos.detalles.corregir.edit', [props.pedido.id, props.detalle.id]));
        configuracion.value = data;
        form.reset();
        form.clearErrors();
        form.juego = data.detalle.juego;
        form.tipo_cuenta = data.detalle.tipo_cuenta;
        form.consola = data.detalle.consola;
        form.precio_unitario = String(Number(data.detalle.precio_unitario));
        form.firma = data.firma;
        Swal.close();
        visible.value = true;
    } catch (error) {
        await mostrarErrores(error.response?.data?.errors || { mensaje: 'No se pudo revisar el juego.' });
    } finally {
        cargando.value = false;
    }
};
const guardar = async () => {
    if (form.processing) return;
    if (!form.juego.trim() || form.precio_unitario === '' || form.motivo.trim().length < 5) {
        await mostrarErrores({ mensaje: 'Selecciona el juego, registra su precio y escribe un motivo de al menos 5 caracteres.' });
        return;
    }
    const confirmado = await confirmarOperacion({
        titulo: '¿Cambiar este juego?',
        texto: 'Se conservarán el pedido y sus pagos. El total del pedido quedará en ' + dinero(totalNuevo.value) + '.',
        confirmButtonText: 'Sí, cambiar juego',
    });
    if (!confirmado) return;
    form.patch(route('pedidos.detalles.corregir.update', [props.pedido.id, props.detalle.id]), {
        preserveScroll: true,
        onStart: () => mostrarCarga('Corrigiendo juego…', 'Actualizando el producto y recalculando el pedido.'),
        onSuccess: (page) => { visible.value = false; mostrarRespuesta(page, 'Juego corregido. Sus pagos se conservan.'); },
        onError: (errores) => mostrarErrores(errores, 'No se pudo cambiar el juego'),
    });
};
</script>

<template>
    <SecondaryButton v-if="disponible" type="button" class="whitespace-nowrap" :disabled="cargando || form.processing" @click="abrir"><i class="fa-solid fa-pen mr-2"></i>Cambiar juego</SecondaryButton>
    <span v-else class="text-xs text-gray-400">—</span>
    <Modal :show="visible" :closeable="!form.processing" @close="visible = false">
        <form class="min-w-0 space-y-5 p-6" @submit.prevent="guardar">
            <h2 class="text-lg font-bold text-gray-900">Cambiar juego antes de generar inventario</h2>
            <p class="text-sm text-gray-600">La cantidad y el descuento se conservan. El cambio queda registrado en el historial.</p>
            <div>
                <InputLabel :for="'cambiar-juego-' + detalle.id" value="Juego correcto" />
                <JuegoCatalogoInput v-model="form.juego" :input-id="'cambiar-juego-' + detalle.id" :error="form.errors.juego" />
            </div>
            <InputError :message="form.errors.juego" />
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <FormSelect :id="'cambiar-licencia-' + detalle.id" v-model="form.tipo_cuenta" label="Tipo de cuenta" :error="form.errors.tipo_cuenta"><option value="Primaria">Primaria</option><option value="Secundaria">Secundaria</option></FormSelect>
                <FormSelect :id="'cambiar-consola-' + detalle.id" v-model="form.consola" label="Consola" :error="form.errors.consola"><option value="PS4">PS4</option><option value="PS5">PS5</option></FormSelect>
            </div>
            <div>
                <InputLabel :for="'cambiar-precio-' + detalle.id" value="Precio unitario acordado" />
                <TextInput :id="'cambiar-precio-' + detalle.id" :model-value="form.precio_unitario" :disabled="configuracion?.precio_bloqueado" type="text" :inputmode="pedido.moneda === 'COP' ? 'numeric' : 'decimal'" class="mt-1 block w-full" @input="ingresarPrecio" />
                <InputError :message="form.errors.precio_unitario" />
                <p v-if="configuracion?.precio_bloqueado" class="mt-2 text-sm text-amber-700">Ya se generaron otros juegos. Puedes corregir el producto conservando su precio original.</p>
            </div>
            <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm">
                <p>Cantidad: <strong>{{ detalle.cantidad }}</strong> · Descuento del juego: <strong>{{ dinero(detalle.descuento) }}</strong></p>
                <p class="mt-2">Total anterior: <strong>{{ dinero(configuracion?.total) }}</strong></p>
                <p>Nuevo total: <strong>{{ dinero(totalNuevo) }}</strong></p>
                <p>Pagos aprobados: <strong>{{ dinero(pagado) }}</strong></p>
                <p v-if="diferencia > 0 && pagado > 0" class="mt-2 font-bold text-amber-800">Falta aprobar {{ dinero(diferencia) }} para completar el pago.</p>
                <p v-else-if="diferencia < 0" class="mt-2 font-bold text-amber-800">Excedente: {{ dinero(-diferencia) }}. Puedes registrar un reembolso por la diferencia.</p>
                <p v-else-if="pagado > 0" class="mt-2 font-bold text-green-800">El pago aprobado cubre el nuevo total.</p>
            </div>
            <FormTextarea :id="'motivo-cambio-' + detalle.id" v-model="form.motivo" label="Motivo del cambio" :error="form.errors.motivo" :minlength="5" :required="true" placeholder="Se seleccionó un juego incorrecto." />
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <SecondaryButton type="button" :disabled="form.processing" @click="visible = false">Cancelar</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Guardar cambio</PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
