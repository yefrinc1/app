<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ canResetPassword: Boolean, status: String });
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <GuestLayout>
        <Head title="Iniciar sesión" />
        <div class="mb-6 text-center"><h1 class="text-2xl font-black text-gray-900">Bienvenido a MRJUEGOZ</h1><p class="mt-1 text-sm text-gray-500">Accede al panel o consulta tus juegos como cliente.</p></div>
        <div v-if="status" class="mb-4 rounded-xl bg-green-50 p-3 text-sm font-medium text-green-700">{{ status }}</div>
        <form class="space-y-4" @submit.prevent="submit">
            <div><InputLabel for="email" value="Correo" /><TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required autofocus autocomplete="username" /><InputError class="mt-2" :message="form.errors.email" /></div>
            <div><InputLabel for="password" value="Contraseña" /><TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required autocomplete="current-password" /><InputError class="mt-2" :message="form.errors.password" /></div>
            <label class="flex items-center"><Checkbox v-model:checked="form.remember" name="remember" /><span class="ms-2 text-sm text-gray-600">Recordarme</span></label>
            <PrimaryButton class="w-full justify-center py-3" :disabled="form.processing"><i class="fa-solid fa-right-to-bracket mr-2"></i>{{ form.processing ? 'Ingresando…' : 'Iniciar sesión' }}</PrimaryButton>
        </form>
        <div class="mt-5 flex flex-col gap-3 text-center text-sm"><Link v-if="canResetPassword" :href="route('password.request')" class="font-semibold text-red-600 hover:underline">¿Olvidaste tu contraseña?</Link><p class="rounded-xl bg-gray-50 p-3 text-xs text-gray-500">Los clientes nuevos deben abrir el enlace privado enviado por MRJUEGOZ para activar su cuenta.</p></div>
    </GuestLayout>
</template>
