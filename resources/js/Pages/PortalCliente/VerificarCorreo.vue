<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { confirmarOperacion } from '@/Utils/alertas';
import PortalClienteLayout from '@/Layouts/PortalClienteLayout.vue';
import { mostrarCarga, mostrarErrores, mostrarExito } from '@/Utils/alertas';

const props = defineProps({ email: String, enviado: Boolean, errorCorreo: String });
const emailActual = ref(props.email);
const correoEnviado = ref(props.enviado);
const correoError = ref(props.errorCorreo);
const editandoCorreo = ref(false);
const guardandoCorreo = ref(false);
const erroresCorreo = ref({});
const cambioCorreo = reactive({ email: '', email_confirmation: '', current_password: '' });
const abrirCambio = () => {
    cambioCorreo.email = emailActual.value;
    cambioCorreo.email_confirmation = '';
    cambioCorreo.current_password = '';
    erroresCorreo.value = {};
    editandoCorreo.value = true;
};
const cerrarCambio = () => {
    if (guardandoCorreo.value) return;
    cambioCorreo.current_password = '';
    editandoCorreo.value = false;
};
const guardarCorreo = async () => {
    if (guardandoCorreo.value || enviando.value) return;
    const confirmado = await confirmarOperacion({
        titulo: '¿Cambiar tu correo?',
        texto: 'Usarás la nueva dirección para iniciar sesión y recibir el enlace de verificación.',
        confirmButtonText: 'Sí, cambiar correo',
    });
    if (!confirmado) return;
    guardandoCorreo.value = true;
    erroresCorreo.value = {};
    mostrarCarga('Actualizando correo…', 'Guardando la nueva dirección y enviando la verificación.');
    try {
        const { data } = await axios.post(route('portal.correo.cambiar'), { ...cambioCorreo });
        emailActual.value = data.email;
        correoEnviado.value = data.enviado;
        correoError.value = data.enviado ? '' : data.message;
        editandoCorreo.value = false;
        espera.value = data.enviado ? 60 : 0;
        if (data.enviado) {
            await mostrarExito('Correo actualizado', data.message);
        } else {
            await mostrarErrores({ mensaje: data.message }, 'Correo actualizado; envío pendiente');
        }
    } catch (error) {
        erroresCorreo.value = error.response?.data?.errors || {};
        await mostrarErrores(
            error.response?.status === 429
                ? { mensaje: 'Has intentado cambiarlo varias veces. Espera unos minutos e inténtalo de nuevo.' }
                : error.response?.data?.errors || { mensaje: 'No se pudo actualizar el correo. Revisa la conexión e inténtalo nuevamente.' }
        );
    } finally {
        cambioCorreo.current_password = '';
        guardandoCorreo.value = false;
    }
};

const enviando = ref(false);
const espera = ref(0);
let temporizador;
onMounted(() => {
    if (props.enviado) espera.value = 60;
    temporizador = setInterval(() => {
        if (espera.value > 0) espera.value--;
    }, 1000);
});
onUnmounted(() => clearInterval(temporizador));
const reenviar = async () => {
    if (enviando.value || guardandoCorreo.value || espera.value > 0) return;
    enviando.value = true;
    mostrarCarga('Enviando correo…', 'Preparando tu enlace de verificación.');
    try {
        await axios.post(route('portal.correo.enviar'));
        espera.value = 60;
        correoEnviado.value = true;
        correoError.value = '';
        await mostrarExito('Correo enviado', 'Revisa tu bandeja de entrada y la carpeta de spam.');
    } catch (error) {
        if (error.response?.status === 429) {
            espera.value = 60;
            await mostrarErrores({ mensaje: 'Espera un minuto antes de solicitar otro correo.' });
        } else {
            await mostrarErrores({ mensaje: error.response?.data?.message || 'No se pudo enviar. Intenta nuevamente o comunícate con soporte.' });
        }
    } finally {
        enviando.value = false;
    }
};
const continuar = () => router.visit(route('portal.index'));
</script>

