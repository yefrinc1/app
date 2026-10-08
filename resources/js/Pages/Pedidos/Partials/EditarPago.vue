<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import FormSelect from '../Components/FormSelect.vue';
import FileInput from '../Components/FileInput.vue';
import FormTextarea from '../Components/FormTextarea.vue';
import { confirmarOperacion, mostrarCarga, mostrarErrores, mostrarRespuesta, verComprobante } from '@/Utils/alertas';

const props = defineProps({ pedido: Object, pago: Object, permissions: Array });
const visible = ref(false);
const cargando = ref(false);
const tieneComprobante = ref(false);
const form = useForm({
    _method: 'patch', metodo_pago: '', valor_bruto: '', fecha_pago: '',
    referencia: '', comprobante: null, observaciones: '', motivo: '', firma: '',
});
const can = (permiso) => (props.permissions || []).includes(permiso);
const editable = computed(() =>
    can('pagos.subir') &&
    (props.pago.estado !== 'aprobado' || can('pagos.revisar')) &&
    ['pendiente', 'rechazado', 'aprobado'].includes(props.pago.estado) &&
    !['completado', 'cancelado', 'reembolsado'].includes(props.pedido.estado) &&
    props.pedido.estado_financiero !== 'reembolsado' &&
    !props.pago.liquidaciones?.length &&
    !(props.pedido.detalles || []).some((d) => Number(d.cantidad_generada) > 0 || d.entregas?.length) &&
    !(props.pedido.reembolsos || []).some((r) => ['pendiente', 'aprobado'].includes(r.estado))
);
const metodos = computed(() => [...new Set([
    props.pago.metodo_pago, 'Bancolombia', 'Nequi', 'Llave Bre-B',
    'Mercado Pago', 'Jumpseller', 'Efectivo', 'Otro',
])].filter(Boolean));
const ingresarImporte = (evento) => {
    const valor = props.pago.moneda === 'COP' ? evento.target.value.replace(/[^0-9]/g, '') : evento.target.value;
    evento.target.value = valor;
    form.valor_bruto = valor;
};
const abrir = async () => {
    if (cargando.value || form.processing) return;
    cargando.value = true;
    mostrarCarga('Revisando pago…', 'Comprobando que pueda corregirse.');
    try {
        const { data } = await axios.get(route('pedidos.pagos.editar', props.pago.id));
        form.reset();
        form.clearErrors();
        form.metodo_pago = data.pago.metodo_pago;
        form.valor_bruto = String(Number(data.pago.valor_bruto));
        form.fecha_pago = data.fecha_pago_formulario || '';
        form.referencia = data.pago.referencia || '';
        form.observaciones = data.pago.observaciones || '';
        form.firma = data.firma;
        tieneComprobante.value = data.tiene_comprobante;
        Swal.close();
        visible.value = true;
    } catch (error) {
        await mostrarErrores(error.response?.data?.errors || { mensaje: 'No se puede editar este pago. Revisa permisos, ventas y liquidaciones.' });
    } finally {
        cargando.value = false;
    }
};
const guardar = async () => {
    if (form.processing) return;
    if (!form.metodo_pago || !(Number(form.valor_bruto) > 0) || form.motivo.trim().length < 5) {
        await mostrarErrores({ mensaje: 'Registra método, importe válido y un motivo de al menos 5 caracteres.' });
        return;
    }
    if (!tieneComprobante.value && !form.comprobante) {
        await mostrarErrores({ comprobante: 'Adjunta el comprobante para guardar.' });
        return;
    }
    const confirmado = await confirmarOperacion({
        titulo: '¿Corregir este pago?',
        texto: 'Quedará pendiente de revisión y dejará de contar como aprobado hasta aprobarlo nuevamente.',
        confirmButtonText: 'Sí, corregir pago',
    });
    if (!confirmado) return;
    // POST con method spoofing permite cargar archivos al actualizar en PHP.
    form.post(route('pedidos.pagos.actualizar', props.pago.id), {
        forceFormData: true, preserveScroll: true,
        onStart: () => mostrarCarga('Guardando corrección…', 'Actualizando datos y comprobante.'),
        onSuccess: (page) => {
            visible.value = false;
            form.reset();
            mostrarRespuesta(page, 'Pago corregido y enviado nuevamente a revisión.');
        },
        onError: (errores) => mostrarErrores(errores, 'No se pudo corregir el pago'),
    });
};
</script>

