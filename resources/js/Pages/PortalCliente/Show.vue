<script setup>
import PortalClienteLayout from '@/Layouts/PortalClienteLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { computed, ref } from 'vue';
import axios from 'axios';
import { copiarTexto } from '@/Utils/portapapeles';

const props = defineProps({ juego: Object, recomendaciones: Array, garantia: Object });
const evidenciaForm = useForm({ archivo: null, declaracion: false });
const nombreArchivo = ref('');
const evidenciaVisible = ref(null);
const imagenEjemploEvidencia = computed(() => {
    if (props.garantia.tipo_requerido !== 'foto') return null;
    const consola = String(props.juego.consola).toLowerCase();
    return ['ps4', 'ps5'].includes(consola) ? `/images/portal/garantia-primaria-${consola}.jpeg` : null;
});
const recomendacionesFinales = computed(() => String(props.juego.tipo_cuenta).toLowerCase() === 'primaria'
    ? [
        'No cambies el correo ni la contraseña.',
        'No modifiques los datos de la cuenta ni vuelvas a acceder a ella después de la instalación.',
        'No borres la cuenta de la consola.',
        'No ingreses estos datos en otra consola; podrías perder el acceso al juego sin reembolso.',
    ]
    : [
        'No cambies los datos de la cuenta.',
        'No actives la cuenta como principal ni elimines la cuenta.',
        'Accede a la cuenta entregada para jugar.',
        'No ingreses estos datos en otra consola; podrías perder el acceso al juego sin reembolso.',
    ]);
