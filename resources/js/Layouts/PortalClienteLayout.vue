<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const usuario = computed(() => page.props.auth?.user);
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-white">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <Link :href="route('portal.index')" class="flex items-center">
                    <div><p class="font-black tracking-wide">MRJUEGOZ</p><p class="text-xs text-slate-400">Portal de clientes</p></div>
                </Link>
                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block"><p class="text-sm font-bold">{{ usuario?.name }}</p><p class="max-w-[220px] truncate text-xs text-slate-400">{{ usuario?.email }}</p></div>
                    <Link :href="route('logout')" method="post" as="button" class="inline-flex h-10 items-center justify-center rounded-xl border border-white/10 bg-white/5 px-4 text-sm font-bold text-slate-200 transition hover:border-red-500/50 hover:bg-red-500/10 hover:text-white"><i class="fa-solid fa-right-from-bracket mr-2"></i>Salir</Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8"><slot /></main>

        <footer class="border-t border-white/10 py-6 text-center text-xs text-slate-500">MRJUEGOZ · Tus juegos siempre disponibles en un solo lugar</footer>
    </div>
</template>
