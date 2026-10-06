<script setup>
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const props = defineProps({ evidencias: Object, filtros: Object, conteos: Object });
const filtro = reactive({ estado: props.filtros?.estado ?? '', buscar: props.filtros?.buscar ?? '' });
const evidenciaVisible = ref(null);
const fechaHora = (valor) => valor ? new Date(valor).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const tamano = (bytes) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
const contacto = (cliente) => cliente?.nombre || cliente?.telefono || cliente?.usuario || cliente?.email || `Cliente #${cliente?.id}`;
const estadoClase = (estado) => ({ en_revision: 'bg-blue-100 text-blue-800', aprobada: 'bg-green-100 text-green-800', rechazada: 'bg-red-100 text-red-800' }[estado] ?? 'bg-gray-100 text-gray-700');
const accionTexto = (accion) => ({ ver_datos: 'Abrió los datos', copiar_usuario: 'Copió el usuario', copiar_contrasena: 'Copió la contraseña', copiar_codigo: 'Copió el código', copiar_todos: 'Copió todos los datos', abrir_tutorial: 'Abrió el tutorial' }[accion] ?? accion.replaceAll('_', ' '));
const buscar = () => router.get(route('garantias.evidencias.index'), { ...filtro }, { preserveState: true, replace: true });
const cambiarEstado = (estado) => { filtro.estado = estado; buscar(); };
const mostrarError = (errores, titulo) => Swal.fire({ title: titulo, text: Object.values(errores || {}).join('\n') || 'Ocurrió un error inesperado.', icon: 'error', confirmButtonColor: '#dc2626' });
const aprobar = async (evidencia) => {
    const resultado = await Swal.fire({ title: '¿Aprobar esta evidencia?', text: 'La garantía del juego quedará activa.', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, aprobar', cancelButtonText: 'Cancelar', confirmButtonColor: '#16a34a', reverseButtons: true });
    if (!resultado.isConfirmed) return;
    router.patch(route('garantias.evidencias.aprobar', evidencia.id), {}, {
        preserveScroll: true,
        onStart: () => Swal.fire({ title: 'Aprobando evidencia…', allowOutsideClick: false, didOpen: () => Swal.showLoading() }),
        onError: (errores) => mostrarError(errores, 'No se pudo aprobar'),
        onSuccess: () => Swal.fire({ title: 'Garantía activada', icon: 'success', timer: 1600, showConfirmButton: false }),
    });
};
const rechazar = async (evidencia) => {
    const resultado = await Swal.fire({ title: 'Solicitar corrección', input: 'textarea', inputLabel: 'Explica claramente qué debe corregir el cliente', inputPlaceholder: 'Ejemplo: el video no muestra que Uso compartido está desactivado…', inputAttributes: { maxlength: '1000' }, showCancelButton: true, confirmButtonText: 'Rechazar y solicitar otra', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626', reverseButtons: true, inputValidator: (valor) => !valor || valor.trim().length < 10 ? 'Escribe un motivo de mínimo 10 caracteres.' : undefined });
    if (!resultado.isConfirmed) return;
    router.patch(route('garantias.evidencias.rechazar', evidencia.id), { motivo_rechazo: resultado.value.trim() }, {
        preserveScroll: true,
        onStart: () => Swal.fire({ title: 'Guardando revisión…', allowOutsideClick: false, didOpen: () => Swal.showLoading() }),
        onError: (errores) => mostrarError(errores, 'No se pudo rechazar'),
        onSuccess: () => Swal.fire({ title: 'Corrección solicitada', text: 'El cliente podrá enviar una nueva evidencia.', icon: 'success', timer: 1900, showConfirmButton: false }),
    });
};
</script>

<template>
    <Head title="Revisión de garantías" />
    <LayoutPageHeader>
        <template #titulo-pagina><div><h2 class="text-xl font-semibold leading-tight text-gray-800">🛡️ Revisión de garantías</h2><p class="mt-1 text-sm text-gray-500">Valida que cada cuenta haya sido instalada con la configuración correcta.</p></div></template>
        <template #contenido-pagina>
            <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-3"><button type="button" class="rounded-2xl border p-5 text-left transition" :class="filtro.estado === 'en_revision' ? 'border-blue-500 bg-blue-50 ring-2 ring-blue-100' : 'border-blue-100 bg-white hover:bg-blue-50'" @click="cambiarEstado('en_revision')"><p class="text-sm font-bold text-blue-700">Pendientes de revisión</p><p class="mt-2 text-3xl font-black text-blue-900">{{ conteos.en_revision }}</p></button><button type="button" class="rounded-2xl border p-5 text-left transition" :class="filtro.estado === 'aprobada' ? 'border-green-500 bg-green-50 ring-2 ring-green-100' : 'border-green-100 bg-white hover:bg-green-50'" @click="cambiarEstado('aprobada')"><p class="text-sm font-bold text-green-700">Aprobadas</p><p class="mt-2 text-3xl font-black text-green-900">{{ conteos.aprobada }}</p></button><button type="button" class="rounded-2xl border p-5 text-left transition" :class="filtro.estado === 'rechazada' ? 'border-red-500 bg-red-50 ring-2 ring-red-100' : 'border-red-100 bg-white hover:bg-red-50'" @click="cambiarEstado('rechazada')"><p class="text-sm font-bold text-red-700">Requieren corrección</p><p class="mt-2 text-3xl font-black text-red-900">{{ conteos.rechazada }}</p></button></div>

                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"><form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="buscar"><input v-model="filtro.buscar" type="search" placeholder="Buscar cliente, teléfono, usuario o juego…" class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500" /><select v-model="filtro.estado" class="rounded-lg border-gray-300 text-sm focus:border-gray-500 focus:ring-gray-500"><option value="">Todos los estados</option><option value="en_revision">En revisión</option><option value="aprobada">Aprobadas</option><option value="rechazada">Requieren corrección</option></select><button type="submit" class="rounded-lg bg-gray-800 px-5 py-2.5 text-sm font-bold text-white hover:bg-gray-700"><i class="fa-solid fa-magnifying-glass mr-2"></i>Buscar</button></form></div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm"><thead class="bg-gray-50"><tr><th class="px-4 py-3 text-left font-bold text-gray-600">Cliente y juego</th><th class="px-4 py-3 text-left font-bold text-gray-600">Evidencia</th><th class="px-4 py-3 text-left font-bold text-gray-600">Estado</th><th class="px-4 py-3 text-left font-bold text-gray-600">Enviada</th><th class="sticky right-0 bg-gray-50 px-4 py-3 text-center font-bold text-gray-600">Acciones</th></tr></thead><tbody class="divide-y divide-gray-100"><tr v-for="evidencia in evidencias.data" :key="evidencia.id" class="hover:bg-gray-50"><td class="px-4 py-4"><p class="font-black text-gray-900">{{ contacto(evidencia.cliente) }}</p><p class="mt-1 font-semibold text-gray-700">{{ evidencia.juego }}</p><p class="mt-1 text-xs text-gray-500">{{ evidencia.tipo_cuenta }} · {{ evidencia.consola }} · {{ evidencia.pedido || evidencia.origen }}</p></td><td class="px-4 py-4"><p class="font-bold capitalize text-gray-800"><i :class="evidencia.tipo_archivo === 'foto' ? 'fa-solid fa-image' : 'fa-solid fa-video'" class="mr-2 text-gray-500"></i>{{ evidencia.tipo_archivo }}</p><p class="mt-1 text-xs text-gray-500">{{ tamano(evidencia.tamano_bytes) }}</p></td><td class="px-4 py-4"><span class="inline-flex rounded-full px-3 py-1 text-xs font-black capitalize" :class="estadoClase(evidencia.estado)">{{ evidencia.estado.replaceAll('_', ' ') }}</span><p v-if="evidencia.motivo_rechazo" class="mt-2 max-w-xs text-xs text-red-600">{{ evidencia.motivo_rechazo }}</p><p v-if="evidencia.revisor" class="mt-1 text-xs text-gray-400">Por {{ evidencia.revisor }}</p></td><td class="whitespace-nowrap px-4 py-4 text-gray-600">{{ fechaHora(evidencia.enviado_at) }}</td><td class="sticky right-0 bg-white px-4 py-4"><div class="flex justify-center gap-2"><button type="button" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100" title="Ver evidencia" @click="evidenciaVisible = evidencia"><i class="fa-solid fa-eye"></i></button><button v-if="evidencia.estado === 'en_revision'" type="button" class="flex h-9 w-9 items-center justify-center rounded-lg bg-green-600 text-white transition active:scale-95 active:bg-green-700" title="Aprobar" @click="aprobar(evidencia)"><i class="fa-solid fa-check"></i></button><button v-if="evidencia.estado === 'en_revision'" type="button" class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-600 text-white transition active:scale-95 active:bg-red-700" title="Solicitar corrección" @click="rechazar(evidencia)"><i class="fa-solid fa-xmark"></i></button></div></td></tr><tr v-if="!evidencias.data.length"><td colspan="5" class="px-6 py-14 text-center text-gray-500"><i class="fa-solid fa-shield-halved mr-2"></i>No hay evidencias con estos filtros.</td></tr></tbody></table></div><div class="border-t border-gray-100 p-4"><Pagination :links="evidencias.links" /></div></div>
            </div>
        </template>
    </LayoutPageHeader>

    <Teleport to="body"><div v-if="evidenciaVisible" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4" @click.self="evidenciaVisible = null"><div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-gray-950 text-white"><div class="flex items-center justify-between border-b border-white/10 px-4 py-3"><div><p class="font-black">{{ evidenciaVisible.juego }}</p><p class="text-xs text-gray-400">{{ contacto(evidenciaVisible.cliente) }} · {{ fechaHora(evidenciaVisible.enviado_at) }}</p></div><button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 hover:bg-white/20" @click="evidenciaVisible = null"><i class="fa-solid fa-xmark"></i></button></div><div class="min-h-0 flex-1 overflow-auto p-4"><div class="rounded-xl bg-black/30 p-3"><img v-if="evidenciaVisible.tipo_archivo === 'foto'" :src="evidenciaVisible.archivo_url" alt="Evidencia de instalación" class="mx-auto max-h-[60vh] max-w-full object-contain" /><video v-else :src="evidenciaVisible.archivo_url" controls playsinline class="mx-auto max-h-[60vh] max-w-full"></video></div><div class="mt-4 rounded-xl border border-white/10 bg-white/5 p-4"><h3 class="font-black"><i class="fa-solid fa-clock-rotate-left mr-2 text-red-400"></i>Últimos accesos a las credenciales</h3><div v-if="evidenciaVisible.accesos.length" class="mt-3 divide-y divide-white/10"><div v-for="(acceso, indice) in evidenciaVisible.accesos" :key="indice" class="grid gap-1 py-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto]"><div><p class="font-bold">{{ accionTexto(acceso.accion) }}</p><p class="mt-1 break-all text-xs text-gray-400">IP {{ acceso.ip || 'No disponible' }} · {{ acceso.user_agent || 'Navegador no identificado' }}</p></div><p class="text-xs text-gray-400">{{ fechaHora(acceso.created_at) }}</p></div></div><p v-else class="mt-3 text-sm text-gray-400">No hay accesos registrados para este juego.</p></div></div></div></div></Teleport>
</template>
