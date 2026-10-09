<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { mensajeActivacionPortal, mensajeIngresoPortal } from '@/Utils/mensajesPortal';
import 'sweetalert2/dist/sweetalert2.min.css';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';
import SectionCard from '@/Pages/Pedidos/Components/SectionCard.vue';
import ClienteFormModal from './Components/ClienteFormModal.vue';
import { confirmarOperacion, mostrarCarga, mostrarErrores } from '@/Utils/alertas';
import { copiarTexto } from '@/Utils/portapapeles';

const props = defineProps({ cliente: Object, pedidos: Object, resumen: Object, puedeEditar: Boolean, portal: Object });
const editando = ref(false);
const clienteVisible = ref({ ...props.cliente });
const dinero = (valor, moneda = 'COP') => new Intl.NumberFormat('es-CO', { style: 'currency', currency: moneda, maximumFractionDigits: 0 }).format(Number(valor || 0));
const fechaHora = (valor) => new Date(valor).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' });
const etiqueta = (estado) => String(estado || '').replaceAll('_', ' ');
const color = (estado) => ({ pagado: 'bg-green-100 text-green-800', completado: 'bg-green-100 text-green-800', parcial: 'bg-amber-100 text-amber-800', en_proceso: 'bg-blue-100 text-blue-800', cancelado: 'bg-red-100 text-red-800', pendiente: 'bg-gray-100 text-gray-800', reembolsado: 'bg-purple-100 text-purple-800', reembolso_parcial: 'bg-orange-100 text-orange-800', pago_parcial: 'bg-amber-100 text-amber-800' }[estado] ?? 'bg-gray-100 text-gray-800');
const clienteActualizado = (cliente) => { clienteVisible.value = { ...clienteVisible.value, ...cliente }; };
const generandoAcceso = ref(false);
const generarAcceso = async () => {
    const confirmado = await confirmarOperacion({ titulo: '¿Generar acceso al portal?', texto: 'Se creará un enlace privado de un solo uso para este cliente. También se intentarán vincular sus ventas antiguas.', confirmButtonText: 'Sí, generar enlace' });
    if (!confirmado) return;
    generandoAcceso.value = true;
    mostrarCarga('Generando acceso…', 'Vinculando compras y creando el enlace seguro.');
    try {
        const { data } = await axios.post(route('clientes.portal-acceso.generar', clienteVisible.value.id));
        const resultado = await Swal.fire({
            title: 'Enlace listo',
            text: 'Envía el mensaje completo al cliente. El enlace vence el ' + new Date(data.expires_at).toLocaleString('es-CO') + ' y funciona una sola vez.',
            input: 'textarea', inputValue: mensajeActivacionPortal(data.url), inputAttributes: { readonly: 'readonly' },
            icon: 'success', confirmButtonText: 'Copiar mensaje y enlace', confirmButtonColor: '#16a34a',
            showCancelButton: true, cancelButtonText: 'Cerrar', reverseButtons: true,
        });
        if (resultado.isConfirmed) {
            try {
                await copiarTexto(mensajeActivacionPortal(data.url));
                await Swal.fire({ title: 'Mensaje y enlace copiados', icon: 'success', timer: 1400, showConfirmButton: false });
            } catch (_) {
                await Swal.fire({ title: 'Enlace generado', text: 'El navegador no permitió copiarlo automáticamente. Selecciona el mensaje completo y cópialo manualmente.', input: 'textarea', inputValue: mensajeActivacionPortal(data.url), inputAttributes: { readonly: 'readonly' }, icon: 'warning', confirmButtonText: 'Cerrar' });
            }
        }
    } catch (error) {
        await mostrarErrores(error.response?.data?.errors ?? { cliente: error.response?.data?.message }, 'No se pudo generar el acceso');
    } finally {
        generandoAcceso.value = false;
    }
};
const copiarIngreso = async () => {
    const url = window.location.origin + route('login', {}, false);
    try {
        await copiarTexto(mensajeIngresoPortal(url, props.portal.email_acceso));
        await Swal.fire({ title: 'Mensaje de ingreso copiado', text: 'Ya puedes enviarlo al cliente.', icon: 'success', timer: 1700, showConfirmButton: false });
    } catch (_) {
        await Swal.fire({ title: 'Copia el mensaje de ingreso', input: 'textarea', inputValue: mensajeIngresoPortal(url, props.portal.email_acceso), inputAttributes: { readonly: 'readonly' }, icon: 'info', confirmButtonText: 'Cerrar' });
    }
};
</script>

