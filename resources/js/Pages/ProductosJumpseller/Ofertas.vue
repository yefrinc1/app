<script setup>
import { defineProps, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    productos: Array,
    mensaje: String,
    tipo: String,
});

const form = useForm({});

function quitarOferta(id) {
    Swal.fire({
        title: 'Quitando oferta...',
        text: 'Por favor espera',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    form.delete(route('productos-oferta-jumpseller.quitar-oferta', { id }), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.close();
            if (props.mensaje) {
                Swal.fire({
                    icon: props.tipo,
                    title: props.mensaje,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        onError: () => {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Hubo un error al quitar la oferta.',
            });
        }
    });
}
</script>

<template>
    <Head title="🪅 Productos en Oferta" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🪅 Productos en Oferta</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-center">Imagen</th>
                                    <th class="px-4 py-2 text-center">Nombre</th>
                                    <th class="px-4 py-2 text-center">Primaria PS4</th>
                                    <th class="px-4 py-2 text-center">Compare Primaria PS4</th>
                                    <th class="px-4 py-2 text-center">Primaria PS5</th>
                                    <th class="px-4 py-2 text-center">Compare Primaria PS5</th>
                                    <th class="px-4 py-2 text-center">Secundaria PS4</th>
                                    <th class="px-4 py-2 text-center">Compare Secundaria PS4</th>
                                    <th class="px-4 py-2 text-center">Secundaria PS5</th>
                                    <th class="px-4 py-2 text-center">Compare Secundaria PS5</th>
                                    <th class="px-4 py-2 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="producto in productos" :key="producto.id" class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">
                                        <img v-if="producto.imagen" :src="producto.imagen" alt="imagen" width="50" />
                                    </td>
                                    <td class="px-4 py-2">{{ producto.nombre }}</td>
                                    <td class="px-4 py-2 text-center">{{ producto.precio_primaria_ps4 ?? '-' }}</td>
                                    <td :class="['px-4 py-2 text-center', producto.compare_primaria_ps4 != null ? 'line-through' : '']">{{ producto.compare_primaria_ps4 ?? '-' }}</td>
                                    <td class="px-4 py-2 text-center">{{ producto.precio_primaria_ps5 ?? '-' }}</td>
                                    <td :class="['px-4 py-2 text-center', producto.compare_primaria_ps5 != null ? 'line-through' : '']">{{ producto.compare_primaria_ps5 ?? '-' }}</td>
                                    <td class="px-4 py-2 text-center">{{ producto.precio_secundaria_ps4 ?? '-' }}</td>
                                    <td :class="['px-4 py-2 text-center', producto.compare_secundaria_ps4 != null ? 'line-through' : '']">{{ producto.compare_secundaria_ps4 ?? '-' }}</td>
                                    <td class="px-4 py-2 text-center">{{ producto.precio_secundaria_ps5 ?? '-' }}</td>
                                    <td :class="['px-4 py-2 text-center', producto.compare_secundaria_ps5 != null ? 'line-through' : '']">{{ producto.compare_secundaria_ps5 ?? '-' }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <DangerButton
                                            :disabled="form.processing"
                                            @click="quitarOferta(producto.id)"
                                        >
                                            QUITAR
                                        </DangerButton>
                                    </td>
                                </tr>
                                <tr v-if="productos.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="12">
                                        No hay productos en oferta
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