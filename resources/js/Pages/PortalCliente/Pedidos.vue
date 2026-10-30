<script setup>
import PortalClienteLayout from '@/Layouts/PortalClienteLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({ pedidos: Object });
const fecha = (valor) => valor ? new Date(valor).toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
const colorEstado = (clave) => ({
    completado: 'bg-green-500/15 text-green-300',
    entregado: 'bg-green-500/15 text-green-300',
    parcial: 'bg-amber-500/15 text-amber-300',
    reembolsado: 'bg-purple-500/15 text-purple-300',
    reembolso_parcial: 'bg-orange-500/15 text-orange-300',
    cancelado: 'bg-red-500/15 text-red-300',
    pago: 'bg-blue-500/15 text-blue-300',
    proceso: 'bg-blue-500/15 text-blue-300',
    pendiente: 'bg-white/10 text-slate-300',
}[clave] ?? 'bg-white/10 text-slate-300');
const mensajeProceso = (pedido) => ({
    pago: 'Estamos esperando el pago para continuar con tu pedido.',
    parcial: 'Estamos preparando el resto de tus juegos.',
    proceso: 'Estamos preparando tus juegos. Esto puede tardar unos minutos.',
}[pedido.estado.clave] ?? 'Tu pedido está en proceso.');
const hayEnProceso = computed(() => (props.pedidos.data || []).some((pedido) => pedido.en_proceso));

let intervalo = null;
onMounted(() => {
    intervalo = setInterval(() => {
        if (!hayEnProceso.value) return;
        router.reload({ only: ['pedidos'], preserveScroll: true });
    }, 8000);
});
onUnmounted(() => {
    if (intervalo) clearInterval(intervalo);
});
</script>

<template>
    <PortalClienteLayout>
        <Head title="Mis pedidos" />
        <section class="mb-8">
            <h1 class="text-3xl font-black">Mis pedidos</h1>
            <p class="mt-2 text-sm text-slate-400">Consulta el estado de tus compras y entra a cada pedido para ver si ya puedes instalar los juegos.</p>
        </section>

        <div v-if="pedidos.data.length" class="space-y-4">
            <article v-for="pedido in pedidos.data" :key="pedido.id" class="overflow-hidden rounded-2xl border p-5" :class="pedido.reembolsado || pedido.reembolso_parcial ? 'border-purple-400/30 bg-purple-500/10' : 'border-white/10 bg-white/5'">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-black">{{ pedido.codigo }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ fecha(pedido.fecha) }} · {{ pedido.total }}</p>
                        <p v-if="pedido.mensaje_reembolso" class="mt-2 text-sm font-bold text-purple-200">{{ pedido.reembolsado ? 'Pedido reembolsado' : 'Reembolso parcial' }}</p>
                    </div>
                    <span class="self-start rounded-full px-3 py-1 text-xs font-bold" :class="colorEstado(pedido.estado.clave)">{{ pedido.estado.texto }}</span>
                </div>

                <div v-if="pedido.en_proceso" class="mt-4 rounded-xl border border-sky-400/20 bg-sky-500/10 px-4 py-3">
                    <div class="flex items-center justify-between gap-3 text-xs font-bold text-sky-100">
                        <span><i class="fa-solid fa-spinner fa-spin mr-2"></i>{{ mensajeProceso(pedido) }}</span>
                        <span class="shrink-0">Cargando</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-black/30">
                        <div class="barra-carga h-full w-2/5 rounded-full bg-gradient-to-r from-sky-400 via-red-500 to-amber-400"></div>
                    </div>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    <div v-for="detalle in pedido.juegos" :key="detalle.id" class="flex items-center gap-3 rounded-xl bg-black/20 p-2.5">
                        <div class="aspect-[3/4] w-14 shrink-0 overflow-hidden rounded-lg bg-slate-950">
                            <img v-if="detalle.imagen" :src="detalle.imagen" :alt="detalle.juego" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full items-center justify-center text-xl">🎮</div>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-bold">{{ detalle.juego }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ detalle.tipo_cuenta }} · {{ detalle.consola }} · {{ detalle.cantidad }} unidad(es)</p>
                        </div>
                    </div>
                </div>
                <Link
                    v-if="pedido.puede_ver_detalle"
                    :href="route('portal.pedidos.show', pedido.id)"
                    class="mt-4 inline-flex items-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-red-500"
                >
                    Ver detalle <i class="fa-solid fa-arrow-right ml-2"></i>
                </Link>
            </article>
        </div>

        <div v-else class="rounded-3xl border border-dashed border-white/20 bg-white/5 px-6 py-14 text-center">
            <div class="text-5xl">🧾</div>
            <h3 class="mt-4 text-xl font-black">Aún no tienes pedidos</h3>
            <p class="mx-auto mt-2 max-w-lg text-sm text-slate-400">Cuando MRJUEGOZ registre una compra a tu nombre, aparecerá aquí.</p>
            <Link :href="route('portal.index')" class="mt-6 inline-flex rounded-xl bg-red-600 px-5 py-3 text-sm font-black text-white hover:bg-red-500">Ir a mis juegos</Link>
        </div>

        <nav v-if="pedidos.links?.length > 3" class="mt-8 flex flex-wrap justify-center gap-2">
            <template v-for="(link, indice) in pedidos.links" :key="indice">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-xl border px-3 py-2 text-xs font-bold"
                    :class="link.active ? 'border-red-500 bg-red-600 text-white' : 'border-white/10 bg-white/5 text-slate-300 hover:border-red-500/40'"
                    v-html="link.label"
                />
                <span v-else class="rounded-xl border border-transparent px-3 py-2 text-xs text-slate-600" v-html="link.label" />
            </template>
        </nav>
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
