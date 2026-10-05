<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import FormTextarea from '@/Pages/Pedidos/Components/FormTextarea.vue';
import { mostrarCarga, mostrarErrores, mostrarRespuesta } from '@/Utils/alertas';

const props = defineProps({
    show: { type: Boolean, default: false },
    cliente: { type: Object, default: null },
});
const emit = defineEmits(['close', 'updated']);

const form = useForm({ nombre: '', codigo_pais: '57', telefono: '', usuario: '', email: '', notas: '' });

watch(() => [props.show, props.cliente], () => {
    if (!props.show || !props.cliente) return;
    form.clearErrors();
    form.defaults({
        nombre: props.cliente.nombre ?? '',
        codigo_pais: props.cliente.codigo_pais ?? '57',
        telefono: props.cliente.telefono ?? '',
        usuario: props.cliente.usuario ?? '',
        email: props.cliente.email ?? '',
        notas: props.cliente.notas ?? '',
    });
    form.reset();
}, { immediate: true, deep: true });

const cerrar = () => {
    if (!form.processing) emit('close');
};
const guardar = () => {
    if (!props.cliente) return;
    form.patch(route('clientes.update', props.cliente.id), {
        preserveScroll: true,
        onStart: () => mostrarCarga('Actualizando cliente…', 'Guardando los datos sin modificar el pedido.'),
        onError: (errores) => mostrarErrores(errores, 'No se pudo actualizar el cliente'),
        onSuccess: (page) => {
            const actualizado = {
                ...props.cliente,
                nombre: form.nombre?.trim() || null,
                codigo_pais: String(form.codigo_pais || '').replace(/\D/g, '') || null,
                telefono: String(form.telefono || '').replace(/\D/g, '') || null,
                usuario: String(form.usuario || '').trim().toLowerCase().replace(/^@+/, '') || null,
                email: String(form.email || '').trim().toLowerCase() || null,
                notas: form.notas?.trim() || null,
            };
            emit('updated', actualizado);
            emit('close');
            mostrarRespuesta(page, 'Datos del cliente actualizados.');
        },
    });
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="cerrar">
        <form class="min-w-0 overflow-hidden p-6" @submit.prevent="guardar">
            <div class="mb-6 flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold text-gray-900"><i class="fa-solid fa-user-pen mr-2 text-red-600"></i>Editar cliente</h2>
                    <p class="mt-1 text-sm text-gray-500">Los cambios se guardan en la ficha del cliente y se verán en sus pedidos.</p>
                </div>
                <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100" @click="cerrar"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
                <div><InputLabel for="editar-cliente-nombre" value="Nombre" /><TextInput id="editar-cliente-nombre" v-model="form.nombre" class="mt-1 block w-full min-w-0" /><InputError class="mt-2" :message="form.errors.nombre" /></div>
                <div class="min-w-0"><InputLabel for="editar-cliente-telefono" value="Teléfono" /><div class="mt-1 grid min-w-0 grid-cols-[80px_minmax(0,1fr)]"><TextInput id="editar-cliente-pais" v-model="form.codigo_pais" class="block w-full min-w-0 rounded-r-none text-center" maxlength="5" placeholder="57" /><TextInput id="editar-cliente-telefono" v-model="form.telefono" class="block w-full min-w-0 rounded-l-none" placeholder="3001234567" /></div><InputError class="mt-2" :message="form.errors.codigo_pais || form.errors.telefono" /></div>
                <div><InputLabel for="editar-cliente-instagram" value="Instagram" /><TextInput id="editar-cliente-instagram" v-model="form.usuario" class="mt-1 block w-full min-w-0" placeholder="@usuario" /><InputError class="mt-2" :message="form.errors.usuario" /></div>
                <div><InputLabel for="editar-cliente-email" value="Correo" /><TextInput id="editar-cliente-email" v-model="form.email" type="email" class="mt-1 block w-full min-w-0" /><InputError class="mt-2" :message="form.errors.email" /></div>
                <FormTextarea id="editar-cliente-notas" v-model="form.notas" label="Notas" class="min-w-0 md:col-span-2" :error="form.errors.notas" />
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                <SecondaryButton type="button" class="w-full justify-center sm:w-auto" :disabled="form.processing" @click="cerrar">Cancelar</SecondaryButton>
                <PrimaryButton class="w-full justify-center sm:w-auto" :disabled="form.processing"><i class="fa-solid fa-floppy-disk mr-2"></i>{{ form.processing ? 'Guardando…' : 'Guardar cambios' }}</PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