<template>
    <SecondaryButton v-if="editable" type="button" :disabled="cargando || form.processing" @click="abrir"><i class="fa-solid fa-pen mr-2"></i>Editar pago</SecondaryButton>
    <Modal :show="visible" :closeable="!form.processing" @close="visible = false">
        <form class="min-w-0 space-y-5 overflow-hidden p-6" @submit.prevent="guardar">
            <h2 class="text-lg font-bold text-gray-900">Editar pago #{{ pago.id }}</h2>
            <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">La corrección queda en el historial. El pago deberá revisarse y aprobarse nuevamente.</p>
            <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                <FormSelect :id="'editar-metodo-' + pago.id" v-model="form.metodo_pago" label="Método de pago" :error="form.errors.metodo_pago" :required="true"><option v-for="metodo in metodos" :key="metodo" :value="metodo">{{ metodo }}</option></FormSelect>
                <div class="min-w-0">
                    <InputLabel :for="'editar-importe-' + pago.id" :value="'Valor bruto (' + pago.moneda + ')'" />
                    <TextInput :id="'editar-importe-' + pago.id" :model-value="form.valor_bruto" type="text" :inputmode="pago.moneda === 'COP' ? 'numeric' : 'decimal'" required class="mt-1 block w-full" @input="ingresarImporte" />
                    <InputError :message="form.errors.valor_bruto" />
                </div>
                <div class="min-w-0">
                    <InputLabel :for="'editar-fecha-' + pago.id" value="Fecha y hora del pago" />
                    <TextInput :id="'editar-fecha-' + pago.id" v-model="form.fecha_pago" type="datetime-local" step="1" class="mt-1 block w-full" />
                    <InputError :message="form.errors.fecha_pago" />
                </div>
                <div class="min-w-0">
                    <InputLabel :for="'editar-referencia-' + pago.id" value="Referencia" />
                    <TextInput :id="'editar-referencia-' + pago.id" v-model="form.referencia" class="mt-1 block w-full" />
                    <InputError :message="form.errors.referencia" />
                </div>
            </div>
            <div class="min-w-0 rounded-lg border border-gray-200 p-3">
                <SecondaryButton v-if="tieneComprobante" type="button" class="mb-3" @click="verComprobante(route('pedidos.pagos.comprobante', pago.id), 'Comprobante actual')"><i class="fa-solid fa-eye mr-2"></i>Ver comprobante actual</SecondaryButton>
                <FileInput :id="'editar-comprobante-' + pago.id" label="Reemplazar comprobante" :file-name="form.comprobante?.name" :error="form.errors.comprobante" @change="form.comprobante = $event.target.files[0] ?? null" />
                <p class="mt-2 text-xs text-gray-500">{{ tieneComprobante ? 'Si no adjuntas otro archivo, se conserva el actual.' : 'Debes adjuntar un comprobante.' }}</p>
            </div>
            <FormTextarea :id="'editar-notas-' + pago.id" v-model="form.observaciones" label="Observaciones" :error="form.errors.observaciones" />
            <FormTextarea :id="'editar-motivo-' + pago.id" v-model="form.motivo" label="Motivo de la corrección" :error="form.errors.motivo" :required="true" :minlength="5" placeholder="Se registraron los datos de otro pago por error." />
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <SecondaryButton type="button" :disabled="form.processing" @click="visible = false">Cancelar</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Guardar corrección</PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
