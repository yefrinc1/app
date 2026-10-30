<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const usuario = computed(() => page.props.auth?.user);
const enJuegos = computed(() => route().current('portal.index') || route().current('portal.juegos.show'));
const enPedidos = computed(() => route().current('portal.pedidos.index') || route().current('portal.pedidos.show'));
const claseEnlace = (activo) => [
    'inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-black transition sm:flex-none',
    activo ? 'bg-red-600 text-white shadow-lg shadow-red-900/40' : 'text-slate-300 hover:bg-white/10 hover:text-white',
].join(' ');
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-white">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/95 backdrop-blur">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between gap-4 py-3 sm:py-4">
                    <Link :href="route('portal.index')" class="flex shrink-0 items-center">
                        <div>
                            <p class="font-black tracking-wide">MRJUEGOZ</p>
                            <p class="text-xs text-slate-400">Portal de clientes</p>
                        </div>
                    </Link>

                    <nav class="hidden items-center gap-1 sm:flex">
                        <Link :href="route('portal.index')" :class="claseEnlace(enJuegos)">
                            <i class="fa-solid fa-gamepad"></i>Mis juegos
                        </Link>
                        <Link :href="route('portal.pedidos.index')" :class="claseEnlace(enPedidos)">
                            <i class="fa-solid fa-receipt"></i>Mis pedidos
                        </Link>
                    </nav>

                    <div class="flex items-center gap-3">
                        <div class="hidden text-right md:block">
                            <p class="text-sm font-bold">{{ usuario?.name }}</p>
                            <p class="max-w-[220px] truncate text-xs text-slate-400">{{ usuario?.email }}</p>
                        </div>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex h-10 items-center justify-center rounded-xl border border-white/10 bg-white/5 px-4 text-sm font-bold text-slate-200 transition hover:border-red-500/50 hover:bg-red-500/10 hover:text-white"
                        >
                            <i class="fa-solid fa-right-from-bracket mr-2"></i>Salir
                        </Link>
                    </div>
                </div>

                <nav class="flex gap-1 pb-3 sm:hidden">
                    <Link :href="route('portal.index')" :class="claseEnlace(enJuegos)">
                        <i class="fa-solid fa-gamepad"></i>Mis juegos
                    </Link>
                    <Link :href="route('portal.pedidos.index')" :class="claseEnlace(enPedidos)">
                        <i class="fa-solid fa-receipt"></i>Mis pedidos
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8"><slot /></main>

        <footer class="border-t border-white/10 py-6 text-center text-xs text-slate-500">MRJUEGOZ · Tus juegos siempre disponibles en un solo lugar</footer>
    </div>
</template>
