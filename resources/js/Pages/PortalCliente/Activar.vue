<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ token: String, cliente: Object, expiresAt: String });
const form = useForm({
    name: props.cliente?.nombre ?? '',
    codigo_pais: props.cliente?.codigo_pais ?? '57',
    telefono: props.cliente?.telefono ?? '',
    usuario: props.cliente?.usuario ?? '',
    email: props.cliente?.email ?? '',
    password: '',
    password_confirmation: '',
});
const enviar = () => form.post(route('portal.activar.store', props.token), {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <GuestLayout>
        <Head title="Activar portal de clientes" />
        <div class="mb-6 text-center"><h1 class="text-2xl font-black text-gray-900">Activa tu portal MRJUEGOZ</h1><p class="mt-2 text-sm text-gray-600">Crea tus datos de acceso para consultar tus juegos y tutoriales.</p></div>
        <div class="mb-5 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900"><p class="font-bold"><i class="fa-solid fa-shield-halved mr-2"></i>Compra identificada</p><p class="mt-1">{{ cliente.contacto }}</p><p class="mt-2 text-xs text-blue-700">Verifica tus datos. Los campos que no estén registrados deben completarse para activar la cuenta.</p></div>
        <form class="space-y-4" @submit.prevent="enviar">
            <div><InputLabel for="portal-name" value="Nombre" /><TextInput id="portal-name" v-model="form.name" required autofocus autocomplete="name" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.name" /></div>
            <div>
                <InputLabel value="Teléfono" />
                <div class="mt-1 grid grid-cols-[80px_minmax(0,1fr)] gap-2">
                    <div><div class="flex h-[42px] items-center rounded-md border border-gray-300 bg-white shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500"><span class="pl-3 text-sm text-gray-500">+</span><input id="portal-country-code" v-model="form.codigo_pais" required inputmode="numeric" autocomplete="tel-country-code" maxlength="5" class="min-w-0 w-full border-0 bg-transparent px-1 py-2 text-sm text-gray-900 outline-none ring-0 focus:ring-0" aria-label="Código de país" /></div><InputError class="mt-2" :message="form.errors.codigo_pais" /></div>
                    <div><TextInput id="portal-phone" v-model="form.telefono" required type="tel" inputmode="numeric" autocomplete="tel-national" placeholder="3001234567" class="block w-full" /><InputError class="mt-2" :message="form.errors.telefono" /></div>
                </div>
            </div>
            <div><InputLabel for="portal-user" value="Usuario de Instagram" /><div class="mt-1 flex rounded-md shadow-sm"><span class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">@</span><input id="portal-user" v-model="form.usuario" required type="text" autocomplete="off" placeholder="usuario" class="min-w-0 w-full rounded-r-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" /></div><InputError class="mt-2" :message="form.errors.usuario" /></div>
            <div><InputLabel for="portal-email" value="Correo para iniciar sesión" /><TextInput id="portal-email" v-model="form.email" required type="email" autocomplete="username" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.email" /></div>
            <div><InputLabel for="portal-password" value="Contraseña" /><TextInput id="portal-password" v-model="form.password" required type="password" autocomplete="new-password" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.password" /></div>
            <div><InputLabel for="portal-password-confirmation" value="Confirmar contraseña" /><TextInput id="portal-password-confirmation" v-model="form.password_confirmation" required type="password" autocomplete="new-password" class="mt-1 block w-full" /><InputError class="mt-2" :message="form.errors.password_confirmation" /></div>
            <InputError :message="form.errors.token" />
            <PrimaryButton class="w-full justify-center py-3" :disabled="form.processing"><i class="fa-solid fa-user-check mr-2"></i>{{ form.processing ? 'Activando…' : 'Activar mi cuenta' }}</PrimaryButton>
        </form>
        <p class="mt-5 text-center text-sm text-gray-500">¿Ya activaste tu cuenta? <Link :href="route('login')" class="font-bold text-red-600 hover:underline">Iniciar sesión</Link></p>
    </GuestLayout>
</template>
