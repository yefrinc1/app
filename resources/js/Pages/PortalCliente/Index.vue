<script setup>
import PortalClienteLayout from '@/Layouts/PortalClienteLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ cliente: Object, juegos: Array, resumen: Object, oferta: Object, mostrar_oferta: Boolean });
const busqueda = ref('');
const consola = ref('');
const ofertaVisible = ref(Boolean(props.mostrar_oferta && props.oferta?.juegos?.length));
const fecha = (valor) => valor ? new Date(valor).toLocaleDateString('es-CO', { day: '2-digit', month: 'short' }) : '';
const dinero = (valor) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(valor || 0));
const garantiaTexto = (estado) => ({ activa: 'Garantía activa', antigua: 'Garantía antigua' }[estado] ?? 'Sin garantía');
const garantiaColor = (estado) => ({ activa: 'bg-emerald-500 text-white', antigua: 'bg-amber-400 text-slate-900' }[estado] ?? 'bg-red-600 text-white');
const esGaleria = computed(() => props.oferta?.presentacion === 'galeria' || props.oferta?.clave === 'varios');
const imagenOferta = computed(() => props.oferta?.juegos?.find((juego) => juego.imagen)?.imagen || null);
const ofertasPorTipo = (tipo) => (props.oferta?.juegos || []).filter((juego) => String(juego.tipo).toLowerCase() === tipo);
const ofertasPrimaria = computed(() => ofertasPorTipo('primaria'));
const ofertasSecundaria = computed(() => ofertasPorTipo('secundaria'));
const columnasOferta = computed(() => [ofertasPrimaria.value.length && { titulo: 'Primaria', juegos: ofertasPrimaria.value }, ofertasSecundaria.value.length && { titulo: 'Secundaria', juegos: ofertasSecundaria.value }].filter(Boolean));
const mensajeWhatsapp = (juego) => {
    const detalle = [juego.tipo, juego.consola].filter(Boolean).join(' ');
    const lineas = [
        `Hola MRJUEGOZ, vi la oferta de ${props.oferta.titulo} en el portal y quiero pedir:`,
        `${juego.nombre} · ${detalle} · ${dinero(juego.precio)}`,
    ];
    return `https://wa.me/${props.oferta.whatsapp}?text=${encodeURIComponent(lineas.join('\n'))}`;
};
const precioDesde = computed(() => {
    const precios = (props.oferta?.juegos || []).map((juego) => Number(juego.precio || 0)).filter((precio) => precio > 0);
    return precios.length ? Math.min(...precios) : 0;
});
const abrirOferta = () => {
    if (props.oferta?.juegos?.length) ofertaVisible.value = true;
};
const cerrarOferta = () => {
    ofertaVisible.value = false;
};
const juegosFiltrados = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();
    return props.juegos.filter((juego) => {
        const coincideTexto = !texto || [juego.juego, juego.tipo_cuenta, juego.pedido].filter(Boolean).join(' ').toLowerCase().includes(texto);
        const coincideConsola = !consola.value || juego.consola === consola.value;
        return coincideTexto && coincideConsola;
    });
});
</script>

