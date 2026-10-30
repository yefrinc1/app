<script setup>
import PortalClienteLayout from '@/Layouts/PortalClienteLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({ pedido: Object });
const fecha = (valor) => valor ? new Date(valor).toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
const colorEstado = (clave) => ({
    completado: 'bg-emerald-500 text-white',
    entregado: 'bg-emerald-500 text-white',
    parcial: 'bg-amber-500 text-white',
    reembolsado: 'bg-purple-500 text-white',
    reembolso_parcial: 'bg-orange-500 text-white',
    cancelado: 'bg-red-600 text-white',
    pago: 'bg-sky-500 text-white',
    proceso: 'bg-sky-500 text-white',
    pendiente: 'bg-white/15 text-slate-200',
}[clave] ?? 'bg-white/15 text-slate-200');
const mensajeProceso = computed(() => ({
    pago: 'Estamos esperando el pago para continuar con tu pedido.',
    parcial: 'Estamos preparando el resto de tus juegos.',
    proceso: 'Estamos preparando tus juegos. Esto puede tardar unos minutos.',
}[props.pedido.estado.clave] ?? 'Tu pedido está en proceso.'));
const portadas = computed(() => (props.pedido.juegos || []).slice(0, 4));

let intervalo = null;
onMounted(() => {
    intervalo = setInterval(() => {
        if (!props.pedido.en_proceso) return;
        router.reload({ only: ['pedido'], preserveScroll: true });
    }, 8000);
});
onUnmounted(() => {
    if (intervalo) clearInterval(intervalo);
});
</script>

<template>
    <PortalClienteLayout>
        <Head :title="pedido.codigo" />
        <Link :href="route('portal.pedidos.index')" class="inline-flex items-center text-sm font-semibold text-slate-300 hover:text-white">
            <i class="fa-solid fa-arrow-left mr-2"></i>Volver a mis pedidos
        </Link>

        <section class="mt-5 overflow-hidden rounded-2xl border" :class="pedido.reembolsado || pedido.reembolso_parcial ? 'border-purple-400/30 bg-purple-500/10' : 'border-white/10 bg-slate-900'">
            <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
                <div class="flex justify-center sm:justify-start">
                    <div class="flex items-end" :class="portadas.length > 1 ? '-space-x-6' : ''">
                        <div
                            v-for="(detalle, indice) in portadas"
                            :key="detalle.id"
                            class="relative aspect-[3/4] w-24 overflow-hidden rounded-xl border-2 border-slate-950 bg-slate-950 shadow-xl sm:w-28 lg:w-32"
                            :style="{ zIndex: portadas.length - indice }"
                        >
                            <img v-if="detalle.imagen" :src="detalle.imagen" :alt="detalle.juego" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full items-center justify-center text-3xl">🎮</div>
                        </div>
                    </div>
                </div>
                <div class="min-w-0 flex-1 text-center sm:text-left">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em]" :class="pedido.reembolsado || pedido.reembolso_parcial ? 'text-purple-300' : 'text-slate-500'">Pedido</p>
                    <h1 class="mt-1 text-3xl font-black text-white">{{ pedido.codigo }}</h1>
                    <p class="mt-2 text-sm text-slate-400">{{ fecha(pedido.fecha) }} · {{ pedido.total }} · {{ pedido.juegos.length }} juego(s)</p>
                    <span class="mt-4 inline-flex rounded-md px-2.5 py-1 text-xs font-bold" :class="colorEstado(pedido.estado.clave)">{{ pedido.estado.texto }}</span>
                </div>
            </div>
            <div v-if="pedido.mensaje_reembolso" class="border-t border-purple-300/20 bg-black/20 px-5 py-4 sm:px-6">
                <p class="font-black text-purple-100"><i class="fa-solid fa-rotate-left mr-2"></i>{{ pedido.reembolsado ? 'Pedido reembolsado' : 'Reembolso parcial' }}</p>
                <p class="mt-1 text-sm text-purple-50/90">{{ pedido.mensaje_reembolso }}</p>
            </div>
            <div v-if="pedido.en_proceso" class="border-t border-sky-400/20 bg-sky-500/10 px-5 py-4 sm:px-6">
                <div class="flex items-center justify-between gap-3 text-sm font-bold text-sky-100">
                    <span><i class="fa-solid fa-spinner fa-spin mr-2"></i>{{ mensajeProceso }}</span>
                    <span class="text-xs">Cargando</span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-black/30">
                    <div class="barra-carga h-full w-2/5 rounded-full bg-gradient-to-r from-sky-400 via-red-500 to-amber-400"></div>
                </div>
            </div>
        </section>

        <section class="mt-8">
            <h2 class="text-2xl font-black">Juegos de este pedido</h2>
            <p class="mt-1 text-sm text-slate-400">{{ pedido.reembolsado ? 'Este pedido no entrega cuentas porque fue reembolsado.' : 'Abre un juego para ver los datos de instalación.' }}</p>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <article
                    v-for="detalle in pedido.juegos"
                    :key="detalle.id"
                    class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900 transition hover:border-red-500/40"
                >
                    <div class="flex items-stretch">
                        <div class="aspect-[3/4] w-28 shrink-0 overflow-hidden bg-slate-950 sm:w-36 lg:w-44">
                            <img v-if="detalle.imagen" :src="detalle.imagen" :alt="detalle.juego" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full items-center justify-center text-4xl">🎮</div>
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col p-4 sm:p-5">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-md bg-red-500/15 px-2.5 py-1 text-[11px] font-bold text-red-300">{{ detalle.tipo_cuenta }}</span>
                                <span class="rounded-md bg-white/10 px-2.5 py-1 text-[11px] font-bold text-slate-200">{{ detalle.consola }}</span>
                                <span class="rounded-md bg-white/10 px-2.5 py-1 text-[11px] font-bold text-slate-300">{{ detalle.cantidad }} unidad(es)</span>
                            </div>
                            <h3 class="mt-3 text-xl font-black leading-tight text-white">{{ detalle.juego }}</h3>

                            <div v-if="detalle.cuentas.length" class="mt-4 space-y-2">
                                <Link
                                    v-for="(cuenta, indice) in detalle.cuentas"
                                    :key="cuenta.id"
                                    :href="cuenta.url"
                                    class="flex items-center justify-between gap-3 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-red-500"
                                >
                                    <span>Cuenta {{ indice + 1 }} · {{ cuenta.estado }}</span>
                                    <span>Ver datos <i class="fa-solid fa-arrow-right ml-1"></i></span>
                                </Link>
                            </div>
                            <p v-if="pedido.reembolsado" class="mt-4 rounded-xl border border-purple-300/20 bg-purple-500/10 px-4 py-3 text-sm text-purple-100">
                                Reembolsado. Esta cuenta ya no está disponible.
                            </p>
                            <p v-else-if="detalle.pendientes > 0" class="mt-4 rounded-xl border border-amber-400/20 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
                                {{ detalle.pendientes }} unidad(es) pendiente(s) de entrega.
                            </p>
                            <p v-else-if="!detalle.cuentas.length" class="mt-4 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
                                Esta cuenta todavía no está lista para instalar.
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </PortalClienteLayout>
</template>

<style scoped>
@keyframes pedido-carga {
    0% { transform: translateX(-120%); }
    100% { transform: translateX(280%); }
}
.barra-carga {
    animation: pedido-carga 1.5s ease-in-out infinite;
}
</style>
