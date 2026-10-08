<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import PortalClienteLayout from '@/Layouts/PortalClienteLayout.vue';
import { mostrarCarga, mostrarErrores, mostrarExito } from '@/Utils/alertas';

const props = defineProps({ email: String, enviado: Boolean, errorCorreo: String });
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
    if (enviando.value || espera.value > 0) return;
    enviando.value = true;
    mostrarCarga('Enviando correo…', 'Preparando tu enlace de verificación.');
    try {
        await axios.post(route('portal.correo.enviar'));
        espera.value = 60;
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
            <p class="mt-4 break-all rounded-xl border border-white/10 bg-slate-950/50 p-3 text-center font-bold">{{ email }}</p>
            <p v-if="enviado" class="mt-4 rounded-xl border border-green-500/20 bg-green-500/10 p-3 text-sm text-green-200" role="status">Te enviamos el enlace de verificación.</p>
            <p v-if="errorCorreo" class="mt-4 rounded-xl border border-amber-500/20 bg-amber-500/10 p-3 text-sm text-amber-200" role="alert">{{ errorCorreo }}</p>
            <ol class="mt-5 list-decimal space-y-3 pl-5 text-sm text-slate-300">
                <li>Abre el correo de MRJUEGOZ. Si no aparece, revisa spam o correo no deseado.</li>
                <li>Pulsa <strong class="text-white">Verificar mi correo</strong>. El enlace vence en 60 minutos.</li>
                <li>Si se solicita, inicia sesión con este correo y tu contraseña.</li>
            </ol>
            <div class="mt-6 flex flex-col gap-3">
                <button type="button" class="rounded-xl bg-red-600 px-4 py-3 font-bold transition hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50" :disabled="enviando || espera > 0" @click="reenviar">
                    {{ enviando ? 'Enviando…' : espera > 0 ? 'Reenviar en ' + espera + ' s' : 'Reenviar correo de verificación' }}
                </button>
                <button type="button" class="rounded-xl border border-white/15 bg-white/5 px-4 py-3 font-bold transition hover:bg-white/10" @click="continuar">Ya verifiqué mi correo, continuar</button>
                <Link :href="route('logout')" method="post" as="button" class="py-2 text-center text-sm text-slate-400 hover:text-white">Cerrar sesión</Link>
            </div>
        </section>
    </PortalClienteLayout>
</template>
