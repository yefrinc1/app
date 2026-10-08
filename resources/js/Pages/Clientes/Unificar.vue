<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SectionCard from '@/Pages/Pedidos/Components/SectionCard.vue';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';
import FormSelect from '@/Pages/Pedidos/Components/FormSelect.vue';
import FormTextarea from '@/Pages/Pedidos/Components/FormTextarea.vue';
import { confirmarOperacion, mostrarCarga, mostrarErrores, mostrarExito } from '@/Utils/alertas';

defineProps({ historial: Object });
const busquedas = ref({ principal: '', duplicado: '' });
const resultados = ref({ principal: [], duplicado: [] });
const buscando = ref({ principal: false, duplicado: false });
const seleccionados = ref({ principal: null, duplicado: null });
const previa = ref(null);
const revisando = ref(false);
const errorBusqueda = ref({ principal: '', duplicado: '' });
const campos = [
    { nombre: 'nombre', etiqueta: 'Nombre' },
    { nombre: 'telefono', etiqueta: 'Teléfono' },
    { nombre: 'usuario', etiqueta: 'Instagram' },
    { nombre: 'email', etiqueta: 'Correo de contacto' },
];
const tablas = { pedidos: 'Pedidos', ventas: 'Ventas', instalacion_evidencias: 'Evidencias de garantía', portal_juego_accesos: 'Accesos a juegos' };
const form = useForm({
    principal_id: null, duplicado_id: null, firma: '',
    selecciones: { nombre: 'principal', telefono: 'principal', usuario: 'principal', email: 'principal' },
    motivo: '', confirmado: false,
});
const dato = (cliente, campo) => {
    if (campo === 'telefono') return cliente.telefono ? '+' + (cliente.codigo_pais || '') + ' ' + cliente.telefono : 'Sin teléfono';
    if (campo === 'usuario') return cliente.usuario ? '@' + cliente.usuario.replace(/^@+/, '') : 'Sin Instagram';
    return cliente[campo] || 'Sin dato';
};
for (const lado of ['principal', 'duplicado']) {
    let temporizador;
    let version = 0;
    watch(() => busquedas.value[lado], (valor) => {
        clearTimeout(temporizador);
        const actual = ++version;
        resultados.value[lado] = [];
        errorBusqueda.value[lado] = '';
        buscando.value[lado] = false;
        if (valor.trim().length < 2) return;
        temporizador = setTimeout(async () => {
            buscando.value[lado] = true;
            try {
                const { data } = await axios.get(route('clientes.unificar.buscar'), { params: { q: valor } });
                if (actual === version) resultados.value[lado] = data;
            } catch {
                if (actual === version) errorBusqueda.value[lado] = 'No se pudo buscar. Intenta nuevamente.';
            } finally {
                if (actual === version) buscando.value[lado] = false;
            }
        }, 350);
    });
}
const seleccionar = (lado, cliente) => {
    seleccionados.value[lado] = cliente;
    busquedas.value[lado] = '';
    resultados.value[lado] = [];
    previa.value = null;
    form.clearErrors();
};
const cambiar = (lado) => {
    seleccionados.value[lado] = null;
    previa.value = null;
    form.clearErrors();
};
const revisar = async () => {
    if (!seleccionados.value.principal || !seleccionados.value.duplicado) return;
    revisando.value = true;
    previa.value = null;
    try {
        const { data } = await axios.get(route('clientes.unificar.previa'), { params: {
            principal_id: seleccionados.value.principal.id,
            duplicado_id: seleccionados.value.duplicado.id,
        } });
        previa.value = data;
        form.principal_id = data.principal.id;
        form.duplicado_id = data.duplicado.id;
        form.firma = data.firma;
        form.confirmado = false;
        form.clearErrors();
        for (const campo of campos) {
            form.selecciones[campo.nombre] = data.principal[campo.nombre] ? 'principal' : 'duplicado';
        }
    } catch (error) {
        await mostrarErrores(error.response?.data?.errors || { mensaje: 'No se pudo revisar la unificación.' });
    } finally {
        revisando.value = false;
    }
};
const unificar = async () => {
    if (!previa.value || form.processing) return;
    if (!form.confirmado || form.motivo.trim().length < 5) {
        await mostrarErrores({ mensaje: 'Confirma que ambos registros son de la misma persona y escribe un motivo de al menos 5 caracteres.' });
        return;
    }
    const confirmado = await confirmarOperacion({
        titulo: '¿Unificar estos clientes?',
        texto: 'Las compras del cliente #' + form.duplicado_id + ' pasarán al #' + form.principal_id + '. El duplicado quedará archivado. Revisa que el teléfono seleccionado sea el correcto.',
        confirmButtonText: 'Sí, unificar clientes',
    });
    if (!confirmado) return;
    form.post(route('clientes.unificar.store'), {
        preserveScroll: true,
        onStart: () => mostrarCarga('Unificando clientes…', 'Reuniendo compras, evidencias y acceso al portal.'),
        onSuccess: () => mostrarExito('Clientes unificados', 'El cliente conserva su acceso al portal y puede consultar todas sus compras.'),
        onError: (errores) => { previa.value = null; mostrarErrores(errores); },
        onCancel: () => mostrarErrores({ mensaje: 'La solicitud fue interrumpida. Revisa los clientes antes de volver a intentarlo.' }),
    });
};
const fecha = (valor) => valor ? new Date(valor).toLocaleString('es-CO') : '';
</script>

