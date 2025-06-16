<script setup>
import { defineProps } from "vue";
import { Link, Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { nextTick, ref } from 'vue';
import Modal from '@/Components/Modal.vue';

const searchQuery = ref('');
const confirmingCierreCrear = ref(false);
const saldoInput = ref(null);

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

// Función para realizar la búsqueda
const buscarFecha = () => {
    form.get(route('cierre-caja.index', { search: searchQuery.value }), {
        preserveScroll: true,
    });
};

// Confirmación antes de eliminar un correo
const eliminarCierre = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas eliminar este correo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("cierre-caja.destroy", id), {
                onSuccess: () => {
                    // Acción después de la eliminación exitosa
                    swalWithTailwind.fire({
                        title: 'Cierre de caja eliminado correctamente',
                        icon: 'success',
                    });
                },
                onError: () => {
                    // Acción si hay un error
                    swalWithTailwind.fire({
                        title: 'Hubo un error al eliminar el cierre de caja',
                        icon: 'error',
                    });
                },
            });
        }
    });
};

const abrirModalCrear = () => {
    form.reset();
    confirmingCierreCrear.value = true;
    nextTick(() => saldoInput.value.focus());
};

const closeModal = () => {
    confirmingCierreCrear.value = false;
    form.reset();
};

const crearPrimerCierre = () => {
    form.post(route("cierre-caja.store"), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: "Primer cierre creado",
                icon: "success",
            });
            closeModal();
        },
    });
};
</script>

<template>

    <Head title="Cierre de Caja" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🎰 Cierre de Caja</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <!-- Filtro de búsqueda -->
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex space-x-4 w-full md:w-1/6">
                            <TextInput v-model="searchQuery" type="date" class="block w-full" />
                            <PrimaryButton @click="buscarFecha">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </PrimaryButton>
                        </div>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Fecha</th>
                                    <th class="px-4 py-2 text-left">Saldo Inicial</th>
                                    <th class="px-4 py-2 text-center">Ingresos</th>
                                    <th class="px-4 py-2 text-center">Egresos</th>
                                    <th class="px-4 py-2 text-center">Saldo Final</th>
                                    <th class="px-4 py-2 text-center">Observaciones</th>
                                    <th class="px-4 py-2 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="cierre_caja in cierre_cajas" :key="cierre_caja.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ cierre_caja.fecha }}</td>
                                    <td class="px-4 py-2">{{ cierre_caja.saldo_inicial }}</td>
                                    <td class="px-4 py-2">{{ cierre_caja.ingresos }}</td>
                                    <td class="px-4 py-2">{{ cierre_caja.egresos }}</td>
                                    <td class="px-4 py-2">{{ cierre_caja.saldo_final }}</td>
                                    <td class="px-4 py-2">{{ cierre_caja.observaciones }}</td>
                                    <td class="px-4 py-2 flex justify-center space-x-2">
                                        <DangerButton @click="eliminarCierre(cierre_caja.id)">
                                            <i class="fa-solid fa-trash"></i>
                                        </DangerButton>
                                    </td>
                                </tr>
                                <tr v-if="cierre_cajas.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="7">
                                        <PrimaryButton @click="abrirModalCrear">
                                            + Primer cierre de caja
                                        </PrimaryButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Modal :show="confirmingCierreCrear" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Crear nuevo cierre de caja</h2>

                    <div class="mt-6">
                        <InputLabel for="saldo_final" value="Saldo" />
                        <TextInput id="saldo_final" ref="saldoInput" v-model="form.saldo_final" type="text" class="mt-1 block w-full"/>
                        <InputError :message="form.errors.saldo_final" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="fecha" value="Fecha" />
                        <TextInput id="fecha" v-model="form.fecha" type="date" class="mt-1 block w-full"/>
                        <InputError :message="form.errors.fecha" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal">Cancelar</SecondaryButton>

                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                            @click="crearPrimerCierre">
                            Crear
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>
