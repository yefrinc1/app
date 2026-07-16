<script setup>
import { computed, defineProps, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import DangerButton from '@/Components/DangerButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    productos: Array,
    mensaje: String,
    tipo: String,
});

const form = useForm({});

const formMasivo = useForm({
    ids: []
});

// Los productos seleccionados aquí conservarán su oferta.
const idsConservar = ref([]);

const productosAQuitar = computed(() => {
    return props.productos.filter(producto => !idsConservar.value.includes(producto.id));
});

const todosConservados = computed(() => {
    return props.productos.length > 0 && idsConservar.value.length === props.productos.length;
});

function conservarTodos() {
    idsConservar.value = todosConservados.value
        ? []
        : props.productos.map(producto => producto.id);
}

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

function quitarTodasLasOfertas() {
    const idsAQuitar = productosAQuitar.value.map(producto => producto.id);

    if (idsAQuitar.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'No hay ofertas para quitar',
            text: 'Todos los productos están marcados para conservar su oferta.'
        });
        return;
    }

    Swal.fire({
        title: '¿Estás seguro?',
        html: `Se quitará la oferta de <strong>${idsAQuitar.length}</strong> productos.<br>
               Se conservará la oferta de <strong>${idsConservar.value.length}</strong> productos.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, quitar todas',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Quitando todas las ofertas...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            formMasivo.ids = idsAQuitar;

            formMasivo.post(route('productos-oferta-jumpseller.quitar-todas'), {
                onSuccess: () => {
                    Swal.close();
                    Swal.fire('¡Listo!', `Se procesaron ${idsAQuitar.length} ofertas.`, 'success');
                },
                onError: () => {
                    Swal.close();
                    Swal.fire('Error', 'No se pudieron quitar todas las ofertas.', 'error');
                }
            });
        }
    });
}

const formatoPrecio = (valor) => {
    if (valor === null || valor === undefined || valor === '') {
        return '-';
    }

    return `$${Number(valor).toLocaleString('es-CO')}`;
};
</script>

<template>
    <Head title="🪅 Productos en Oferta" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🪅 Productos en Oferta
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
                                    Productos con oferta activa
                                </h2>

                                <p class="text-sm text-gray-500 mt-1">
                                    Revisa los productos que tienen precio comparativo activo en Jumpseller.
                                </p>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-3">
                                <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                    <p class="text-sm text-gray-500">
                                        Productos en oferta
                                    </p>

                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ productos.length }}
                                    </p>
                                </div>

                                <DangerButton
                                    v-if="productos.length > 0"
                                    :disabled="formMasivo.processing || productosAQuitar.length === 0"
                                    @click="quitarTodasLasOfertas"
                                    class="w-full sm:w-auto justify-center"
                                >
                                    <i class="fa-solid fa-ban mr-2"></i>
                                    Quitar restantes ({{ productosAQuitar.length }})
                                </DangerButton>
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
                                    Detalle de ofertas
                                </h3>

                                <p class="text-sm text-gray-500">
                                    Precios actuales y precios comparativos por tipo de licencia.
                                </p>
                            </div>

                            <PrimaryButton
                                v-if="productos.length > 0"
                                type="button"
                                @click="conservarTodos"
                            >
                                {{ todosConservados ? 'Desmarcar todos' : 'Conservar todos' }}
                            </PrimaryButton>
                        </div>

                        <div class="overflow-x-auto shadow rounded-lg">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100 border-b">
                                        <th class="sticky right-0 bg-gray-100 px-4 py-2 z-10 text-center">
                                            Acción
                                        </th>
                                        <th class="px-4 py-2 text-center">Conservar oferta</th>
                                        <th class="px-4 py-2 text-left">Producto</th>
                                        <th class="px-4 py-2 text-left">Primaria PS4</th>
                                        <th class="px-4 py-2 text-left">Primaria PS5</th>
                                        <th class="px-4 py-2 text-left">Secundaria PS4</th>
                                        <th class="px-4 py-2 text-left">Secundaria PS5</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="producto in productos"
                                        :key="producto.id"
                                        class="border-b hover:bg-gray-100"
                                    >
                                        <!-- Acción -->
                                        <td class="sticky right-0 bg-white px-4 py-2 z-10">
                                            <div class="flex justify-center">
                                                <DangerButton
                                                    class="w-full sm:w-auto justify-center"
                                                    :disabled="form.processing"
                                                    @click="quitarOferta(producto.id)"
                                                >
                                                    <i class="fa-solid fa-trash mr-2"></i>
                                                    Quitar
                                                </DangerButton>
                                            </div>
                                        </td>

                                        <!-- Excluir de la eliminación masiva -->
                                        <td class="px-4 py-2 text-center">
                                            <label class="flex items-center">
                                                <Checkbox 
                                                    v-model:checked="idsConservar"
                                                    :value="producto.id" 
                                                />

                                                <span class="font-semibold text-gray-700">
                                                    No quitar
                                                </span>
                                            </label>
                                        </td>

                                        <!-- Producto -->
                                        <td class="px-4 py-2">
                                            <div class="bg-gray-50 rounded-lg p-2 min-w-[280px]">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-14 h-14 rounded-lg bg-white border border-gray-200 flex items-center justify-center overflow-hidden shrink-0">
                                                        <img
                                                            v-if="producto.imagen"
                                                            :src="producto.imagen"
                                                            alt="imagen"
                                                            class="w-full h-full object-cover"
                                                        />

                                                        <i
                                                            v-else
                                                            class="fa-solid fa-gamepad text-gray-400 text-xl"
                                                        ></i>
                                                    </div>

                                                    <div class="min-w-0">
                                                        <p class="font-bold text-gray-900 break-words">
                                                            {{ producto.nombre }}
                                                        </p>

                                                        <p class="text-sm text-gray-500 mt-1">
                                                            ID producto: {{ producto.id }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Primaria PS4 -->
                                        <td class="px-4 py-2">
                                            <div class="inline-flex flex-col gap-1">
                                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap">
                                                    Actual: {{ formatoPrecio(producto.precio_primaria_ps4) }}
                                                </span>

                                                <span
                                                    :class="[
                                                        'px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap',
                                                        producto.compare_primaria_ps4 != null
                                                            ? 'bg-red-100 text-red-800 line-through'
                                                            : 'bg-gray-100 text-gray-500'
                                                    ]"
                                                >
                                                    Antes: {{ formatoPrecio(producto.compare_primaria_ps4) }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Primaria PS5 -->
                                        <td class="px-4 py-2">
                                            <div class="inline-flex flex-col gap-1">
                                                <span class="bg-indigo-100 text-indigo-800 px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap">
                                                    Actual: {{ formatoPrecio(producto.precio_primaria_ps5) }}
                                                </span>

                                                <span
                                                    :class="[
                                                        'px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap',
                                                        producto.compare_primaria_ps5 != null
                                                            ? 'bg-red-100 text-red-800 line-through'
                                                            : 'bg-gray-100 text-gray-500'
                                                    ]"
                                                >
                                                    Antes: {{ formatoPrecio(producto.compare_primaria_ps5) }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Secundaria PS4 -->
                                        <td class="px-4 py-2">
                                            <div class="inline-flex flex-col gap-1">
                                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap">
                                                    Actual: {{ formatoPrecio(producto.precio_secundaria_ps4) }}
                                                </span>

                                                <span
                                                    :class="[
                                                        'px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap',
                                                        producto.compare_secundaria_ps4 != null
                                                            ? 'bg-red-100 text-red-800 line-through'
                                                            : 'bg-gray-100 text-gray-500'
                                                    ]"
                                                >
                                                    Antes: {{ formatoPrecio(producto.compare_secundaria_ps4) }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Secundaria PS5 -->
                                        <td class="px-4 py-2">
                                            <div class="inline-flex flex-col gap-1">
                                                <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap">
                                                    Actual: {{ formatoPrecio(producto.precio_secundaria_ps5) }}
                                                </span>

                                                <span
                                                    :class="[
                                                        'px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap',
                                                        producto.compare_secundaria_ps5 != null
                                                            ? 'bg-red-100 text-red-800 line-through'
                                                            : 'bg-gray-100 text-gray-500'
                                                    ]"
                                                >
                                                    Antes: {{ formatoPrecio(producto.compare_secundaria_ps5) }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="productos.length === 0">
                                        <td class="px-4 py-10 text-center" colspan="7">
                                            <div class="flex flex-col items-center gap-3">
                                                <div class="w-16 h-16 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                                                    <i class="fa-solid fa-tags text-2xl"></i>
                                                </div>

                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">
                                                        No hay productos en oferta
                                                    </h3>

                                                    <p class="text-sm text-gray-500">
                                                        Actualmente no hay productos con precio comparativo activo.
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
