<script setup>
import { defineProps } from "vue";
import { Head, useForm, Link } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    notificaciones: Object,
});

const form = useForm({});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

const completarNotificacion = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas completar esta notificación?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Sí, completar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("notificaciones.destroy", id), {
                preserveScroll: true,
                onSuccess: () => {
                    swalWithTailwind.fire({
                        title: 'Notificación completada',
                        icon: 'success',
                    });
                },
                onError: () => {
                    swalWithTailwind.fire({
                        title: 'Hubo un error al completar la notificación',
                        icon: 'error',
                    });
                },
            });
        }
    });
};

const iconoNotificacion = (tipo) => {
    if (tipo === 'crear_codigos') return 'fa-solid fa-key';
    if (tipo === 'agotado_juego') return 'fa-solid fa-triangle-exclamation';
    if (tipo === 'crear_juego') return 'fa-solid fa-gamepad';
    return 'fa-solid fa-bell';
};

const colorNotificacion = (tipo) => {
    if (tipo === 'crear_codigos') return 'bg-amber-100 text-amber-600';
    if (tipo === 'agotado_juego') return 'bg-red-100 text-red-600';
    if (tipo === 'crear_juego') return 'bg-blue-100 text-blue-600';
    return 'bg-gray-100 text-gray-600';
};

const badgeNotificacion = (tipo) => {
    if (tipo === 'crear_codigos') {
        return {
            texto: 'Crear códigos',
            clase: 'bg-amber-100 text-amber-700',
        };
    }

    if (tipo === 'agotado_juego') {
        return {
            texto: 'Juego agotado',
            clase: 'bg-red-100 text-red-700',
        };
    }

    if (tipo === 'crear_juego') {
        return {
            texto: 'Crear juego',
            clase: 'bg-blue-100 text-blue-700',
        };
    }

    return {
        texto: 'Notificación',
        clase: 'bg-gray-100 text-gray-700',
    };
};

const formatearFecha = (fecha) => {
    if (!fecha) return '';

    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(fecha));
};
</script>

<template>
    <Head title="Notificaciones" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <div class="flex items-center gap-2">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    🔔 Notificaciones
                </h2>

                <span
                    v-if="notificaciones.length > 0"
                    class="bg-red-100 text-red-700 px-2 py-1 rounded-full text-sm font-bold"
                >
                    {{ notificaciones.length }} pendientes
                </span>
            </div>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <!-- Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">
                                Centro de notificaciones
                            </h3>
                            <p class="text-sm text-gray-500">
                                Revisa las tareas pendientes y márcalas como completadas.
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-lg px-4 py-3 border border-gray-100">
                            <p class="text-xs text-gray-500">Pendientes</p>
                            <p class="text-2xl font-bold text-gray-900">
                                {{ notificaciones.length }}
                            </p>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="notificaciones.length === 0"
                        class="bg-gray-50 border border-dashed border-gray-300 rounded-xl p-10 text-center"
                    >
                        <div class="mx-auto w-16 h-16 rounded-full bg-green-100 text-green-600 flex items-center justify-center mb-4">
                            <i class="fa-solid fa-circle-check text-3xl"></i>
                        </div>

                        <h3 class="text-lg font-bold text-gray-900">
                            Todo está al día
                        </h3>

                        <p class="text-sm text-gray-500 mt-1">
                            No tienes notificaciones pendientes por revisar.
                        </p>
                    </div>

                    <!-- Cards -->
                    <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <div
                            v-for="(notificacion, i) in notificaciones"
                            :key="notificacion.id"
                            class="relative bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-all"
                        >
                            <div class="flex flex-col md:flex-row md:items-start gap-4">
                                <!-- Icono -->
                                <div
                                    class="w-12 h-12 rounded-full flex items-center justify-center shrink-0"
                                    :class="colorNotificacion(notificacion.tipo)"
                                >
                                    <i :class="iconoNotificacion(notificacion.tipo)" class="text-xl"></i>
                                </div>

                                <!-- Contenido -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="text-xs text-gray-400 font-semibold">
                                            #{{ i + 1 }}
                                        </span>

                                        <span
                                            class="px-3 py-1.5 rounded-full text-sm font-bold"
                                            :class="badgeNotificacion(notificacion.tipo).clase"
                                        >
                                            {{ badgeNotificacion(notificacion.tipo).texto }}
                                        </span>
                                    </div>

                                    <div class="text-base text-gray-700 leading-relaxed">
                                        <template v-if="notificacion.tipo == 'crear_codigos'">
                                            {{ notificacion.mensaje }} para el correo

                                            <Link
                                                :href="route('codigo-verificacion.create', { correo: notificacion.correo })"
                                                class="font-bold text-blue-700 underline hover:text-blue-900"
                                            >
                                                {{ notificacion.correo }}
                                            </Link>
                                        </template>

                                        <template v-else-if="notificacion.tipo == 'agotado_juego'">
                                            {{ notificacion.mensaje }} para el juego
                                            <strong class="text-gray-900">{{ notificacion.juego }}</strong>
                                        </template>

                                        <template v-else-if="notificacion.tipo == 'crear_juego'">
                                            {{ notificacion.mensaje }} para el juego
                                            <strong class="text-gray-900">{{ notificacion.juego }}</strong>
                                        </template>

                                        <template v-else>
                                            {{ notificacion.mensaje }}
                                        </template>
                                    </div>

                                    <div class="flex items-center gap-2 text-sm text-gray-400 mt-3">
                                        <i class="fa-regular fa-clock"></i>
                                        <span>{{ formatearFecha(notificacion.created_at) }}</span>
                                    </div>
                                </div>

                                <!-- Acción -->
                                <button
                                    @click="completarNotificacion(notificacion.id)"
                                    class="w-full md:w-14 h-14 rounded-xl md:rounded-full bg-green-100 text-green-700 hover:bg-green-600 hover:text-white transition-all flex items-center justify-center shrink-0 shadow-sm"
                                    title="Completar notificación"
                                >
                                    <span class="hidden md:inline">
                                        <i class="fa-solid fa-check text-2xl"></i>
                                    </span>

                                    <span class="inline-flex md:hidden items-center gap-2 font-bold text-base">
                                        <i class="fa-solid fa-check text-xl"></i>
                                        Completar
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>