<template>
    <Head :title="`Cliente · ${clienteVisible.nombre || clienteVisible.telefono || clienteVisible.id}`" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-xl font-semibold leading-tight text-gray-800">👤 {{ clienteVisible.nombre || 'Cliente sin nombre' }}</h2><p class="mt-1 text-sm text-gray-500">Ficha e historial del cliente #{{ clienteVisible.id }}</p></div>
                <div class="flex flex-col gap-2 sm:flex-row"><PrimaryButton v-if="puedeEditar" type="button" class="w-full justify-center sm:w-auto" @click="editando = true"><i class="fa-solid fa-user-pen mr-2"></i>Editar datos</PrimaryButton><Link :href="route('clientes.index')"><SecondaryButton type="button" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-arrow-left mr-2"></i>Volver</SecondaryButton></Link></div>
            </div>
        </template>
        <template #contenido-pagina>
            <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5"><p class="text-sm font-semibold text-blue-700"><i class="fa-solid fa-box mr-2"></i>Pedidos</p><p class="mt-2 text-2xl font-bold text-blue-900">{{ resumen.total_pedidos }}</p></div>
                    <div class="rounded-2xl border border-green-100 bg-green-50 p-5"><p class="text-sm font-semibold text-green-700"><i class="fa-solid fa-cart-shopping mr-2"></i>Ventas</p><p class="mt-2 text-2xl font-bold text-green-900">{{ resumen.total_ventas }}</p></div>
                    <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5"><p class="text-sm font-semibold text-amber-700"><i class="fa-solid fa-coins mr-2"></i>Total en pedidos</p><p class="mt-2 text-2xl font-bold text-amber-900">{{ dinero(resumen.total_comprado) }}</p></div>
                </div>

                <SectionCard title="Datos del cliente" description="Información de contacto utilizada en sus pedidos." icon="fa-solid fa-address-card text-blue-500">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-bold uppercase text-gray-500">Nombre</p><p class="mt-2 font-semibold text-gray-900">{{ clienteVisible.nombre || 'Sin registrar' }}</p></div>
                        <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-bold uppercase text-gray-500">Teléfono</p><p class="mt-2 font-semibold text-gray-900">{{ clienteVisible.telefono ? `+${clienteVisible.codigo_pais || ''} ${clienteVisible.telefono}` : 'Sin registrar' }}</p></div>
                        <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-bold uppercase text-gray-500">Instagram</p><p class="mt-2 font-semibold text-gray-900">{{ clienteVisible.usuario ? `@${clienteVisible.usuario}` : 'Sin registrar' }}</p></div>
                        <div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-bold uppercase text-gray-500">Correo</p><p class="mt-2 break-all font-semibold text-gray-900">{{ clienteVisible.email || 'Sin registrar' }}</p></div>
                        <div class="rounded-xl bg-gray-50 p-4 md:col-span-2 xl:col-span-4"><p class="text-xs font-bold uppercase text-gray-500">Notas</p><p class="mt-2 whitespace-pre-line text-gray-800">{{ clienteVisible.notas || 'Sin notas' }}</p></div>
                    </div>
                </SectionCard>

                <SectionCard title="Portal del cliente" description="Genera el acceso que enviarás en lugar de compartir las credenciales por WhatsApp." icon="fa-solid fa-shield-halved text-purple-500">
                    <div class="flex flex-col gap-5 rounded-2xl border p-5 sm:flex-row sm:items-center sm:justify-between" :class="portal.activo ? 'border-green-200 bg-green-50' : 'border-purple-200 bg-purple-50'">
                        <div><p class="font-black" :class="portal.activo ? 'text-green-900' : 'text-purple-900'"><i class="mr-2" :class="portal.activo ? 'fa-solid fa-circle-check' : 'fa-solid fa-link'"></i>{{ portal.activo ? 'Cuenta activa' : 'Acceso sin activar' }}</p><p class="mt-1 text-sm" :class="portal.activo ? 'text-green-700' : 'text-purple-700'">{{ portal.activo ? `El cliente inicia sesión con ${portal.email_acceso}.` : 'Genera un enlace privado para que el cliente cree su contraseña.' }}</p><p class="mt-2 text-xs text-gray-500">Compras históricas vinculadas: {{ portal.ventas_historicas }}</p></div>
                        <PrimaryButton v-if="puedeEditar && !portal.activo" type="button" class="w-full justify-center sm:w-auto" :disabled="generandoAcceso" @click="generarAcceso"><i class="fa-solid fa-paper-plane mr-2"></i>{{ generandoAcceso ? 'Generando…' : 'Generar enlace' }}</PrimaryButton>
                        <PrimaryButton v-else-if="portal.activo" type="button" class="w-full justify-center sm:w-auto" @click="copiarIngreso"><i class="fa-solid fa-copy mr-2"></i>Copiar mensaje de ingreso</PrimaryButton>
                    </div>
                </SectionCard>

                <SectionCard title="Historial de pedidos" description="Pedidos registrados para este cliente." icon="fa-solid fa-clock-rotate-left text-red-500">
                    <div class="overflow-x-auto rounded-lg shadow">
                        <table class="min-w-full border border-gray-200 text-sm">
                            <thead><tr class="border-b bg-gray-100"><th class="px-4 py-3 text-left">Pedido</th><th class="px-4 py-3 text-left">Total</th><th class="px-4 py-3 text-left">Finanzas</th><th class="px-4 py-3 text-left">Entrega</th><th class="px-4 py-3 text-left">Estado</th><th class="sticky right-0 z-10 bg-gray-100 px-4 py-3 text-center">Acción</th></tr></thead>
                            <tbody>
                                <tr v-for="pedido in pedidos.data" :key="pedido.id" class="border-b hover:bg-gray-50"><td class="px-4 py-3"><p class="font-bold text-gray-900">{{ pedido.codigo }}</p><p class="mt-1 text-xs text-gray-500">{{ fechaHora(pedido.created_at) }}</p></td><td class="px-4 py-3"><p class="font-bold text-green-800">{{ dinero(pedido.total, pedido.moneda) }}</p><p class="mt-1 text-xs text-gray-500">Pagado: {{ dinero(pedido.total_pagado, pedido.moneda) }}</p></td><td class="px-4 py-3"><span class="inline-flex rounded-lg px-3 py-1 text-xs font-bold" :class="color(pedido.estado_financiero)">{{ etiqueta(pedido.estado_financiero) }}</span></td><td class="px-4 py-3"><span class="inline-flex rounded-lg px-3 py-1 text-xs font-bold" :class="color(pedido.estado_entrega)">{{ etiqueta(pedido.estado_entrega) }}</span></td><td class="px-4 py-3"><span class="inline-flex rounded-lg px-3 py-1 text-xs font-bold" :class="color(pedido.estado)">{{ etiqueta(pedido.estado) }}</span></td><td class="sticky right-0 bg-white px-4 py-3 text-center"><Link :href="route('pedidos.show', pedido.id)"><SecondaryButton type="button" class="h-9 w-9 justify-center" title="Ver pedido"><i class="fa-solid fa-eye"></i></SecondaryButton></Link></td></tr>
                                <tr v-if="!pedidos.data.length"><td colspan="6" class="px-4 py-12 text-center text-gray-500"><i class="fa-solid fa-box-open mr-2"></i>Este cliente todavía no tiene pedidos.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="pedidos.links" />
                </SectionCard>
            </div>
            <ClienteFormModal :show="editando" :cliente="clienteVisible" @close="editando = false" @updated="clienteActualizado" />
        </template>
    </LayoutPageHeader>
</template>
