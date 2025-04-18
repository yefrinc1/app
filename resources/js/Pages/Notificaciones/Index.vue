<script setup>
import { defineProps } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
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
        title: '¿Seguro que deseas confirmar la notificacion?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("notificaciones.destroy", id), {
                onSuccess: () => {
                    // Acción después de la eliminación exitosa
                    swalWithTailwind.fire({
                        title: 'Notificacion completada',
                        icon: 'success',
                    });
                },
                onError: () => {
                    // Acción si hay un error
                    swalWithTailwind.fire({
                        title: 'Hubo un error al completar la notificacion',
                        icon: 'error',
                    });
                },
            });
        }
    });
};

</script>

<template>

    <Head title="Notificaciones" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🔔 Notificaciones</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Descripcion</th>
                                    <th class="px-4 py-2 text-center">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="notificacion in notificaciones" :key="notificacion.id" class="border-b hover:bg-gray-100">
                                    <td v-if="notificacion.tipo == 'crear_codigos'" class="px-4 py-2">
                                        {{ notificacion.mensaje }} para el correo <strong>{{ notificacion.correo }}</strong>
                                    </td>
                                    <td v-if="notificacion.tipo == 'agotado_juego'" class="px-4 py-2">
                                        {{ notificacion.mensaje }} para el juego <strong>{{ notificacion.juego }}</strong>
                                    </td>
                                    <td v-if="notificacion.tipo == 'crear_juego'" class="px-4 py-2">
                                        {{ notificacion.mensaje }} para el juego <strong>{{ notificacion.juego }}</strong>
                                    </td>
                                    <td class="px-4 py-2 flex justify-center space-x-2">
                                        <PrimaryButton @click="completarNotificacion(notificacion.id)">
                                            <i class="fa-solid fa-check"></i>
                                        </PrimaryButton>
                                    </td>
                                </tr>
                                <tr v-if="notificaciones.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="8">
                                        No hay notificaciones registradas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>
