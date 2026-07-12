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
                    swalWithTailwind.fire({
                        title: 'Pago realizado correctamente',
                        icon: 'success',
                    });

                    form.reset();
                },
                onError: () => {
                    swalWithTailwind.fire({
                        title: 'Hubo un error al completar el pago',
                        icon: 'error',
                    });
                },
            });
        }
    });
};

const formatoCop = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
};

const formatearFecha = (fecha) => {
    if (!fecha) return 'Sin pagos';
    
    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(fecha));
};

const totalPendiente = () => {
    return props.ventas_ultimo_pago.reduce((total, item) => {
        return total + Number(item.porcentaje || 0);
    }, 0);
};
</script>

<template>
    <Head title="Pagos 💰" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                💰 Pagos
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <!-- Header -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">
                                    Pagos pendientes
                                </h2>

                                <p class="text-sm text-gray-500 mt-1">
                                    Consulta las ventas realizadas por usuario y confirma el pago correspondiente.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                    <p class="text-sm text-gray-500">
                                        Usuarios pendientes
                                    </p>

                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ ventas_ultimo_pago.length }}
                                    </p>
                                </div>

                                <div class="bg-green-50 border border-green-100 rounded-xl px-4 py-3">
                                    <p class="text-sm text-green-700">
                                        Total pendiente
                                    </p>

                                    <p class="text-2xl font-bold text-green-800">
                                        ${{ formatoCop(totalPendiente()) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Tabla -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">
                                    Detalle de pagos
                                </h3>

                                <p class="text-sm text-gray-500">
                                    El valor corresponde al porcentaje calculado sobre las ventas.
                                </p>
                            </div>

                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold w-fit">
                                {{ ventas_ultimo_pago.length }} registros
                            </span>
                        </div>

                        <div class="overflow-x-auto shadow rounded-lg">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100 border-b">
                                        <th class="sticky right-0 bg-gray-100 px-4 py-2 z-10 text-center">
                                            Acción
                                        </th>
                                        <th class="px-4 py-2 text-left">Usuario</th>
                                        <th class="px-4 py-2 text-left">Ventas</th>
                                        <th class="px-4 py-2 text-left">Porcentaje 6%</th>
                                        <th class="px-4 py-2 text-left">Último pago</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="info_pago in ventas_ultimo_pago"
                                        :key="info_pago.id_usuario"
                                        class="border-b hover:bg-gray-100"
                                    >
                                        <!-- Acción -->
                                        <td class="sticky right-0 bg-white px-4 py-2 z-10">
                                            <div class="flex justify-center">
                                                <PrimaryButton
                                                    class="w-full sm:w-auto justify-center"
                                                    @click="completarPago(
                                                        info_pago.id_usuario,
                                                        info_pago.nombre,
                                                        info_pago.porcentaje
                                                    )"
                                                >
                                                    <i class="fa-solid fa-money-bill mr-2"></i>
                                                    Pagar
                                                </PrimaryButton>
                                            </div>
                                        </td>

                                        <!-- Usuario -->
                                        <td class="px-4 py-2">
                                            <div class="bg-gray-50 rounded-lg p-2 min-w-[220px]">
                                                <div class="flex items-center gap-2 text-md">
                                                    <i class="fa-solid fa-user text-blue-500"></i>

                                                    <span class="font-bold text-gray-900">
                                                        {{ info_pago.nombre }}
                                                    </span>
                                                </div>

                                                <div class="flex items-center gap-2 text-sm text-gray-500 mt-1">
                                                    <i class="fa-solid fa-id-badge text-gray-400"></i>
                                                    <span>ID usuario: {{ info_pago.id_usuario }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Ventas -->
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-3 py-1 rounded-lg text-sm font-bold">
                                                <i class="fa-solid fa-cart-shopping"></i>
                                                {{ info_pago.total_ventas }} ventas
                                            </span>
                                        </td>

                                        <!-- Porcentaje -->
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-gradient-to-r from-green-100 to-green-200 text-green-900 px-3 py-1 rounded-lg text-sm font-bold shadow-sm">
                                                <i class="fa-solid fa-money-bill-wave"></i>
                                                ${{ formatoCop(info_pago.porcentaje) }}
                                            </span>
                                        </td>

                                        <!-- Fecha -->
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-sm font-bold">
                                                <i class="fa-regular fa-calendar"></i>
                                                {{ formatearFecha(info_pago.fecha_ultimo_pago) }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="ventas_ultimo_pago.length === 0">
                                        <td class="px-4 py-10 text-center" colspan="5">
                                            <div class="flex flex-col items-center gap-3">
                                                <div class="w-16 h-16 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                                                    <i class="fa-solid fa-money-bill-wave text-2xl"></i>
                                                </div>

                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">
                                                        No hay pagos pendientes
                                                    </h3>

                                                    <p class="text-sm text-gray-500">
                                                        No tienes pagos registrados para confirmar.
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </LayoutPageHeader>
</template>