<template>
    <Head title="Verifica tu correo · MRJUEGOZ" />
    <PortalClienteLayout>
        <section class="mx-auto max-w-xl rounded-2xl border border-white/10 bg-white/5 p-6 shadow-xl sm:p-8">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-500/15 text-3xl text-blue-300"><i class="fa-solid fa-envelope-circle-check"></i></div>
            <h1 class="mt-5 text-center text-2xl font-black">Confirma tu correo</h1>
            <p class="mt-3 text-center text-sm leading-relaxed text-slate-300">Tu cuenta ya está creada. Verifica tu correo para consultar los juegos y sus datos de instalación.</p>
            <p class="mt-4 break-all rounded-xl border border-white/10 bg-slate-950/50 p-3 text-center font-bold">{{ emailActual }}</p>
            <p v-if="correoEnviado" class="mt-4 rounded-xl border border-green-500/20 bg-green-500/10 p-3 text-sm text-green-200" role="status">Te enviamos el enlace de verificación.</p>
            <p v-if="correoError" class="mt-4 rounded-xl border border-amber-500/20 bg-amber-500/10 p-3 text-sm text-amber-200" role="alert">{{ correoError }}</p>
            <div class="mt-4">
                <button v-if="!editandoCorreo" type="button" class="text-sm font-bold text-blue-300 underline hover:text-blue-200 disabled:opacity-50" :disabled="enviando || guardandoCorreo" @click="abrirCambio">¿Ingresaste mal el correo? Cambiar correo</button>
                <form v-else class="space-y-4 rounded-xl border border-white/10 bg-slate-950/50 p-4" @submit.prevent="guardarCorreo">
                    <h2 class="font-bold">Corregir mi correo</h2>
                    <p class="text-sm text-slate-300">La nueva dirección también será tu correo para iniciar sesión. Tus compras y contraseña se conservan.</p>
                    <div>
                        <label for="nuevo-correo-portal" class="text-sm font-semibold">Nuevo correo</label>
                        <input id="nuevo-correo-portal" v-model="cambioCorreo.email" type="email" autocomplete="email" maxlength="150" required :disabled="guardandoCorreo" class="mt-1 block w-full min-w-0 rounded-lg border-white/20 bg-slate-900 text-white focus:border-blue-400 focus:ring-blue-400" />
                        <p v-for="mensaje in erroresCorreo.email || []" :key="mensaje" class="mt-1 text-sm text-red-300">{{ mensaje }}</p>
                    </div>
                    <div>
                        <label for="confirmar-correo-portal" class="text-sm font-semibold">Repite el nuevo correo</label>
                        <input id="confirmar-correo-portal" v-model="cambioCorreo.email_confirmation" type="email" autocomplete="off" maxlength="150" required :disabled="guardandoCorreo" class="mt-1 block w-full min-w-0 rounded-lg border-white/20 bg-slate-900 text-white focus:border-blue-400 focus:ring-blue-400" />
                    </div>
                    <div>
                        <label for="password-cambiar-correo" class="text-sm font-semibold">Tu contraseña actual</label>
                        <input id="password-cambiar-correo" v-model="cambioCorreo.current_password" type="password" autocomplete="current-password" required :disabled="guardandoCorreo" class="mt-1 block w-full min-w-0 rounded-lg border-white/20 bg-slate-900 text-white focus:border-blue-400 focus:ring-blue-400" />
                        <p v-for="mensaje in erroresCorreo.current_password || []" :key="mensaje" class="mt-1 text-sm text-red-300">{{ mensaje }}</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="submit" :disabled="guardandoCorreo || enviando" class="rounded-xl bg-red-600 px-4 py-3 text-sm font-bold hover:bg-red-500 disabled:opacity-50">{{ guardandoCorreo ? 'Guardando…' : 'Cambiar y enviar verificación' }}</button>
                        <button type="button" :disabled="guardandoCorreo" class="rounded-xl border border-white/15 px-4 py-3 text-sm font-bold disabled:opacity-50" @click="cerrarCambio">Cancelar</button>
                    </div>
                </form>
            </div>
            <ol class="mt-5 list-decimal space-y-3 pl-5 text-sm text-slate-300">
                <li>Abre el correo de MRJUEGOZ. Si no aparece, revisa spam o correo no deseado.</li>
                <li>Pulsa <strong class="text-white">Verificar mi correo</strong>. El enlace vence en 60 minutos.</li>
                <li>Si se solicita, inicia sesión con este correo y tu contraseña.</li>
            </ol>
            <div class="mt-6 flex flex-col gap-3">
                <button type="button" class="rounded-xl bg-red-600 px-4 py-3 font-bold transition hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50" :disabled="enviando || guardandoCorreo || espera > 0" @click="reenviar">
                    {{ enviando ? 'Enviando…' : espera > 0 ? 'Reenviar en ' + espera + ' s' : 'Reenviar correo de verificación' }}
                </button>
                <button type="button" class="rounded-xl border border-white/15 bg-white/5 px-4 py-3 font-bold transition hover:bg-white/10" @click="continuar">Ya verifiqué mi correo, continuar</button>
                <Link :href="route('logout')" method="post" as="button" class="py-2 text-center text-sm text-slate-400 hover:text-white">Cerrar sesión</Link>
            </div>
        </section>
    </PortalClienteLayout>
</template>