const registrarAcceso = async (accion) => {
    try {
        await axios.post(route('portal.juegos.accesos.store', [props.juego.origen, props.juego.id]), { accion });
    } catch (_) {
        // El registro de auditoría no debe impedir al cliente utilizar el portal.
    }
};
const copiar = async (texto, etiqueta, accion) => {
    if (!texto) return;
    try {
        await copiarTexto(texto);
        await Swal.fire({ title: `${etiqueta} copiado`, icon: 'success', timer: 1300, showConfirmButton: false });
    } catch (_) {
        await Swal.fire({ title: `Copia ${etiqueta.toLowerCase()}`, input: 'textarea', inputValue: texto, inputAttributes: { readonly: 'readonly' }, icon: 'info', confirmButtonText: 'Cerrar' });
    } finally {
        registrarAcceso(accion);
    }
};
const mensajeCompleto = computed(() => [
    '⚠️ RECOMENDACIONES IMPORTANTES',
    ...props.recomendaciones.map((item, i) => `${i + 1}. ${item}`),
    '', `🎮 Juego: ${props.juego.juego}`, `🕹️ Consola: ${props.juego.consola}`,
    `🔑 Tipo de cuenta: ${props.juego.tipo_cuenta}`, '',
    `👤 Usuario: ${props.juego.usuario || 'No disponible'}`,
    `🔒 Contraseña: ${props.juego.contrasena || 'No disponible'}`,
    `🛡️ Código de verificación: ${props.juego.codigo || 'Ver ayuda del candado'}`,
].join('\n'));
const youtubeEmbedDe = (url) => {
    if (!url) return null;
    const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([^?&/]+)/i);
    return match ? `https://www.youtube.com/embed/${match[1]}` : null;
};
const youtubeEmbed = computed(() => youtubeEmbedDe(props.juego.tutorial));
const youtubeEmbedCandado = computed(() => youtubeEmbedDe(props.juego.tutorial_candado));
const verificacion = computed(() => props.juego.verificacion || {});
const mostrarAyudaCandado = computed(() => props.garantia.estado === 'activa' || props.garantia.saltar_evidencia);
const candadoAbierto = ref(false);
const datosAbiertos = ref(false);
const abrirCandado = () => {
    candadoAbierto.value = true;
    registrarAcceso('abrir_candado');
};
const abrirDatos = () => {
    datosAbiertos.value = true;
    registrarAcceso('abrir_datos');
};
const solicitarCodigo = async () => {
    const confirmacion = await Swal.fire({
        title: '¿Generar código para quitar el candado?',
        text: 'Mira el video y usa este código cuando PlayStation te lo pida al volver a entrar. Es de un solo uso.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, generar código',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        reverseButtons: true,
    });
    if (!confirmacion.isConfirmed) return;

    router.post(route('portal.juegos.codigo', [props.juego.origen, props.juego.id]), {}, {
        preserveScroll: true,
        onStart: () => Swal.fire({ title: 'Generando código…', allowOutsideClick: false, didOpen: () => Swal.showLoading() }),
        onError: (errores) => Swal.fire({ title: 'No se pudo generar', text: Object.values(errores).join('\n') || 'Intenta de nuevo.', icon: 'error', confirmButtonColor: '#dc2626' }),
        onSuccess: () => Swal.fire({ title: 'Código listo', text: 'Mira el video y úsalo para quitar el candado. Es de un solo uso.', icon: 'success', confirmButtonColor: '#16a34a' }),
    });
};
const garantiaActiva = computed(() => props.garantia.estado === 'activa');
const garantiaAntigua = computed(() => props.garantia.estado === 'antigua' || props.garantia.saltar_evidencia);
const garantiaEtiqueta = computed(() => {
    if (garantiaActiva.value) return 'Garantía activa';
    if (garantiaAntigua.value) return 'Garantía antigua';
    return 'Sin garantía';
});
const garantiaEtiquetaColor = computed(() => {
    if (garantiaActiva.value) return 'bg-emerald-500 text-white';
    if (garantiaAntigua.value) return 'bg-amber-400 text-slate-900';
    return 'bg-red-600 text-white';
});
const garantiaTexto = computed(() => ({ activa: 'Garantía activa', antigua: 'Garantía antigua', en_revision: 'Evidencia en revisión', requiere_correccion: 'Requiere corrección', pendiente: 'Evidencia pendiente para activar la garantía' }[props.garantia.estado] ?? 'Evidencia pendiente para activar la garantía'));
const garantiaColor = computed(() => ({ activa: 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100', antigua: 'border-amber-400/30 bg-amber-400/10 text-amber-50', en_revision: 'border-sky-400/30 bg-sky-400/10 text-sky-100', requiere_correccion: 'border-amber-400/30 bg-amber-400/10 text-amber-100', pendiente: 'border-white/10 bg-white/5 text-slate-100' }[props.garantia.estado]));
const fechaHora = (valor) => valor ? new Date(valor).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) : '';
const elegirArchivo = (evento) => {
    const input = evento.target;
    const archivo = input.files?.[0] ?? null;
    evidenciaForm.clearErrors('archivo');

    if (!archivo) {
        evidenciaForm.archivo = null;
        nombreArchivo.value = '';
        return;
    }

    const maxMb = Number(props.garantia.max_mb) || 50;
    if (archivo.size > maxMb * 1024 * 1024) {
        const pesoMb = Math.max(1, Math.round(archivo.size / (1024 * 1024)));
        const esVideo = props.garantia.tipo_requerido !== 'foto';
        evidenciaForm.archivo = null;
        nombreArchivo.value = '';
        input.value = '';
        Swal.fire({
            title: esVideo ? 'El video es demasiado pesado' : 'La foto es demasiado pesada',
            text: esVideo
                ? `Tu video pesa ${pesoMb} MB. Debe ser máximo ${maxMb} MB. Grábalo más corto o baja la calidad.`
                : `Tu foto pesa ${pesoMb} MB. Debe ser máximo ${maxMb} MB. Toma otra más liviana.`,
            icon: 'warning',
            confirmButtonColor: '#dc2626',
        });
        return;
    }

    evidenciaForm.archivo = archivo;
    nombreArchivo.value = archivo.name;
};
const enviarEvidencia = async () => {
    if (!evidenciaForm.archivo) {
        await Swal.fire({ title: 'Falta la evidencia', text: `Debes seleccionar ${props.garantia.tipo_requerido === 'foto' ? 'una foto' : 'un video'}.`, icon: 'warning', confirmButtonColor: '#dc2626' });
        return;
    }
    if (!evidenciaForm.declaracion) {
        await Swal.fire({ title: 'Confirma la declaración', text: 'Debes confirmar que no modificaste los datos ni la seguridad de la cuenta.', icon: 'warning', confirmButtonColor: '#dc2626' });
        return;
    }
    const confirmacion = await Swal.fire({ title: '¿Enviar evidencia?', text: 'El equipo MRJUEGOZ revisará la configuración antes de activar la garantía.', icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, enviar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626', reverseButtons: true });
    if (!confirmacion.isConfirmed) return;

    evidenciaForm.post(route('portal.garantias.store', [props.juego.origen, props.juego.id]), {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => Swal.fire({ title: 'Subiendo evidencia…', text: 'No cierres esta ventana mientras termina la carga.', allowOutsideClick: false, didOpen: () => Swal.showLoading() }),
        onError: (errores) => Swal.fire({ title: 'No se pudo enviar', text: Object.values(errores).join('\n'), icon: 'error', confirmButtonColor: '#dc2626' }),
        onSuccess: () => {
            evidenciaForm.reset();
            nombreArchivo.value = '';
            Swal.fire({ title: 'Evidencia enviada', text: 'La instalación quedó pendiente de revisión.', icon: 'success', confirmButtonColor: '#16a34a' });
        },
    });
};
</script>

<template>
    <PortalClienteLayout>
        <Head :title="juego.juego" />
        <Link :href="route('portal.index')" class="inline-flex items-center text-sm font-semibold text-slate-300 hover:text-white">
            <i class="fa-solid fa-arrow-left mr-2"></i>Volver a mis juegos
        </Link>

        <section class="mt-5 rounded-2xl border border-white/10 bg-slate-900 p-5 sm:p-0 sm:overflow-hidden">
            <div class="flex flex-col items-center sm:flex-row sm:items-stretch">
                <div class="aspect-[3/4] w-40 overflow-hidden rounded-xl bg-slate-950 sm:w-36 sm:rounded-none lg:w-44">
                    <img v-if="juego.imagen" :src="juego.imagen" :alt="juego.juego" class="h-full w-full object-cover" />
                    <div v-else class="flex h-full items-center justify-center text-5xl">🎮</div>
                </div>
                <div class="mt-4 flex min-w-0 flex-1 flex-col justify-center text-center sm:mt-0 sm:p-5 sm:text-left">
                    <div class="flex flex-wrap justify-center gap-2 sm:justify-start">
                        <span class="rounded-md bg-red-500/15 px-2.5 py-1 text-xs font-bold text-red-300">{{ juego.tipo_cuenta }}</span>
                        <span class="rounded-md bg-white/10 px-2.5 py-1 text-xs font-bold text-slate-200">{{ juego.consola }}</span>
                        <span v-if="juego.pedido" class="rounded-md bg-white/10 px-2.5 py-1 text-xs font-bold text-slate-300">{{ juego.pedido }}</span>
                        <span class="rounded-md px-2.5 py-1 text-xs font-bold" :class="garantiaEtiquetaColor">
                            {{ garantiaEtiqueta }}
                        </span>
                    </div>
                    <h1 class="mt-3 text-2xl font-black leading-tight text-white sm:text-3xl">{{ juego.juego }}</h1>
                </div>
            </div>
        </section>

        <section v-if="mostrarAyudaCandado" class="mt-6 overflow-hidden rounded-2xl border border-red-400/30 bg-slate-900">
            <div class="p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-wide text-red-300">Ayuda rápida</p>
                <h2 class="mt-1 text-xl font-black text-white">¿Qué necesitas hacer?</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">Elige una opción y te llevamos directamente a lo que necesitas.</p>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <button type="button" class="group flex items-start gap-4 rounded-xl border border-white/10 bg-slate-950 p-4 text-left hover:border-white/25 hover:bg-slate-950/70" @click="abrirDatos">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-slate-100 group-hover:bg-white/15">
                            <i class="fa-solid fa-gamepad"></i>
                        </span>
                        <span>
                            <span class="block font-black text-white">Ver datos del juego</span>
                            <span class="mt-1 block text-sm leading-5 text-slate-400">Consultar el usuario, la contraseña y el código de acceso.</span>
                        </span>
                    </button>
                    <button type="button" class="group flex items-start gap-4 rounded-xl border border-red-400/25 bg-red-500/10 p-4 text-left hover:border-red-400/50 hover:bg-red-500/15" @click="abrirCandado">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-500/20 text-red-300 group-hover:bg-red-500/30">
                            <i class="fa-solid fa-lock-open"></i>
                        </span>
                        <span>
                            <span class="block font-black text-white">No funciona o tiene candado</span>
                            <span class="mt-1 block text-sm leading-5 text-slate-300">Ver el video y generar un código si hace falta.</span>
                        </span>
                    </button>
                </div>
            </div>
        </section>

        <div class="mt-6">
            <div class="space-y-6">
                <section v-if="!mostrarAyudaCandado" class="rounded-2xl border border-amber-400/20 bg-amber-400/10 p-5">
                    <h2 class="font-black text-amber-100"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Antes de instalar</h2>
                    <ol class="mt-4 space-y-3 text-sm leading-6 text-amber-50">
                        <li v-for="(item, indice) in recomendaciones" :key="item" class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-300 text-xs font-black text-slate-900">{{ indice + 1 }}</span>
                            <span>{{ item }}</span>
                        </li>
                    </ol>
                </section>

                <section v-if="juego.tutorial && !mostrarAyudaCandado" class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900">
                    <div class="px-5 pt-5">
                        <h2 class="text-lg font-black"><i class="fa-solid fa-circle-play mr-2 text-red-500"></i>Tutorial de instalación</h2>
                    </div>
                    <div v-if="youtubeEmbed" class="mt-4 aspect-video overflow-hidden bg-black">
                        <iframe :src="youtubeEmbed" class="h-full w-full" title="Tutorial de instalación" allowfullscreen @load="registrarAcceso('abrir_tutorial')"></iframe>
                    </div>
                    <a v-else :href="juego.tutorial" target="_blank" rel="noopener noreferrer" class="m-5 inline-flex rounded-xl bg-red-600 px-5 py-3 font-bold hover:bg-red-500" @click="registrarAcceso('abrir_tutorial')">
                        <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>Abrir tutorial
                    </a>
                </section>

                <section v-if="!mostrarAyudaCandado" id="evidencia-garantia" class="scroll-mt-28 rounded-2xl border p-5" :class="garantiaColor">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide opacity-70">Evidencia para la garantía</p>
                            <h2 class="mt-1 text-xl font-black"><i class="fa-solid fa-shield-halved mr-2"></i>{{ garantiaTexto }}</h2>
                        </div>
                        <span class="w-fit rounded-md bg-black/20 px-3 py-1 text-xs font-bold">{{ garantia.tipo_requerido === 'foto' ? 'Foto obligatoria' : 'Video obligatorio' }}</span>
                    </div>
                    <div v-if="garantia.estado === 'activa'" class="mt-5 rounded-xl bg-black/20 p-4 text-sm">
                        <p class="font-bold">La instalación fue verificada correctamente.</p>
                        <p class="mt-1 opacity-80">Conserva la configuración y no modifiques los datos de la cuenta.</p>
                    </div>
                    <div v-else-if="garantia.estado === 'en_revision'" class="mt-5 rounded-xl bg-black/20 p-4 text-sm">
                        <p class="font-bold">Tu evidencia fue recibida.</p>
                        <p class="mt-1 opacity-80">MRJUEGOZ la revisará antes de activar la garantía.</p>
                    </div>
                    <template v-else>
                        <div v-if="garantia.estado === 'requiere_correccion'" class="mt-5 rounded-xl border border-amber-300/20 bg-black/20 p-4 text-sm">
                            <p class="font-black">Debes enviar una nueva evidencia</p>
                            <p class="mt-2 whitespace-pre-line">{{ garantia.evidencias[0]?.motivo_rechazo }}</p>
                        </div>
                        <h3 class="mt-5 font-black">Cómo debe verse la evidencia</h3>
                        <ol class="mt-3 space-y-3 text-sm">
                            <li v-for="(item, indice) in garantia.instrucciones" :key="item" class="flex gap-3">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 text-xs font-black">{{ indice + 1 }}</span>
                                <span>{{ item }}</span>
                            </li>
                        </ol>
                        <figure v-if="imagenEjemploEvidencia" class="mt-5 overflow-hidden rounded-xl border border-white/15 bg-black/20">
                            <div class="p-4">
                                <h3 class="font-black">Foto de ejemplo</h3>
                                <p class="mt-1 text-sm opacity-80">Úsala solo como referencia. Debes subir una foto propia de tu consola.</p>
                            </div>
                            <img :src="imagenEjemploEvidencia" :alt="`Ejemplo de evidencia para ${juego.consola}`" class="max-h-[420px] w-full bg-black object-contain" loading="lazy" />
                        </figure>
                        <div v-if="garantia.puede_enviar" class="mt-5 rounded-xl border border-white/10 bg-black/20 p-4">
                            <h3 class="text-sm font-black">Cómo subirla</h3>
                            <ol class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                                <li class="flex gap-2"><span class="font-black">1.</span><span>Selecciona tu {{ garantia.tipo_requerido === 'foto' ? 'foto' : 'video' }}.</span></li>
                                <li class="flex gap-2"><span class="font-black">2.</span><span>Marca la confirmación.</span></li>
                                <li class="flex gap-2"><span class="font-black">3.</span><span>Presiona “Enviar evidencia”.</span></li>
                            </ol>
                            <label class="mt-4 block text-sm font-black">{{ garantia.tipo_requerido === 'foto' ? 'Seleccionar foto' : 'Seleccionar video' }}</label>
                            <label class="mt-2 flex min-w-0 cursor-pointer items-center gap-3 overflow-hidden rounded-xl border border-dashed border-white/25 bg-white/5 px-4 py-4 hover:border-red-400">
                                <i class="fa-solid fa-cloud-arrow-up shrink-0 text-xl"></i>
                                <span class="min-w-0 flex-1 truncate text-sm">{{ nombreArchivo || `Archivo máximo ${garantia.max_mb} MB` }}</span>
                                <input type="file" class="sr-only" :accept="garantia.accept" @change="elegirArchivo" />
                            </label>
                            <p v-if="evidenciaForm.errors.archivo" class="mt-2 text-sm text-red-300">{{ evidenciaForm.errors.archivo }}</p>
                            <label class="mt-4 flex cursor-pointer items-start gap-3 text-sm">
                                <input v-model="evidenciaForm.declaracion" type="checkbox" class="mt-1 rounded border-white/30 bg-slate-900 text-red-600 focus:ring-red-500" />
                                <span>Confirmo que no modifiqué el correo, contraseña, verificación, seguridad ni otra configuración de la cuenta.</span>
                            </label>
                            <p v-if="evidenciaForm.errors.declaracion" class="mt-2 text-sm text-red-300">{{ evidenciaForm.errors.declaracion }}</p>
                            <button type="button" class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 font-black text-white hover:bg-red-500 disabled:opacity-60" :disabled="evidenciaForm.processing" @click="enviarEvidencia">
                                <i class="fa-solid fa-paper-plane mr-2"></i>{{ evidenciaForm.processing ? 'Subiendo…' : 'Enviar evidencia para revisión' }}
                            </button>
                        </div>
                    </template>
                    <div v-if="garantia.evidencias.length" class="mt-5 border-t border-white/10 pt-4">
                        <h3 class="text-sm font-black">Historial de evidencias</h3>
                        <div class="mt-3 space-y-2">
                            <button v-for="evidencia in garantia.evidencias" :key="evidencia.id" type="button" class="flex w-full items-center justify-between gap-3 rounded-xl bg-black/20 p-3 text-left text-sm hover:bg-black/30" @click="evidenciaVisible = evidencia">
                                <span class="min-w-0">
                                    <span class="block font-bold">{{ evidencia.tipo_archivo === 'foto' ? 'Foto' : 'Video' }} · {{ fechaHora(evidencia.enviado_at) }}</span>
                                    <span v-if="evidencia.motivo_rechazo" class="mt-1 block truncate text-xs opacity-70">{{ evidencia.motivo_rechazo }}</span>
                                </span>
                                <span class="shrink-0 capitalize">{{ evidencia.estado.replaceAll('_', ' ') }} <i class="fa-solid fa-eye ml-2"></i></span>
                            </button>
                        </div>
                    </div>
                </section>

                <section v-if="mostrarAyudaCandado" class="rounded-2xl border border-emerald-400/25 bg-emerald-400/10 p-5 text-emerald-50">
                    <h2 class="text-lg font-black">Recomendaciones finales · {{ String(juego.tipo_cuenta).toLowerCase() === 'primaria' ? 'Primaria' : 'Secundaria' }}</h2>
                    <p class="mt-2 text-sm">{{ String(juego.tipo_cuenta).toLowerCase() === 'secundaria' ? 'Para mantener tu garantía de por vida, sigue estas instrucciones:' : 'Para que disfrutes al máximo y mantengas tu garantía, sigue estas recomendaciones:' }}</p>
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm">
                        <li v-for="item in recomendacionesFinales" :key="item">{{ item }}</li>
                    </ul>
                </section>
            </div>

        </div>

        <section v-if="!mostrarAyudaCandado" class="mt-6 rounded-2xl border border-white/10 bg-slate-900 p-5 sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-red-300">Acceso</p>
                    <h2 class="mt-1 text-xl font-black text-white">Datos del juego</h2>
                    <p class="mt-1 text-sm text-slate-400">Copia estos datos para usarlos en tu consola.</p>
                </div>
                <span class="w-fit rounded-md bg-white/10 px-2.5 py-1 text-xs font-bold text-slate-300">{{ juego.consola }}</span>
            </div>

            <div class="mt-5 grid gap-3 lg:grid-cols-3">
                <div class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Correo</p>
                    <p class="mt-1 break-all text-lg font-semibold leading-snug text-white">{{ juego.usuario || 'No disponible' }}</p>
                    <button v-if="juego.usuario" type="button" class="mt-3 inline-flex items-center rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-500" @click="copiar(juego.usuario, 'Correo', 'copiar_usuario')">
                        <i class="fa-solid fa-copy mr-2"></i>Copiar correo
                    </button>
                </div>

                <div class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Contraseña</p>
                    <p class="mt-1 break-all text-lg font-semibold leading-snug text-white">{{ juego.contrasena || 'No disponible' }}</p>
                    <button v-if="juego.contrasena" type="button" class="mt-3 inline-flex items-center rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-500" @click="copiar(juego.contrasena, 'Contraseña', 'copiar_contrasena')">
                        <i class="fa-solid fa-copy mr-2"></i>Copiar contraseña
                    </button>
                </div>

                <div class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Código de verificación</p>
                    <p class="mt-1 break-all text-lg font-semibold leading-snug text-white">{{ juego.codigo || 'No disponible' }}</p>
                    <button v-if="juego.codigo" type="button" class="mt-3 inline-flex items-center rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-500" @click="copiar(juego.codigo, 'Código', 'copiar_codigo')">
                        <i class="fa-solid fa-copy mr-2"></i>Copiar código
                    </button>
                </div>
            </div>

            <button type="button" class="mt-4 w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-black text-white hover:bg-red-500" @click="copiar(mensajeCompleto, 'Todos los datos', 'copiar_todos')">
                <i class="fa-solid fa-copy mr-2"></i>Copiar todos los datos
            </button>

            <div v-if="garantia.puede_enviar" class="mt-5 rounded-xl border border-amber-300/30 bg-amber-400/10 p-4 text-amber-50">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-300 text-lg text-slate-900">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="font-black">No olvides subir la evidencia</p>
                        <p class="mt-1 text-sm leading-6 text-amber-100">Debes enviarla para no perder la garantía. Sin esta evidencia no podremos validar la instalación ni activar la cobertura de tu juego.</p>
                        <a href="#evidencia-garantia" class="mt-3 inline-flex items-center rounded-lg bg-amber-300 px-4 py-2 text-sm font-black text-slate-900 hover:bg-amber-200">
                            <i class="fa-solid fa-cloud-arrow-up mr-2"></i>Subir evidencia ahora
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <Teleport to="body">
            <div v-if="datosAbiertos" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4" @click.self="datosAbiertos = false">
                <div class="flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-slate-900 text-white shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-red-300">Acceso</p>
                            <h2 class="mt-1 text-xl font-black">Datos del juego</h2>
                            <p class="mt-1 text-sm text-slate-400">Copia estos datos para usarlos en tu consola.</p>
                        </div>
                        <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 hover:bg-white/20" aria-label="Cerrar datos del juego" @click="datosAbiertos = false">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5">
                        <div class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Usuario</p>
                                    <p class="mt-1 break-all text-lg font-semibold leading-snug">{{ juego.usuario || 'No disponible' }}</p>
                                </div>
                                <button type="button" class="shrink-0 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold hover:bg-red-500" @click="copiar(juego.usuario, 'Usuario', 'copiar_usuario')">
                                    <i class="fa-solid fa-copy mr-1"></i>Copiar
                                </button>
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Contraseña</p>
                                    <p class="mt-1 break-all text-lg font-semibold leading-snug">{{ juego.contrasena || 'No disponible' }}</p>
                                </div>
                                <button type="button" class="shrink-0 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold hover:bg-red-500" @click="copiar(juego.contrasena, 'Contraseña', 'copiar_contrasena')">
                                    <i class="fa-solid fa-copy mr-1"></i>Copiar
                                </button>
                            </div>
                        </div>

                        <div v-if="juego.codigo" class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ verificacion.es_instalacion ? 'Código de verificación' : 'Código para desbloquear' }}</p>
                                    <p class="mt-1 break-all text-lg font-semibold leading-snug">{{ juego.codigo }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ verificacion.es_instalacion ? 'Úsalo en el primer inicio de sesión.' : 'Es de un solo uso.' }}</p>
                                </div>
                                <button type="button" class="shrink-0 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold hover:bg-red-500" @click="copiar(juego.codigo, 'Código', 'copiar_codigo')">
                                    <i class="fa-solid fa-copy mr-1"></i>Copiar
                                </button>
                            </div>
                        </div>

                        <button type="button" class="w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-black hover:bg-red-500" @click="copiar(mensajeCompleto, 'Todos los datos', 'copiar_todos')">
                            <i class="fa-solid fa-copy mr-2"></i>Copiar todos los datos
                        </button>
                        <p class="text-xs leading-5 text-slate-500">Se registra IP y navegador al copiar estos datos.</p>
                    </div>
                </div>
            </div>

            <div v-if="candadoAbierto" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4" @click.self="candadoAbierto = false">
                <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-red-400/30 bg-slate-900 text-white shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-5 py-4 sm:px-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-red-300">Ayuda paso a paso</p>
                            <h2 class="mt-1 text-xl font-black">Cómo organizar el juego</h2>
                            <p class="mt-1 text-sm text-slate-400">Sigue estos pasos si el juego no funciona o aparece con candado.</p>
                        </div>
                        <button type="button" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 hover:bg-white/20" aria-label="Cerrar ayuda" @click="candadoAbierto = false">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div class="px-5 pb-3 pt-5 sm:px-6 sm:pt-6">
                            <p class="text-sm font-bold"><span class="mr-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600 text-xs font-black">1</span>Sigue el video para quitar el candado</p>
                        </div>
                        <div v-if="youtubeEmbedCandado" class="aspect-video overflow-hidden bg-black">
                            <iframe :src="youtubeEmbedCandado" class="h-full w-full" title="Tutorial para quitar el candado" allowfullscreen></iframe>
                        </div>
                        <a v-else-if="juego.tutorial_candado" :href="juego.tutorial_candado" target="_blank" rel="noopener noreferrer" class="mx-5 mb-5 inline-flex rounded-xl bg-red-600 px-5 py-3 font-bold hover:bg-red-500 sm:mx-6">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>Abrir video
                        </a>
                        <p v-else class="px-5 pb-5 text-sm text-amber-200 sm:px-6">No hay video cargado para esta consola. Escribe a soporte MRJUEGOZ.</p>

                        <div class="border-t border-white/10 p-5 sm:p-6">
                            <p class="text-sm font-bold"><span class="mr-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600 text-xs font-black">2</span>Inicia sesión con estos datos</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Usuario</p>
                                    <p class="mt-1 break-all font-semibold leading-snug">{{ juego.usuario || 'No disponible' }}</p>
                                    <button type="button" class="mt-3 inline-flex items-center rounded-lg bg-red-600 px-3 py-2 text-xs font-bold hover:bg-red-500" @click="copiar(juego.usuario, 'Usuario', 'copiar_usuario')">
                                        <i class="fa-solid fa-copy mr-2"></i>Copiar usuario
                                    </button>
                                </div>
                                <div class="rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Contraseña</p>
                                    <p class="mt-1 break-all font-semibold leading-snug">{{ juego.contrasena || 'No disponible' }}</p>
                                    <button type="button" class="mt-3 inline-flex items-center rounded-lg bg-red-600 px-3 py-2 text-xs font-bold hover:bg-red-500" @click="copiar(juego.contrasena, 'Contraseña', 'copiar_contrasena')">
                                        <i class="fa-solid fa-copy mr-2"></i>Copiar contraseña
                                    </button>
                                </div>
                            </div>

                            <div v-if="verificacion.es_primaria && juego.codigo && !verificacion.es_instalacion && !verificacion.puede_solicitar" class="mt-4 rounded-xl border border-white/10 bg-slate-950 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Código de verificación</p>
                                        <p class="mt-1 break-all text-lg font-semibold leading-snug">{{ juego.codigo }}</p>
                                        <p class="mt-1 text-xs text-slate-400">Úsalo cuando PlayStation te lo pida al iniciar sesión. Es de un solo uso.</p>
                                    </div>
                                    <button type="button" class="shrink-0 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold hover:bg-red-500" @click="copiar(juego.codigo, 'Código', 'copiar_codigo')">
                                        <i class="fa-solid fa-copy mr-1"></i>Copiar
                                    </button>
                                </div>
                            </div>
                            <button v-else-if="verificacion.es_primaria && verificacion.puede_solicitar" type="button" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-black hover:bg-red-500" @click="solicitarCodigo">
                                <i class="fa-solid fa-key mr-2"></i>Generar código de verificación
                            </button>
                            <p v-else-if="verificacion.es_primaria && verificacion.sin_stock" class="mt-4 text-sm text-amber-200">Estamos recargando códigos para esta cuenta. Escribe a soporte MRJUEGOZ.</p>
                            <p v-else-if="verificacion.es_primaria && !verificacion.tiene_correo" class="mt-4 text-sm text-amber-200">Esta cuenta no tiene códigos asociados. Escribe a soporte MRJUEGOZ.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="evidenciaVisible" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4" @click.self="evidenciaVisible = null">
                <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-slate-950 text-white">
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <div>
                            <p class="font-black">Evidencia de instalación</p>
                            <p class="text-xs text-slate-400">{{ fechaHora(evidenciaVisible.enviado_at) }}</p>
                        </div>
                        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 hover:bg-white/20" @click="evidenciaVisible = null"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto p-4">
                        <img v-if="evidenciaVisible.tipo_archivo === 'foto'" :src="evidenciaVisible.archivo_url" alt="Evidencia de instalación" class="mx-auto max-h-[75vh] max-w-full object-contain" />
                        <video v-else :src="evidenciaVisible.archivo_url" controls playsinline class="mx-auto max-h-[75vh] max-w-full"></video>
                    </div>
                </div>
            </div>
        </Teleport>
    </PortalClienteLayout>
</template>