<template>
    <Head title="Unificar clientes" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">👥 Unificar clientes</h2>
        </template>
        <template #contenido-pagina>
            <div class="space-y-6">
                <nav class="flex flex-wrap gap-2" aria-label="Clientes">
                    <Link :href="route('clientes.index')"><SecondaryButton type="button">Directorio de clientes</SecondaryButton></Link>
                    <PrimaryButton type="button" aria-current="page">Unificar clientes</PrimaryButton>
                </nav>
                <SectionCard title="Reunir las compras de una misma persona" description="Selecciona el registro que conservarás y el duplicado. Después revisa los datos y las compras antes de confirmar." icon="fa-solid fa-users text-blue-500">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div v-for="lado in ['principal', 'duplicado']" :key="lado" class="min-w-0 rounded-xl border border-gray-200 p-4">
                            <h3 class="mb-3 font-bold text-gray-900">{{ lado === 'principal' ? '1. Cliente que conservarás' : '2. Cliente duplicado' }}</h3>
                            <template v-if="!seleccionados[lado]">
                                <InputLabel :for="'buscar-' + lado" value="Buscar por nombre, teléfono, Instagram o correo" />
                                <TextInput :id="'buscar-' + lado" v-model="busquedas[lado]" class="mt-1 block w-full" placeholder="+57 322 5378810 o @usuario" autocomplete="off" />
                                <p v-if="buscando[lado]" class="mt-2 text-sm text-gray-500">Buscando…</p>
                                <p v-if="errorBusqueda[lado]" class="mt-2 text-sm text-red-600">{{ errorBusqueda[lado] }}</p>
                                <p v-if="!buscando[lado] && busquedas[lado].trim().length >= 2 && !resultados[lado].length && !errorBusqueda[lado]" class="mt-2 text-sm text-gray-500">No se encontraron clientes.</p>
                                <div class="mt-3 max-h-80 space-y-2 overflow-y-auto">
                                    <button v-for="cliente in resultados[lado]" :key="cliente.id" type="button" class="block w-full rounded-lg border border-gray-200 p-3 text-left hover:bg-gray-50 focus:ring-2 focus:ring-red-500" @click="seleccionar(lado, cliente)">
                                        <p class="font-bold">#{{ cliente.id }} · {{ cliente.nombre || 'Sin nombre' }}</p>
                                        <p class="break-words text-sm text-gray-600">{{ dato(cliente, 'telefono') }} · {{ dato(cliente, 'usuario') }}</p>
                                        <p class="break-all text-sm text-gray-600">{{ cliente.email || 'Sin correo' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ cliente.pedidos_count }} pedidos · {{ cliente.ventas_count }} ventas · {{ cliente.user_id ? 'Portal activo' : 'Sin cuenta del portal' }}</p>
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <p class="font-bold">#{{ seleccionados[lado].id }} · {{ seleccionados[lado].nombre || 'Sin nombre' }}</p>
                                <p class="mt-2 text-sm">{{ dato(seleccionados[lado], 'telefono') }}</p>
                                <p class="text-sm">{{ dato(seleccionados[lado], 'usuario') }}</p>
                                <p class="break-all text-sm">{{ seleccionados[lado].cuenta_portal?.email || seleccionados[lado].email || 'Sin correo' }}</p>
                                <p class="mt-2 text-sm font-semibold">{{ seleccionados[lado].user_id ? 'Portal activo: se conservará este acceso' : 'Sin cuenta del portal' }}</p>
                                <SecondaryButton type="button" class="mt-3" :disabled="revisando || form.processing" @click="cambiar(lado)">Elegir otro cliente</SecondaryButton>
                            </template>
                        </div>
                    </div>
                    <PrimaryButton type="button" class="mt-5 w-full justify-center sm:w-auto" :disabled="!seleccionados.principal || !seleccionados.duplicado || revisando || form.processing" @click="revisar">
                        <i class="fa-solid fa-magnifying-glass mr-2"></i>{{ revisando ? 'Revisando…' : 'Revisar unificación' }}
                    </PrimaryButton>
                </SectionCard>
                <SectionCard v-if="previa" title="Datos que conservarás" description="Elige el teléfono real del cliente. El correo de contacto puede cambiar; el correo para iniciar sesión se conserva." icon="fa-solid fa-address-card text-blue-500">
                    <form class="space-y-5" @submit.prevent="unificar">
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                            <p v-if="previa.correo_acceso">Acceso al portal: <strong class="break-all">{{ previa.correo_acceso }}</strong>. Se conserva la contraseña actual.</p>
                            <p v-else>Ninguno tiene cuenta activa. Podrás generar un nuevo enlace de activación después de unificar.</p>
                            <p class="mt-2">El duplicado se archivará y sus enlaces de activación dejarán de funcionar.</p>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormSelect v-for="campo in campos" :id="'conservar-' + campo.nombre" :key="campo.nombre" v-model="form.selecciones[campo.nombre]" :label="campo.etiqueta">
                                <option value="principal">#{{ previa.principal.id }}: {{ dato(previa.principal, campo.nombre) }}</option>
                                <option value="duplicado">#{{ previa.duplicado.id }}: {{ dato(previa.duplicado, campo.nombre) }}</option>
                            </FormSelect>
                        </div>
                        <div v-if="previa.principal.notas || previa.duplicado.notas" class="rounded-lg border p-4 text-sm">
                            <p class="font-bold">Se conservarán las notas de ambos registros</p>
                            <p class="mt-2 whitespace-pre-wrap break-words">{{ previa.principal.notas }}</p>
                            <p class="mt-2 whitespace-pre-wrap break-words">{{ previa.duplicado.notas }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                            <div v-for="(cantidad, tabla) in previa.transferencias" :key="tabla" class="rounded-lg bg-gray-50 p-3">
                                <p class="text-sm text-gray-600">{{ tablas[tabla] }}</p><p class="text-xl font-bold">{{ cantidad }}</p>
                            </div>
                        </div>
                        <FormTextarea id="motivo-unificacion" v-model="form.motivo" label="Motivo de la unificación" placeholder="Compró antes con teléfono y después con Instagram." :rows="3" />
                        <InputError :message="form.errors.motivo" />
                        <label class="flex items-start gap-3 text-sm text-gray-700">
                            <input v-model="form.confirmado" type="checkbox" class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500" />
                            <span>Confirmé que ambos registros pertenecen a la misma persona y que los datos elegidos son correctos.</span>
                        </label>
                        <PrimaryButton :disabled="form.processing || !form.confirmado" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-link mr-2"></i>Unificar clientes</PrimaryButton>
                    </form>
                </SectionCard>
                <SectionCard title="Historial de unificaciones" description="Registro de los clientes reunidos y el motivo de la operación." icon="fa-solid fa-clock-rotate-left text-gray-500">
                    <div class="overflow-x-auto rounded-lg shadow">
                        <table class="min-w-full border border-gray-200 text-sm">
                            <thead class="bg-gray-100"><tr><th class="px-4 py-3 text-left">Fecha</th><th class="px-4 py-3 text-left">Conservado</th><th class="px-4 py-3 text-left">Duplicado</th><th class="px-4 py-3 text-left">Motivo</th></tr></thead>
                            <tbody>
                                <tr v-for="item in historial.data" :key="item.id" class="border-t hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-4 py-3">{{ fecha(item.created_at) }}</td>
                                    <td class="px-4 py-3"><Link :href="route('clientes.show', item.principal_id_original)" class="font-bold text-blue-600">#{{ item.principal_id_original }}</Link></td>
                                    <td class="px-4 py-3">#{{ item.duplicado_id_original }}</td>
                                    <td class="min-w-[200px] px-4 py-3">{{ item.motivo }}</td>
                                </tr>
                                <tr v-if="!historial.data.length"><td colspan="4" class="px-4 py-8 text-center text-gray-500">Todavía no hay unificaciones.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="historial.links" />
                </SectionCard>
            </div>
        </template>
    </LayoutPageHeader>
</template>