<template>
    <PortalClienteLayout>
        <Head title="Mis juegos" />

        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-3xl font-black">Mis juegos</h1>
                <p class="mt-1 text-sm text-slate-400">Hola, {{ cliente.nombre || 'jugador' }}. Elige un juego para ver los datos de instalación.</p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <label class="sr-only" for="buscar-juego">Buscar juego</label>
                <input
                    id="buscar-juego"
                    v-model="busqueda"
                    type="search"
                    placeholder="Buscar juego..."
                    class="w-full rounded-xl border-white/10 bg-white/5 text-sm text-white placeholder:text-slate-500 focus:border-red-500 focus:ring-red-500 sm:w-64"
                />
                <select
                    v-model="consola"
                    class="rounded-xl border-white/10 bg-slate-900 text-sm text-white focus:border-red-500 focus:ring-red-500"
                    aria-label="Filtrar por consola"
                >
                    <option value="">Todas las consolas</option>
                    <option value="PS4">PS4</option>
                    <option value="PS5">PS5</option>
                </select>
            </div>
        </div>

        <div v-if="oferta?.juegos?.length" class="mb-6 flex flex-col gap-3 rounded-2xl border border-red-500/30 bg-gradient-to-r from-red-600/20 to-slate-900 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wide text-red-300">Oferta</p>
                <h2 class="mt-1 text-lg font-black text-white">{{ oferta.titulo }}<span v-if="precioDesde"> · desde {{ dinero(precioDesde) }}</span></h2>
                <p class="mt-1 text-sm text-slate-300">{{ oferta.subtitulo }}</p>
            </div>
            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                <button type="button" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-black text-white hover:bg-red-500" @click="abrirOferta">
                    <i class="fa-solid fa-tags mr-2"></i>Ver oferta
                </button>
                <a
                    v-if="oferta.url_tienda"
                    :href="oferta.url_tienda"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-black text-white hover:bg-white/10"
                >
                    <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>Ver todas las ofertas
                </a>
            </div>
        </div>

        <p v-if="resumen.pendientes_garantia > 0" class="mb-6 rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-100">
            <i class="fa-solid fa-shield-halved mr-2"></i>{{ resumen.pendientes_garantia }} juego(s) pendientes de evidencia para la garantía.
        </p>

        <div v-if="juegosFiltrados.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            <Link
                v-for="juego in juegosFiltrados"
                :key="`${juego.origen}-${juego.id}`"
                :href="route('portal.juegos.show', [juego.origen, juego.id])"
                class="group min-w-0 overflow-hidden rounded-2xl border border-white/10 bg-slate-900/80 transition duration-200 hover:-translate-y-0.5 hover:border-red-500/60 hover:shadow-[0_12px_28px_rgba(220,38,38,0.18)]"
            >
                <div class="relative aspect-[3/4] overflow-hidden bg-slate-950">
                    <img
                        v-if="juego.imagen"
                        :src="juego.imagen"
                        :alt="juego.juego"
                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                    />
                    <div v-else class="flex h-full items-center justify-center bg-gradient-to-br from-slate-800 to-slate-950 text-4xl">🎮</div>
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/10 to-transparent opacity-80"></div>
                    <span class="absolute left-2 top-2 rounded-md bg-black/70 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-white backdrop-blur-sm">{{ juego.consola }}</span>
                    <span class="absolute right-2 top-2 inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold backdrop-blur-sm" :class="garantiaColor(juego.garantia_estado)">
                        <i class="fa-solid fa-shield-halved mr-1 text-[9px]"></i>{{ garantiaTexto(juego.garantia_estado) }}
                    </span>
                    <div class="absolute inset-x-0 bottom-0 p-2.5">
                        <h3 class="line-clamp-2 text-sm font-black leading-4 text-white drop-shadow">{{ juego.juego }}</h3>
                        <p class="mt-1 truncate text-[11px] text-slate-300">{{ juego.tipo_cuenta }} · {{ fecha(juego.fecha) }}</p>
                    </div>
                </div>
            </Link>
        </div>

        <div v-else-if="juegos.length" class="rounded-3xl border border-dashed border-white/20 bg-white/5 px-6 py-14 text-center">
            <h3 class="text-xl font-black">No hay juegos con ese filtro</h3>
            <p class="mx-auto mt-2 max-w-lg text-sm text-slate-400">Prueba con otro nombre o consola.</p>
        </div>
        <div v-else class="rounded-3xl border border-dashed border-white/20 bg-white/5 px-6 py-14 text-center">
            <h3 class="text-xl font-black">Todavía no aparecen juegos</h3>
            <p class="mx-auto mt-2 max-w-lg text-sm text-slate-400">Si ya realizaste una compra, comunícate con MRJUEGOZ para revisar la vinculación de tu teléfono o usuario.</p>
        </div>

        <Teleport to="body">
            <div
                v-if="ofertaVisible"
                class="fixed inset-0 z-[100] flex items-end justify-center bg-black/80 p-4 sm:items-center"
                @click.self="cerrarOferta"
            >
                <div class="max-h-[92vh] w-full max-w-4xl overflow-auto rounded-3xl border border-white/10 bg-slate-950 text-white shadow-[0_24px_80px_rgba(0,0,0,0.55)]">
                    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-red-400">Oferta</p>
                            <h2 class="mt-1 text-2xl font-black">{{ oferta.titulo }}</h2>
                            <p class="mt-1 text-sm text-slate-400">{{ oferta.subtitulo }}</p>
                        </div>
                        <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 hover:bg-white/20" @click="cerrarOferta">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="p-4 sm:p-5">
                        <div v-if="esGaleria">
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <a
                                    v-for="juego in oferta.juegos"
                                    :key="juego.nombre"
                                    :href="juego.url || oferta.url_tienda"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group overflow-hidden rounded-2xl border border-white/10 bg-slate-900 hover:border-red-500/60"
                                >
                                    <div class="aspect-[3/4] overflow-hidden bg-slate-950">
                                        <img v-if="juego.imagen" :src="juego.imagen" :alt="juego.nombre" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
                                        <div v-else class="flex h-full items-center justify-center text-4xl">🎮</div>
                                    </div>
                                    <div class="p-2.5">
                                        <p class="line-clamp-2 text-xs font-black leading-4 text-white">{{ juego.nombre }}</p>
                                        <div class="mt-1 flex flex-wrap items-end gap-x-2 gap-y-1">
                                            <p class="text-sm font-black">{{ dinero(juego.precio) }}</p>
                                            <p v-if="juego.precio_comparacion" class="text-[10px] text-slate-500 line-through">{{ dinero(juego.precio_comparacion) }}</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <a
                                v-if="oferta.url_tienda"
                                :href="oferta.url_tienda"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-4 py-3 text-sm font-black text-white hover:bg-red-500"
                            >
                                <i class="fa-solid fa-tags mr-2"></i>Ver todas las ofertas
                            </a>
                        </div>
                        <article v-else class="flex flex-col overflow-hidden rounded-2xl border border-white/10 bg-slate-900 md:flex-row">
                            <div class="relative isolate flex items-center justify-center overflow-hidden bg-slate-950 p-5 md:w-80 md:shrink-0 md:p-6 lg:w-96">
                                <img
                                    v-if="imagenOferta"
                                    :src="imagenOferta"
                                    alt=""
                                    aria-hidden="true"
                                    class="pointer-events-none absolute inset-0 h-full w-full scale-125 object-cover opacity-40 blur-2xl"
                                />
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-red-500/15 via-slate-950/20 to-slate-950/80"></div>
                                <div class="relative">
                                    <div class="absolute -inset-4 rounded-[1.75rem] bg-red-500/25 blur-2xl"></div>
                                    <div class="relative overflow-hidden rounded-2xl shadow-[0_22px_50px_rgba(0,0,0,0.55)] ring-1 ring-white/20">
                                        <img
                                            v-if="imagenOferta"
                                            :src="imagenOferta"
                                            :alt="oferta.titulo"
                                            class="relative block h-auto max-h-72 w-auto max-w-[13rem] object-contain md:max-h-none md:w-full md:max-w-full"
                                        />
                                        <div
                                            v-if="imagenOferta"
                                            class="pointer-events-none absolute inset-0 bg-gradient-to-tr from-black/10 via-transparent to-white/15"
                                        ></div>
                                        <div v-if="!imagenOferta" class="flex h-64 w-40 items-center justify-center bg-slate-900 text-5xl md:h-80 md:w-56">🎮</div>
                                    </div>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1 p-3 sm:p-4">
                                <div class="grid gap-3" :class="columnasOferta.length > 1 ? 'grid-cols-2' : 'grid-cols-1'">
                                    <div v-for="columna in columnasOferta" :key="columna.titulo" class="rounded-xl border border-white/10 bg-slate-950 p-3">
                                        <p class="text-xs font-bold uppercase tracking-wide text-red-300">{{ columna.titulo }}</p>
                                        <div class="mt-3 space-y-3">
                                            <div v-for="juego in columna.juegos" :key="`${juego.nombre}-${juego.tipo}-${juego.consola}`">
                                                <p class="text-sm font-black">{{ juego.consola || juego.nombre }}</p>
                                                <p v-if="oferta.clave === 'gta'" class="mt-0.5 text-xs leading-4 text-slate-400">{{ juego.nombre }}</p>
                                                <div class="mt-1 flex flex-wrap items-end gap-x-2 gap-y-1">
                                                    <p class="text-base font-black text-white sm:text-lg">{{ dinero(juego.precio) }}</p>
                                                    <p v-if="juego.precio_comparacion" class="text-xs text-slate-500 line-through">{{ dinero(juego.precio_comparacion) }}</p>
                                                    <span v-if="juego.descuento" class="rounded-md bg-red-600 px-1.5 py-0.5 text-[10px] font-black">-{{ juego.descuento }}%</span>
                                                </div>
                                                <div class="mt-2 flex flex-col gap-2">
                                                    <a v-if="juego.url" :href="juego.url" target="_blank" rel="noopener noreferrer" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-3 py-2 text-xs font-black text-white hover:bg-red-500">
                                                        <i class="fa-solid fa-cart-shopping mr-1"></i>Comprar
                                                    </a>
                                                    <a v-if="oferta.whatsapp" :href="mensajeWhatsapp(juego)" target="_blank" rel="noopener noreferrer" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-500 px-3 py-2 text-xs font-black text-slate-950 hover:bg-emerald-400">
                                                        <i class="fa-brands fa-whatsapp mr-1"></i>Pedir
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </Teleport>
    </PortalClienteLayout>
</template>
