<script setup>
import { defineProps } from "vue";
import { Link, Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    correos_madres: Object,
});

const form = useForm({ });

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

if (props.mensaje_correo_creado == '') {
    swalWithTailwind.fire({
        title: props.mensaje_correo_creado,
        icon: "success",
    });
}

// Confirmación antes de eliminar un correo
const quitarHijo = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas quitar este hijo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.get(route("correo-madre.quitarHijo", id), {
                onSuccess: () => {
                    // Acción después de la eliminación exitosa
                    swalWithTailwind.fire({
                        title: 'Hijo eliminado correctamente',
                        icon: 'success',
                    });
                },
                onError: () => {
                    // Acción si hay un error
                    swalWithTailwind.fire({
                        title: 'Hubo un error al quitar el hijo',
                        icon: 'error',
                    });
                },
            });
        }
    });
};

</script>

<template>

    <Head title="Hijos de la Madre" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">👤 Hijos de la madre: {{ correos_madres.id }}</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Id</th>
                                    <th class="px-4 py-2 text-left">Correo</th>
                                    <th class="px-4 py-2 text-left">Contraseña</th>
                                    <th class="px-4 py-2 text-left">Juego</th>
                                    <th class="px-4 py-2 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="correo in correos_madres.correos_juegos" :key="correo.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ correo.id }}</td>
                                    <td class="px-4 py-2">{{ correo.correo }} </td>
                                    <td class="px-4 py-2">{{ correo.contrasena }}</td>
                                    <td class="px-4 py-2">{{ correo.juego }}</td>
                                    <td class="px-4 py-2 flex justify-center space-x-2">
                                        <DangerButton @click="quitarHijo(correo.id)">
                                            <i class="fa-solid fa-trash"></i>
                                        </DangerButton>
                                    </td>
                                </tr>

                                <tr v-if="correos_madres.correos_juegos.length == 0">
                                    <td class="px-4 py-2 text-center" colspan="8">
                                        No tiene hijos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-center">
                        <Link :href="route('correo-madre.index')">
                            <SecondaryButton>VOLVER</SecondaryButton>
                        </Link>
                    </div>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>
