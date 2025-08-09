<script setup>
import { defineProps } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    ventas_ultimo_pago: Array,
});

const form = useForm({
    id_usuario: '',
    nombre: '',
    valor: '',
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

const completarPago = (id, nombre, valor) => {
    form.id_usuario = id;
    form.nombre = nombre;
    form.valor = valor;

    swalWithTailwind.fire({
        title: '¿Seguro que deseas confirmar el pago?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, pagar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.post(route("pagos.store"), {
                onSuccess: () => {
                    // Acción después de la eliminación exitosa
                    swalWithTailwind.fire({
                        title: 'Pago realizado correctamente',
                        icon: 'success',
                    });

                    form.reset();
                },
                onError: () => {
                    // Acción si hay un error
                    swalWithTailwind.fire({
                        title: 'Hubo un error al completar el pago',
                        icon: 'error',
                    });
                },
            });
        }
    });
};

</script>

<template>

    <Head title="Pagos 💰" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">💰 Pagos </h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Nombre</th>
                                    <th class="px-4 py-2 text-left">Ventas</th>
                                    <th class="px-4 py-2 text-left">Porcentaje 6%</th>
                                    <th class="px-4 py-2 text-left">Fecha Ultimo Pago</th>
                                    <th class="px-4 py-2 text-center">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="info_pago in ventas_ultimo_pago" :key="info_pago.id_usuario" class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ info_pago.nombre }}</td>
                                    <td class="px-4 py-2">{{ info_pago.total_ventas }}</td>
                                    <td class="px-4 py-2">{{ info_pago.porcentaje }}</td>
                                    <td class="px-4 py-2">{{ info_pago.fecha_ultimo_pago }}</td>
                                    <td class="px-4 py-2 flex justify-center space-x-2">
                                        <PrimaryButton @click="completarPago(info_pago.id_usuario, info_pago.nombre, info_pago.porcentaje)">
                                            <i class="fa-solid fa-money-bill mr-2"></i> Pagar
                                        </PrimaryButton>
                                    </td>
                                </tr>
                                <tr v-if="ventas_ultimo_pago.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="8">
                                        No hay pagos registrados.
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
