<script setup>
import PortalClienteLayout from '@/Layouts/PortalClienteLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { computed } from 'vue';
import { copiarTexto } from '@/Utils/portapapeles';

const props = defineProps({ juego: Object, recomendaciones: Array });
const copiar = async (texto, etiqueta) => {
    if (!texto) return;
    try {
        await copiarTexto(texto);
        await Swal.fire({ title: `${etiqueta} copiado`, icon: 'success', timer: 1300, showConfirmButton: false });
    } catch (_) {
        await Swal.fire({ title: `Copia ${etiqueta.toLowerCase()}`, input: 'textarea', inputValue: texto, inputAttributes: { readonly: 'readonly' }, icon: 'info', confirmButtonText: 'Cerrar' });
    }
};
const mensajeCompleto = computed(() => [
    '⚠️ RECOMENDACIONES IMPORTANTES',
    ...props.recomendaciones.map((item, i) => `${i + 1}. ${item}`),
    '', `🎮 Juego: ${props.juego.juego}`, `🕹️ Consola: ${props.juego.consola}`,
    `🔑 Tipo de cuenta: ${props.juego.tipo_cuenta}`, '',
    `👤 Usuario: ${props.juego.usuario || 'No disponible'}`,
    `🔒 Contraseña: ${props.juego.contrasena || 'No disponible'}`,
    `🛡️ Código de verificación: ${props.juego.codigo || 'Solicitar a soporte'}`,
].join('\n'));
const youtubeEmbed = computed(() => {
    const url = props.juego.tutorial;
    if (!url) return null;
    const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([^?&/]+)/i);
    return match ? `https://www.youtube.com/embed/${match[1]}` : null;
});
</script>

<template>
    <PortalClienteLayout>
        <Head :title="juego.juego" />
        <Link :href="route('portal.index')" class="inline-flex items-center text-sm font-bold text-slate-300 hover:text-white"><i class="fa-solid fa-arrow-left mr-2"></i>Volver a mis juegos</Link>
        <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.8fr)]">
            <div class="space-y-6">
                <section class="overflow-hidden rounded-3xl border border-white/10 bg-slate-900"><div class="grid sm:grid-cols-[minmax(220px,320px)_minmax(0,1fr)]"><div class="mx-auto aspect-square w-full max-w-[420px] bg-slate-950 p-3 sm:max-w-none"><img v-if="juego.imagen" :src="juego.imagen" :alt="juego.juego" class="h-full w-full object-contain" /><div v-else class="flex h-full items-center justify-center text-6xl">🎮</div></div><div class="flex min-w-0 flex-col justify-center border-t border-white/10 p-5 sm:border-l sm:border-t-0 sm:p-6"><div class="flex flex-wrap gap-2"><span class="rounded-full bg-red-500/15 px-3 py-1 text-xs font-bold text-red-300">{{ juego.tipo_cuenta }}</span><span class="rounded-full bg-blue-500/15 px-3 py-1 text-xs font-bold text-blue-300">{{ juego.consola }}</span><span v-if="juego.pedido" class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-slate-300">{{ juego.pedido }}</span></div><h1 class="mt-4 break-words text-2xl font-black sm:text-3xl">{{ juego.juego }}</h1></div></div></section>
                <section class="rounded-3xl border border-amber-400/20 bg-amber-400/10 p-6"><h2 class="font-black text-amber-200"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Antes de instalar</h2><ol class="mt-4 space-y-3 text-sm text-amber-50"><li v-for="(item, indice) in recomendaciones" :key="item" class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-300 font-black text-slate-900">{{ indice + 1 }}</span><span>{{ item }}</span></li></ol></section>
                <section v-if="juego.tutorial" class="rounded-3xl border border-white/10 bg-slate-900 p-6"><h2 class="text-xl font-black"><i class="fa-solid fa-circle-play mr-2 text-red-500"></i>Tutorial de instalación</h2><div v-if="youtubeEmbed" class="mt-4 aspect-video overflow-hidden rounded-2xl"><iframe :src="youtubeEmbed" class="h-full w-full" title="Tutorial de instalación" allowfullscreen></iframe></div><a v-else :href="juego.tutorial" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex rounded-xl bg-red-600 px-5 py-3 font-bold hover:bg-red-500"><i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>Abrir tutorial</a></section>
            </div>
            <aside class="h-fit rounded-3xl border border-red-500/20 bg-gradient-to-br from-slate-900 to-red-950/40 p-6 shadow-2xl lg:sticky lg:top-28"><h2 class="text-xl font-black"><i class="fa-solid fa-key mr-2 text-red-500"></i>Datos del juego</h2><p class="mt-1 text-sm text-slate-400">Pulsa cada campo para copiarlo.</p><div class="mt-6 space-y-4"><button type="button" class="w-full rounded-2xl border border-white/10 bg-black/20 p-4 text-left hover:border-red-500/40" @click="copiar(juego.usuario, 'Usuario')"><span class="text-xs font-bold uppercase text-slate-500">👤 Usuario</span><span class="mt-1 block break-all font-mono font-bold">{{ juego.usuario || 'No disponible' }}</span></button><button type="button" class="w-full rounded-2xl border border-white/10 bg-black/20 p-4 text-left hover:border-red-500/40" @click="copiar(juego.contrasena, 'Contraseña')"><span class="text-xs font-bold uppercase text-slate-500">🔒 Contraseña</span><span class="mt-1 block break-all font-mono font-bold">{{ juego.contrasena || 'No disponible' }}</span></button><button type="button" class="w-full rounded-2xl border p-4 text-left" :class="juego.codigo ? 'border-white/10 bg-black/20 hover:border-red-500/40' : 'border-amber-400/20 bg-amber-400/10'" @click="copiar(juego.codigo, 'Código')"><span class="text-xs font-bold uppercase text-slate-500">🛡️ Código de verificación</span><span class="mt-1 block break-all font-mono font-bold">{{ juego.codigo || 'Solicitar a soporte' }}</span><span v-if="!juego.codigo" class="mt-2 block text-xs text-amber-200">Las ventas antiguas no guardaban la relación con el código utilizado.</span></button></div><button type="button" class="mt-6 w-full rounded-xl bg-red-600 px-5 py-3 font-black hover:bg-red-500" @click="copiar(mensajeCompleto, 'Todos los datos')"><i class="fa-solid fa-copy mr-2"></i>Copiar todos los datos</button></aside>
        </div>
    </PortalClienteLayout>
</template>
