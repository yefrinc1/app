<script setup>
import { defineProps } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { ref } from 'vue';

const searchQuery = ref('');

const props = defineProps({
    cierre_cajas: Object,
    search: String,
    mensaje: String,
});

const form = useForm({
    fecha: '',
    saldo_inicial: 0,
    ingresos: 0,
    egresos: 0,
    saldo_final: '',
});

searchQuery.value = props.search;

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

if (props.mensaje) {
    swalWithTailwind.fire({
        title: props.mensaje,
        icon: 'success',
    });
}

const buscarFecha = () => {
    router.get(
        route('cierre-caja.index'),
        searchQuery.value ? { search: searchQuery.value } : {},
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const eliminarCierre = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas eliminar este cierre de caja?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Sí, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("cierre-caja.destroy", id), {
                preserveScroll: true,
                onSuccess: () => {
                    swalWithTailwind.fire({
                        title: 'Cierre de caja eliminado correctamente',
                        icon: 'success',
                    });
                },
                onError: () => {
                    swalWithTailwind.fire({
                        title: 'Hubo un error al eliminar el cierre de caja',
                        icon: 'error',
                    });
                },
            });
        }
    });
};

const formatearFecha = (fecha) => {
    if (!fecha) return "";

    const [year, month, day] = fecha.split("-");

    return `${day}/${month}/${year}`;
};

const formatoCop = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
};

const calcularDiferencia = (cierre) => {
    return Number(cierre.saldo_final || 0) - Number(cierre.saldo_inicial || 0);
};
</script>

<template>
    <Head title="Cierre de Caja" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🎰 Cierre de Caja
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">
                                Historial de cierres
                            </h3>

                            <p class="text-sm text-gray-500">
                                Consulta los cierres de caja registrados por fecha.
                            </p>
                        </div>

                        <div class="flex gap-2 w-full sm:w-auto">
                            <TextInput
                                v-model="searchQuery"
                                type="date"
                                class="w-full sm:w-48"
                                @keyup.enter="buscarFecha"
                            />

                            <PrimaryButton
                                @click="buscarFecha"
                                class="w-10 h-10 flex items-center justify-center !px-0 !py-0"
                            >
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </PrimaryButton>
                        </div>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="sticky right-0 bg-gray-100 px-4 py-2 z-10 text-center">
                                        Acciones
                                    </th>
                                    <th class="px-4 py-2 text-left">Fecha</th>
                                    <th class="px-4 py-2 text-left">Saldos</th>
                                    <th class="px-4 py-2 text-left">Movimientos</th>
                                    <th class="px-4 py-2 text-left">Resultado</th>
                                    <th class="px-4 py-2 text-left">Observaciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="cierre_caja in cierre_cajas"
                                    :key="cierre_caja.id"
                                    class="border-b hover:bg-gray-100"
                                >
                                    <td class="sticky right-0 bg-white px-4 py-2 z-10">
                                        <div class="flex flex-col items-center gap-2">
                                            <DangerButton
                                                class="w-8 h-8 flex items-center justify-center"
                                                @click="eliminarCierre(cierre_caja.id)"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </DangerButton>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-sm font-bold">
                                            <i class="fa-regular fa-calendar"></i>
                                            {{ formatearFecha(cierre_caja.fecha) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2">
                                        <div class="inline-flex flex-col gap-1">
                                            <span class="bg-gradient-to-r from-blue-100 to-blue-200 text-blue-900 px-3 py-1 rounded-lg text-sm font-bold shadow-sm">
                                                Inicial: ${{ formatoCop(cierre_caja.saldo_inicial) }}
                                            </span>

                                            <span class="bg-gradient-to-r from-green-100 to-green-200 text-green-900 px-3 py-1 rounded-lg text-sm font-bold shadow-sm">
                                                Final: ${{ formatoCop(cierre_caja.saldo_final) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <div class="inline-flex flex-col gap-1">
                                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-lg text-sm font-bold">
                                                Ingresos: ${{ formatoCop(cierre_caja.ingresos) }}
                                            </span>

                                            <span class="bg-red-100 text-red-800 px-3 py-1 rounded-lg text-sm font-bold">
                                                Egresos: ${{ formatoCop(cierre_caja.egresos) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold',
                                                calcularDiferencia(cierre_caja) >= 0
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            ]"
                                        >
                                            <i
                                                :class="calcularDiferencia(cierre_caja) >= 0
                                                    ? 'fa-solid fa-circle-check'
                                                    : 'fa-solid fa-circle-xmark'"
                                            ></i>

                                            ${{ formatoCop(calcularDiferencia(cierre_caja)) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2">
                                        <div class="max-w-[260px]">
                                            <span
                                                v-if="cierre_caja.observaciones"
                                                class="text-sm text-gray-700"
                                            >
                                                {{ cierre_caja.observaciones }}
                                            </span>

                                            <span
                                                v-else
                                                class="text-sm text-gray-400"
                                            >
                                                Sin observaciones
                                            </span>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="cierre_cajas.length === 0">
                                    <td class="px-4 py-10 text-center" colspan="6">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="w-16 h-16 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                                                <i class="fa-solid fa-cash-register text-2xl"></i>
                                            </div>

                                            <div>
                                                <h3 class="text-lg font-bold text-gray-900">
                                                    No hay cierres registrados
                                                </h3>

                                                <p class="text-sm text-gray-500">
                                                    No hay cierres de caja para mostrar.
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
        </template>
    </LayoutPageHeader>
</template>