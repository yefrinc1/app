<script setup>
import PortalClienteLayout from '@/Layouts/PortalClienteLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { computed, ref } from 'vue';
import axios from 'axios';
import { copiarTexto } from '@/Utils/portapapeles';

const props = defineProps({ juego: Object, recomendaciones: Array, garantia: Object });
const evidenciaForm = useForm({ archivo: null, declaracion: false });
const nombreArchivo = ref('');
const evidenciaVisible = ref(null);
const instalacionPrimaria = computed(() => {
    if (String(props.juego.tipo_cuenta).toLowerCase() !== 'primaria') return null;
    const consola = String(props.juego.consola).toUpperCase();
    if (!['PS4', 'PS5'].includes(consola)) return null;
    const esPs5 = consola === 'PS5';
    return {
        consola,
        imagen: `/images/portal/garantia-primaria-${consola.toLowerCase()}.jpeg`,
        texto: 'Es muy importante que agregues el usuario como nuevo y no como invitado. Sigue los pasos del video de instalación y, al finalizar, sube una foto con la sesión de la cuenta entregada cerrada. Así podremos revisar la configuración y brindarte la garantía correspondiente.',
        pie: esPs5
            ? 'Referencia PS5: pulsa Cerrar sesión. La foto que debes subir es posterior a este paso y debe mostrar que la sesión ya está cerrada.'
            : 'Referencia PS4: con la sesión cerrada, Administración de cuentas muestra la opción Iniciar sesión.',
    };
});
const instalacionSecundaria = computed(() => {
    if (String(props.juego.tipo_cuenta).toLowerCase() !== 'secundaria') return null;
    const consola = String(props.juego.consola).toUpperCase();
    if (!['PS4', 'PS5'].includes(consola)) return null;
    return {
        consola,
        minuto: consola === 'PS5' ? '1:20' : '0:40',
        accion: consola === 'PS5' ? 'desactivar Uso compartido de consola y juego offline' : 'desactivar la cuenta como PS4 principal',
    };
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
    `🛡️ Código de verificación: ${props.juego.codigo || 'Solicitar a soporte'}`,
].join('\n'));
const youtubeEmbed = computed(() => {
    const url = props.juego.tutorial;
    if (!url) return null;
    const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([^?&/]+)/i);
    return match ? `https://www.youtube.com/embed/${match[1]}` : null;
});
const garantiaTexto = computed(() => ({ activa: 'Garantía activa', en_revision: 'Evidencia en revisión', requiere_correccion: 'Requiere corrección', pendiente: 'Evidencia pendiente' }[props.garantia.estado] ?? 'Evidencia pendiente'));
const garantiaColor = computed(() => ({ activa: 'border-green-400/30 bg-green-400/10 text-green-200', en_revision: 'border-blue-400/30 bg-blue-400/10 text-blue-200', requiere_correccion: 'border-amber-400/30 bg-amber-400/10 text-amber-200', pendiente: 'border-slate-400/20 bg-white/5 text-slate-200' }[props.garantia.estado]));
const fechaHora = (valor) => valor ? new Date(valor).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) : '';
const elegirArchivo = (evento) => {
    const archivo = evento.target.files?.[0] ?? null;
    evidenciaForm.archivo = archivo;
    nombreArchivo.value = archivo?.name ?? '';
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
        <Link :href="route('portal.index')" class="inline-flex items-center text-sm font-bold text-slate-300 hover:text-white"><i class="fa-solid fa-arrow-left mr-2"></i>Volver a mis juegos</Link>
        <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,0.8fr)]">
            <div class="space-y-6">
                <section class="overflow-hidden rounded-3xl border border-white/10 bg-slate-900"><div class="grid sm:grid-cols-[minmax(220px,320px)_minmax(0,1fr)]"><div class="mx-auto aspect-square w-full max-w-[420px] bg-slate-950 p-3 sm:max-w-none"><img v-if="juego.imagen" :src="juego.imagen" :alt="juego.juego" class="h-full w-full object-contain" /><div v-else class="flex h-full items-center justify-center text-6xl">🎮</div></div><div class="flex min-w-0 flex-col justify-center border-t border-white/10 p-5 sm:border-l sm:border-t-0 sm:p-6"><div class="flex flex-wrap gap-2"><span class="rounded-full bg-red-500/15 px-3 py-1 text-xs font-bold text-red-300">{{ juego.tipo_cuenta }}</span><span class="rounded-full bg-blue-500/15 px-3 py-1 text-xs font-bold text-blue-300">{{ juego.consola }}</span><span v-if="juego.pedido" class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-slate-300">{{ juego.pedido }}</span></div><h1 class="mt-4 break-words text-2xl font-black sm:text-3xl">{{ juego.juego }}</h1></div></div></section>
                <section class="rounded-3xl border border-amber-400/20 bg-amber-400/10 p-6"><h2 class="font-black text-amber-200"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Antes de instalar</h2><ol class="mt-4 space-y-3 text-sm text-amber-50"><li v-for="(item, indice) in recomendaciones" :key="item" class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-300 font-black text-slate-900">{{ indice + 1 }}</span><span>{{ item }}</span></li></ol></section>
                <section v-if="juego.tutorial" class="rounded-3xl border border-white/10 bg-slate-900 p-6"><h2 class="text-xl font-black"><i class="fa-solid fa-circle-play mr-2 text-red-500"></i>Tutorial de instalación</h2><div v-if="youtubeEmbed" class="mt-4 aspect-video overflow-hidden rounded-2xl"><iframe :src="youtubeEmbed" class="h-full w-full" title="Tutorial de instalación" allowfullscreen @load="registrarAcceso('abrir_tutorial')"></iframe></div><a v-else :href="juego.tutorial" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex rounded-xl bg-red-600 px-5 py-3 font-bold hover:bg-red-500" @click="registrarAcceso('abrir_tutorial')"><i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>Abrir tutorial</a></section>
                <section class="rounded-3xl border p-6" :class="garantiaColor">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p class="text-xs font-black uppercase tracking-[0.18em] opacity-70">Validación de instalación</p><h2 class="mt-2 text-xl font-black"><i class="fa-solid fa-shield-halved mr-2"></i>{{ garantiaTexto }}</h2></div><span class="w-fit rounded-full border border-current/20 px-3 py-1 text-xs font-black">{{ garantia.tipo_requerido === 'foto' ? 'Foto obligatoria' : 'Video obligatorio' }}</span></div>
                    <div v-if="garantia.estado === 'activa'" class="mt-5 rounded-2xl bg-black/20 p-4 text-sm"><p class="font-bold">La instalación fue verificada correctamente.</p><p class="mt-1 opacity-80">Conserva la configuración y no modifiques los datos de la cuenta.</p></div>
                    <div v-else-if="garantia.estado === 'en_revision'" class="mt-5 rounded-2xl bg-black/20 p-4 text-sm"><p class="font-bold">Tu evidencia fue recibida.</p><p class="mt-1 opacity-80">MRJUEGOZ la revisará antes de activar la garantía.</p></div>
                    <template v-else>
                        <div v-if="garantia.estado === 'requiere_correccion'" class="mt-5 rounded-2xl border border-amber-300/20 bg-black/20 p-4 text-sm"><p class="font-black">Debes enviar una nueva evidencia</p><p class="mt-2 whitespace-pre-line">{{ garantia.evidencias[0]?.motivo_rechazo }}</p></div>
                        <div v-if="instalacionPrimaria" class="mt-6 overflow-hidden rounded-2xl border border-white/15 bg-black/20">
                            <div class="p-4"><h3 class="font-black">🎮 Instalación primaria {{ instalacionPrimaria.consola }}</h3><p class="mt-3 text-sm leading-6">{{ instalacionPrimaria.texto }}</p><p class="mt-3 text-xs font-bold text-amber-200">Sube una foto propia de tu consola. No subas esta imagen de ejemplo.</p></div>
                            <img :src="instalacionPrimaria.imagen" :alt="`Guía para cerrar sesión en ${instalacionPrimaria.consola}`" class="max-h-[420px] w-full bg-black object-contain" loading="lazy" />
                            <p class="p-4 text-sm leading-6">{{ instalacionPrimaria.pie }}</p>
                        </div>
                        <div v-if="instalacionSecundaria" class="mt-6 rounded-2xl border border-red-400/30 bg-red-400/10 p-4 text-sm leading-6">
                            <h3 class="font-black">🎥 Video obligatorio: secundaria {{ instalacionSecundaria.consola }}</h3>
                            <p class="mt-2">Agrega el usuario como nuevo, no como invitado. Sigue el tutorial y presta especial atención al minuto <strong>{{ instalacionSecundaria.minuto }}</strong>, cuando se explica cómo {{ instalacionSecundaria.accion }}.</p>
                            <p class="mt-2 font-bold">Graba un video propio, continuo y sin cortes mientras desactivas esta opción. Muestra el estado antes y después. Es el paso más importante para revisar y activar tu garantía.</p>
                        </div>
                        <h3 class="mt-6 font-black">Cómo debe verse la evidencia</h3>
                        <ol class="mt-3 space-y-3 text-sm"><li v-for="(item, indice) in garantia.instrucciones" :key="item" class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 text-xs font-black">{{ indice + 1 }}</span><span>{{ item }}</span></li></ol>
                        <div v-if="garantia.puede_enviar" class="mt-6 rounded-2xl border border-white/10 bg-black/20 p-4">
                            <label class="block text-sm font-black">{{ garantia.tipo_requerido === 'foto' ? 'Seleccionar foto' : 'Seleccionar video' }}</label>
                            <label class="mt-2 flex min-w-0 cursor-pointer items-center gap-3 overflow-hidden rounded-xl border border-dashed border-white/25 bg-white/5 px-4 py-4 hover:border-red-400"><i class="fa-solid fa-cloud-arrow-up shrink-0 text-xl"></i><span class="min-w-0 flex-1 truncate text-sm">{{ nombreArchivo || `Archivo máximo ${garantia.max_mb} MB` }}</span><input type="file" class="sr-only" :accept="garantia.accept" @change="elegirArchivo" /></label>
                            <p v-if="evidenciaForm.errors.archivo" class="mt-2 text-sm text-red-300">{{ evidenciaForm.errors.archivo }}</p>
                            <label class="mt-4 flex cursor-pointer items-start gap-3 text-sm"><input v-model="evidenciaForm.declaracion" type="checkbox" class="mt-1 rounded border-white/30 bg-slate-900 text-red-600 focus:ring-red-500" /><span>Confirmo que no modifiqué el correo, contraseña, verificación, seguridad ni otra configuración de la cuenta.</span></label>
                            <p v-if="evidenciaForm.errors.declaracion" class="mt-2 text-sm text-red-300">{{ evidenciaForm.errors.declaracion }}</p>
                            <button type="button" class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 font-black text-white hover:bg-red-500 disabled:opacity-60" :disabled="evidenciaForm.processing" @click="enviarEvidencia"><i class="fa-solid fa-paper-plane mr-2"></i>{{ evidenciaForm.processing ? 'Subiendo…' : 'Enviar evidencia para revisión' }}</button>
                        </div>
                    </template>
                    <div v-if="garantia.evidencias.length" class="mt-6 border-t border-white/10 pt-5"><h3 class="text-sm font-black">Historial de evidencias</h3><div class="mt-3 space-y-2"><button v-for="evidencia in garantia.evidencias" :key="evidencia.id" type="button" class="flex w-full items-center justify-between gap-3 rounded-xl bg-black/20 p-3 text-left text-sm hover:bg-black/30" @click="evidenciaVisible = evidencia"><span class="min-w-0"><span class="block font-bold">{{ evidencia.tipo_archivo === 'foto' ? 'Foto' : 'Video' }} · {{ fechaHora(evidencia.enviado_at) }}</span><span v-if="evidencia.motivo_rechazo" class="mt-1 block truncate text-xs opacity-70">{{ evidencia.motivo_rechazo }}</span></span><span class="shrink-0 capitalize">{{ evidencia.estado.replaceAll('_', ' ') }} <i class="fa-solid fa-eye ml-2"></i></span></button></div></div>
                </section>
                <section v-if="garantia.estado === 'activa'" class="rounded-3xl border border-green-400/25 bg-green-400/10 p-6 text-green-50">
                    <h2 class="text-xl font-black">Cuenta {{ String(juego.tipo_cuenta).toLowerCase() === 'primaria' ? 'Primaria' : 'Secundaria' }}: recomendaciones finales</h2>
                    <p class="mt-2 text-sm">{{ String(juego.tipo_cuenta).toLowerCase() === 'secundaria' ? 'Para mantener tu garantía de por vida, sigue estas instrucciones:' : 'Para que disfrutes al máximo y mantengas tu garantía, sigue estas recomendaciones:' }}</p>
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm"><li v-for="item in recomendacionesFinales" :key="item">{{ item }}</li></ul>
                    <p class="mt-4 text-sm">{{ String(juego.tipo_cuenta).toLowerCase() === 'primaria' ? 'Guarda bien tus datos: no ofrecemos garantía por su pérdida.' : 'No somos responsables por la pérdida de los datos de acceso.' }} Si necesitas ayuda con tu juego o garantía, contacta a soporte MRJUEGOZ. 👊🏻🎮</p>
                </section>
            </div>
            <aside class="h-fit rounded-3xl border border-red-500/20 bg-gradient-to-br from-slate-900 to-red-950/40 p-6 shadow-2xl lg:sticky lg:top-28"><h2 class="text-xl font-black"><i class="fa-solid fa-key mr-2 text-red-500"></i>Datos del juego</h2><p class="mt-1 text-sm text-slate-400">Pulsa cada campo para copiarlo.</p><div class="mt-6 space-y-4"><button type="button" class="w-full rounded-2xl border border-white/10 bg-black/20 p-4 text-left hover:border-red-500/40" @click="copiar(juego.usuario, 'Usuario', 'copiar_usuario')"><span class="text-xs font-bold uppercase text-slate-500">👤 Usuario</span><span class="mt-1 block break-all font-mono font-bold">{{ juego.usuario || 'No disponible' }}</span></button><button type="button" class="w-full rounded-2xl border border-white/10 bg-black/20 p-4 text-left hover:border-red-500/40" @click="copiar(juego.contrasena, 'Contraseña', 'copiar_contrasena')"><span class="text-xs font-bold uppercase text-slate-500">🔒 Contraseña</span><span class="mt-1 block break-all font-mono font-bold">{{ juego.contrasena || 'No disponible' }}</span></button><button type="button" class="w-full rounded-2xl border p-4 text-left" :class="juego.codigo ? 'border-white/10 bg-black/20 hover:border-red-500/40' : 'border-amber-400/20 bg-amber-400/10'" @click="copiar(juego.codigo, 'Código', 'copiar_codigo')"><span class="text-xs font-bold uppercase text-slate-500">🛡️ Código de verificación</span><span class="mt-1 block break-all font-mono font-bold">{{ juego.codigo || 'Solicitar a soporte' }}</span><span v-if="!juego.codigo" class="mt-2 block text-xs text-amber-200">Las ventas antiguas no guardaban la relación con el código utilizado.</span></button></div><button type="button" class="mt-6 w-full rounded-xl bg-red-600 px-5 py-3 font-black hover:bg-red-500" @click="copiar(mensajeCompleto, 'Todos los datos', 'copiar_todos')"><i class="fa-solid fa-copy mr-2"></i>Copiar todos los datos</button><p class="mt-4 rounded-xl border border-amber-400/20 bg-amber-400/10 p-3 text-xs text-amber-100"><i class="fa-solid fa-circle-info mr-2"></i>Después de instalar, envía la evidencia solicitada para validar la configuración y activar la garantía.</p><p class="mt-3 text-[11px] leading-5 text-slate-500"><i class="fa-solid fa-lock mr-1"></i>Por seguridad se registran la fecha, dirección IP y navegador al consultar o copiar estas credenciales.</p></aside>
        </div>
        <Teleport to="body"><div v-if="evidenciaVisible" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4" @click.self="evidenciaVisible = null"><div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-slate-950 text-white"><div class="flex items-center justify-between border-b border-white/10 px-4 py-3"><div><p class="font-black">Evidencia de instalación</p><p class="text-xs text-slate-400">{{ fechaHora(evidenciaVisible.enviado_at) }}</p></div><button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 hover:bg-white/20" @click="evidenciaVisible = null"><i class="fa-solid fa-xmark"></i></button></div><div class="min-h-0 flex-1 overflow-auto p-4"><img v-if="evidenciaVisible.tipo_archivo === 'foto'" :src="evidenciaVisible.archivo_url" alt="Evidencia de instalación" class="mx-auto max-h-[75vh] max-w-full object-contain" /><video v-else :src="evidenciaVisible.archivo_url" controls playsinline class="mx-auto max-h-[75vh] max-w-full"></video></div></div></div></Teleport>
    </PortalClienteLayout>
</template>
