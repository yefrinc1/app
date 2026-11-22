<script setup>
import { Link, Head, useForm } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    correos_madres: Object,
    mensaje_correo_creado: { type: String, default: '' },
});

const form = useForm({});

if (props.mensaje_correo_creado) {
    Swal.fire({
        title: props.mensaje_correo_creado,
        icon: 'success',
        confirmButtonColor: '#16a34a',
    });
}

const quitarHijo = (id) => {
    Swal.fire({
        title: '¿Seguro que deseas quitar este hijo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        reverseButtons: true,
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        form.get(route('correo-madre.quitarHijo', id), {
            onSuccess: () => {
                Swal.fire({
                    title: 'Hijo eliminado correctamente',
                    icon: 'success',
                    confirmButtonColor: '#16a34a',
                });
            },
            onError: () => {
                Swal.fire({
                    title: 'Hubo un error al quitar el hijo',
                    icon: 'error',
                    confirmButtonColor: '#dc2626',
                });
            },
        });
    });
};

const formatearFecha = (fecha) => {
    if (!fecha) {
        return '—';
    }
    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(fecha));
};

const hijos = props.correos_madres.correos_juegos ?? [];
</script>

<template>
    <Head :title="`Madre ${correos_madres.correo}`" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">👤 Correo madre</h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6 p-4 sm:p-8">
                <section class="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-md">
                    <div class="h-1 bg-gradient-to-r from-red-600 via-red-500 to-amber-500"></div>
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-red-600">Madre</p>
                                <div class="mt-3 flex items-center gap-2 text-lg font-semibold text-gray-900">
                                    <i class="fa-solid fa-envelope text-red-500"></i>
                                    <span class="break-all">{{ correos_madres.correo }}</span>
                                </div>
                                <div class="mt-2 flex items-center gap-2 text-base text-gray-700">
                                    <i class="fa-solid fa-key text-amber-500"></i>
                                    <span>{{ correos_madres.contrasena }}</span>
                                </div>
                            </div>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold',
                                    correos_madres.disponible == 1
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-red-100 text-red-800',
                                ]"
                            >
                                <i :class="correos_madres.disponible == 1 ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark'"></i>
                                {{ correos_madres.disponible == 1 ? 'Disponible' : 'Completa' }}
                            </span>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-3 py-1 text-xs font-bold text-red-800">
                                <i class="fa-solid fa-users"></i>
                                {{ hijos.length }}/6 hijos
                            </span>
                            <span class="rounded-lg bg-yellow-100 px-3 py-1 text-xs font-bold text-yellow-900">
                                USD {{ correos_madres.saldo_usd }}
                            </span>
                            <span class="rounded-lg bg-green-100 px-3 py-1 text-xs font-bold text-green-900">
                                COP ${{ Number(correos_madres.saldo_cop).toLocaleString('es-CO') }}
                            </span>
                            <span class="rounded-lg bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                                Nacimiento {{ formatearFecha(correos_madres.fecha_nacimiento) }}
                            </span>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl bg-white p-4 shadow-md sm:p-6">
                    <header class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Hijos</h3>
                        <p class="mt-1 text-sm text-gray-600">Cuentas ligadas a esta madre.</p>
                    </header>

                    <div v-if="hijos.length" class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gradient-to-r from-red-50 via-white to-amber-50 text-left text-gray-600">
                                    <th class="px-4 py-3 font-semibold">Correo</th>
                                    <th class="px-4 py-3 font-semibold">Contraseña</th>
                                    <th class="px-4 py-3 font-semibold">Juego</th>
                                    <th class="px-4 py-3 font-semibold">Nacimiento</th>
                                    <th class="px-4 py-3 text-center font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="correo in hijos"
                                    :key="correo.id"
                                    class="border-t border-gray-100 transition hover:bg-amber-50/40"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2 font-medium text-gray-800">
                                            <i class="fa-solid fa-envelope text-red-400"></i>
                                            <span class="break-all">{{ correo.correo }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <i class="fa-solid fa-key text-amber-500"></i>
                                            <span>{{ correo.contrasena }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            v-if="correo.juego"
                                            class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800"
                                        >
                                            <i class="fa-solid fa-gamepad"></i>
                                            {{ correo.juego }}
                                        </span>
                                        <span v-else class="text-gray-400">Sin juego</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ formatearFecha(correo.fecha_nacimiento) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-center">
                                            <DangerButton class="h-8 w-8" @click="quitarHijo(correo.id)">
                                                <i class="fa-solid fa-trash"></i>
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                        No tiene hijos registrados.
                    </p>
                </section>

                <div class="flex flex-wrap justify-center gap-3">
                    <Link :href="route('correo-madre.index')">
                        <SecondaryButton>Ver madres</SecondaryButton>
                    </Link>
                    <Link :href="route('correo-juegos.creacion-completa')">
                        <PrimaryButton>Creación completa</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>
    </LayoutPageHeader>
</template>
