<script setup>
import PortalClienteLayout from '@/Layouts/PortalClienteLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ cliente: Object, juegos: Array, pedidos: Array, resumen: Object });
const fecha = (valor) => valor ? new Date(valor).toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
const etiqueta = (valor) => String(valor || '').replaceAll('_', ' ');
const color = (estado) => ({ completado: 'bg-green-500/15 text-green-300', pagado: 'bg-green-500/15 text-green-300', reembolsado: 'bg-purple-500/15 text-purple-300', reembolso_parcial: 'bg-orange-500/15 text-orange-300', parcial: 'bg-amber-500/15 text-amber-300', pago_parcial: 'bg-amber-500/15 text-amber-300', en_proceso: 'bg-blue-500/15 text-blue-300', cancelado: 'bg-red-500/15 text-red-300', pendiente: 'bg-white/10 text-slate-300' }[estado] ?? 'bg-white/10 text-slate-300');
const garantiaTexto = (estado) => ({ activa: 'Garantía activa', en_revision: 'En revisión', requiere_correccion: 'Requiere corrección', pendiente: 'Evidencia pendiente' }[estado] ?? 'Evidencia pendiente');
const garantiaColor = (estado) => ({ activa: 'bg-green-600 text-white', en_revision: 'bg-blue-600 text-white', requiere_correccion: 'bg-amber-500 text-slate-950', pendiente: 'bg-slate-700 text-slate-100' }[estado] ?? 'bg-slate-700 text-slate-100');
</script>

<template>
    <PortalClienteLayout>
        <Head title="Mis juegos" />
        <section class="overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-red-600 via-red-700 to-slate-950 p-6 shadow-2xl sm:p-8">
            <p class="text-sm font-bold uppercase tracking-[0.22em] text-red-100">Bienvenido a tu cuenta</p><h1 class="mt-2 text-3xl font-black sm:text-4xl">Hola, {{ cliente.nombre || 'jugador' }} 👋</h1><p class="mt-3 max-w-2xl text-red-100">Aquí encontrarás los datos de tus juegos, códigos disponibles y tutoriales de instalación. Ya no tendrás que buscar las credenciales en conversaciones antiguas.</p>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-3"><div class="rounded-2xl border border-white/10 bg-white/5 p-5"><p class="text-sm text-slate-400">Juegos disponibles</p><p class="mt-2 text-3xl font-black">{{ resumen.total }}</p></div><div class="rounded-2xl border border-white/10 bg-white/5 p-5"><p class="text-sm text-slate-400">Pedidos actuales</p><p class="mt-2 text-3xl font-black">{{ resumen.pedidos }}</p></div><div class="rounded-2xl border border-white/10 bg-white/5 p-5"><p class="text-sm text-slate-400">Compras históricas</p><p class="mt-2 text-3xl font-black">{{ resumen.historicos }}</p></div></section>

        <section class="mt-10"><div class="mb-5"><h2 class="text-2xl font-black">Mis juegos</h2><p class="mt-1 text-sm text-slate-400">Selecciona un juego para consultar sus datos de acceso.</p></div>
            <div v-if="juegos.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <article v-for="juego in juegos" :key="`${juego.origen}-${juego.id}`" class="group min-w-0 overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-lg transition hover:-translate-y-1 hover:border-red-500/60">
                    <div class="relative aspect-square overflow-hidden bg-slate-950 p-2"><img v-if="juego.imagen" :src="juego.imagen" :alt="juego.juego" class="h-full w-full object-contain" /><div v-else class="flex h-full items-center justify-center text-5xl">🎮</div><span class="absolute left-3 top-3 rounded-full bg-black/75 px-3 py-1 text-xs font-bold shadow-sm backdrop-blur">{{ juego.consola }}</span><span class="absolute bottom-3 right-3 rounded-full px-3 py-1 text-[11px] font-black shadow-lg" :class="garantiaColor(juego.garantia_estado)"><i class="fa-solid fa-shield-halved mr-1"></i>{{ garantiaTexto(juego.garantia_estado) }}</span></div>
                    <div class="p-4"><p class="truncate text-[11px] font-bold uppercase tracking-wider text-red-400">{{ juego.tipo_cuenta }} · {{ juego.origen === 'historica' ? 'Compra anterior' : juego.pedido }}</p><h3 class="mt-2 line-clamp-2 min-h-[48px] text-lg font-black leading-6">{{ juego.juego }}</h3><p class="mt-2 text-xs text-slate-500">{{ fecha(juego.fecha) }}</p><Link :href="route('portal.juegos.show', [juego.origen, juego.id])" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-red-500"><i class="fa-solid fa-eye mr-2"></i>Ver datos e instalación</Link></div>
                </article>
            </div>
            <div v-else class="rounded-3xl border border-dashed border-white/20 bg-white/5 px-6 py-14 text-center"><div class="text-5xl">🎮</div><h3 class="mt-4 text-xl font-black">Todavía no aparecen juegos</h3><p class="mx-auto mt-2 max-w-lg text-sm text-slate-400">Si ya realizaste una compra, comunícate con MRJUEGOZ para revisar la vinculación de tu teléfono o usuario.</p></div>
        </section>

        <section v-if="pedidos.length" class="mt-12"><div class="mb-5"><h2 class="text-2xl font-black">Mis pedidos</h2><p class="mt-1 text-sm text-slate-400">Consulta el avance de tus compras actuales.</p></div><div class="space-y-4"><article v-for="pedido in pedidos" :key="pedido.id" class="rounded-2xl border border-white/10 bg-white/5 p-5"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-black">{{ pedido.codigo }}</p><p class="mt-1 text-xs text-slate-500">{{ fecha(pedido.created_at) }}</p></div><div class="flex flex-wrap gap-2"><span class="rounded-full px-3 py-1 text-xs font-bold" :class="color(pedido.estado_financiero)">Finanzas: {{ etiqueta(pedido.estado_financiero) }}</span><span class="rounded-full px-3 py-1 text-xs font-bold" :class="color(pedido.estado_entrega)">Entrega: {{ etiqueta(pedido.estado_entrega) }}</span></div></div><div class="mt-4 grid gap-2 sm:grid-cols-2"><div v-for="detalle in pedido.detalles" :key="detalle.id" class="rounded-xl bg-black/20 p-3"><p class="font-bold">{{ detalle.juego }}</p><p class="mt-1 text-xs text-slate-400">{{ detalle.tipo_cuenta }} · {{ detalle.consola }} · {{ detalle.cantidad }} unidad(es)</p></div></div></article></div></section>
    </PortalClienteLayout>
</template>
