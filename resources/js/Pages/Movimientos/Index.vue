<script setup>
import { defineProps } from "vue";
import { Link, Head, useForm } from "@inertiajs/vue3";
import Modal from '@/Components/Modal.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { ref } from 'vue';

const confirmingCorreoEditar = ref(false);
const confirmingCorreoCrear = ref(false);

const props = defineProps({
    movimientos: Object,
});

const form = useForm({
    id: 0,
    tipo: '',
    descripcion: '',
    valor: '',
    observaciones: '',
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

const crearModal = () => {
    form.reset();
    confirmingCorreoCrear.value = true;
};

const editarModal = (id, tipo, descripcion, valor, observaciones) => {
    form.id = id;
    form.tipo = tipo;
    form.descripcion = descripcion;
    form.valor = valor;
    form.observaciones = observaciones;
    confirmingCorreoEditar.value = true;
};

const crearMovimiento = () => {
    form.post(route("movimientos.store"), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: "Movimiento creado correctamente",
                icon: "success",
            });
            closeModal();
        },
    });
};

const editarMovimiento = () => {
    form.put(route("movimientos.update", form.id), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: "Movimiento actualizado correctamente",
                icon: "success",
            });
            closeModal();
        },
    });
};

const closeModal = () => {
    confirmingCorreoEditar.value = false;
    confirmingCorreoCrear.value = false;
    form.reset();
};

const eliminarMovimiento = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas eliminar este movimiento?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("movimientos.destroy", id), {
                preserveScroll: true,
                onSuccess: () => {
                    swalWithTailwind.fire({
                        title: 'Movimiento eliminado correctamente',
                        icon: 'success',
                    });
                },
                onError: () => {
                    swalWithTailwind.fire({
                        title: 'Hubo un error al eliminar el movimiento',
                        icon: 'error',
                    });
                },
            });
        }
    });
};

const formatearFecha = (fecha) => {
    if (!fecha) return "";

    return new Intl.DateTimeFormat("es-CO", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric"
    }).format(new Date(fecha));
};

const formatoCop = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
};

const claseTipo = (tipo) => {
    if (tipo === 'Ingreso') {
        return 'bg-green-100 text-green-800';
    }

    if (tipo === 'Egreso') {
        return 'bg-red-100 text-red-800';
    }

    if (tipo === 'Retiro Utilidades') {
        return 'bg-yellow-100 text-yellow-800';
    }

    return 'bg-gray-100 text-gray-800';
};

const iconoTipo = (tipo) => {
    if (tipo === 'Ingreso') {
        return 'fa-solid fa-arrow-trend-up';
    }

    if (tipo === 'Egreso') {
        return 'fa-solid fa-arrow-trend-down';
    }

    if (tipo === 'Retiro Utilidades') {
        return 'fa-solid fa-money-bill-transfer';
    }

    return 'fa-solid fa-circle-info';
};
</script>

<template>
    <Head title="Movimientos" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🔄 Movimientos
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">
                                Registro de movimientos
                            </h3>
                            <p class="text-sm text-gray-500">
                                Controla ingresos, egresos y retiros de utilidades.
                            </p>
                        </div>

                        <PrimaryButton
                            @click="crearModal()"
                            class="w-full sm:w-auto justify-center"
                        >
                            + Nuevo Movimiento
                        </PrimaryButton>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="sticky right-0 bg-gray-100 px-4 py-2 z-10 text-center">
                                        Acciones
                                    </th>
                                    <th class="px-4 py-2 text-left">Movimiento</th>
                                    <th class="px-4 py-2 text-left">Valor</th>
                                    <th class="px-4 py-2 text-left">Observaciones</th>
                                    <th class="px-4 py-2 text-left">Fecha</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="movimiento in props.movimientos.data"
                                    :key="movimiento.id"
                                    class="border-b hover:bg-gray-100"
                                >
                                    <td class="sticky right-0 bg-white px-4 py-2 z-10">
                                        <div class="flex flex-col items-center gap-2">
                                            <SecondaryButton
                                                class="w-8 h-8 flex items-center justify-center"
                                                @click="editarModal(
                                                    movimiento.id,
                                                    movimiento.tipo,
                                                    movimiento.descripcion,
                                                    movimiento.valor,
                                                    movimiento.observaciones
                                                )"
                                            >
                                                <i class="fa-solid fa-user-pen"></i>
                                            </SecondaryButton>

                                            <DangerButton
                                                class="w-8 h-8 flex items-center justify-center"
                                                @click="eliminarMovimiento(movimiento.id)"
                                            >
                                                <i class="fa-solid fa-trash"></i>
                                            </DangerButton>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <div class="bg-gray-50 rounded-lg p-2 min-w-[230px]">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    :class="[
                                                        'inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold',
                                                        claseTipo(movimiento.tipo)
                                                    ]"
                                                >
                                                    <i :class="iconoTipo(movimiento.tipo)"></i>
                                                    {{ movimiento.tipo }}
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-2 text-sm text-gray-700 mt-2">
                                                <i class="fa-solid fa-align-left text-blue-500"></i>
                                                <span class="font-medium">
                                                    {{ movimiento.descripcion || 'Sin descripción' }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <span
                                            :class="[
                                                'inline-flex items-center px-3 py-1 rounded-lg text-sm font-bold shadow-sm',
                                                movimiento.tipo === 'Ingreso'
                                                    ? 'bg-gradient-to-r from-green-100 to-green-200 text-green-900'
                                                    : movimiento.tipo === 'Egreso'
                                                        ? 'bg-gradient-to-r from-red-100 to-red-200 text-red-900'
                                                        : 'bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-900'
                                            ]"
                                        >
                                            COP ${{ formatoCop(movimiento.valor) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2">
                                        <div class="max-w-[260px]">
                                            <span
                                                v-if="movimiento.observaciones"
                                                class="text-smtext-gray-700"
                                            >
                                                {{ movimiento.observaciones }}
                                            </span>

                                            <span
                                                v-else
                                                class="text-smtext-gray-400"
                                            >
                                                Sin observaciones
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-1 rounded-lg text-xs font-semibold">
                                            <i class="fa-regular fa-calendar"></i>
                                            {{ formatearFecha(movimiento.created_at) }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="props.movimientos.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="5">
                                        No hay movimientos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="mt-4">
                        <div class="flex flex-wrap justify-center gap-2">
                            <template
                                v-for="(link, index) in props.movimientos.links"
                                :key="index"
                            >
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    v-html="link.label"
                                    class="px-3 py-2 border rounded-md text-xs font-semibold transition ease-in-out duration-150"
                                    :class="link.active
                                        ? 'bg-gray-800 text-white border-gray-800'
                                        : 'bg-gray-200 text-gray-700 border-transparent hover:bg-gray-300'"
                                />
                            </template>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal Crear -->
            <Modal :show="confirmingCorreoCrear" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Crear Movimiento
                    </h2>

                    <div class="mt-6">
                        <InputLabel for="tipo_crear" value="Tipo" />

                        <select
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            v-model="form.tipo"
                            id="tipo_crear"
                            required
                        >
                            <option value="">Seleccione un tipo</option>
                            <option value="Ingreso">Ingreso</option>
                            <option value="Egreso">Egreso</option>
                            <option value="Retiro Utilidades">Retiro Utilidades</option>
                        </select>

                        <InputError class="mt-2" :message="form.errors.tipo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="descripcion_crear" value="Descripción" />

                        <TextInput
                            id="descripcion_crear"
                            type="text"
                            class="mt-1 block w-full"
                            autocomplete="descripcion"
                            v-model="form.descripcion"
                        />

                        <InputError :message="form.errors.descripcion" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="valor_crear" value="Valor" />

                        <TextInput
                            id="valor_crear"
                            type="number"
                            class="mt-1 block w-full"
                            autocomplete="valor"
                            v-model="form.valor"
                        />

                        <InputError :message="form.errors.valor" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="observaciones_crear" value="Observaciones" />

                        <TextInput
                            id="observaciones_crear"
                            type="text"
                            class="mt-1 block w-full"
                            autocomplete="observaciones"
                            v-model="form.observaciones"
                        />

                        <InputError :message="form.errors.observaciones" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal">
                            {{ $t("Cancel") }}
                        </SecondaryButton>

                        <PrimaryButton
                            class="ms-3"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="crearMovimiento"
                        >
                            Crear
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>

            <!-- Modal Editar -->
            <Modal :show="confirmingCorreoEditar" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Editar Movimiento
                    </h2>

                    <div class="mt-6">
                        <InputLabel for="tipo_editar" value="Tipo" />

                        <select
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            v-model="form.tipo"
                            id="tipo_editar"
                            required
                        >
                            <option value="">Seleccione un tipo</option>
                            <option value="Ingreso">Ingreso</option>
                            <option value="Egreso">Egreso</option>
                            <option value="Retiro Utilidades">Retiro Utilidades</option>
                        </select>

                        <InputError class="mt-2" :message="form.errors.tipo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="descripcion_editar" value="Descripción" />

                        <TextInput
                            id="descripcion_editar"
                            type="text"
                            class="mt-1 block w-full"
                            autocomplete="descripcion"
                            v-model="form.descripcion"
                        />

                        <InputError :message="form.errors.descripcion" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="valor_editar" value="Valor" />

                        <TextInput
                            id="valor_editar"
                            type="number"
                            class="mt-1 block w-full"
                            autocomplete="valor"
                            v-model="form.valor"
                        />

                        <InputError :message="form.errors.valor" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="observaciones_editar" value="Observaciones" />

                        <TextInput
                            id="observaciones_editar"
                            type="text"
                            class="mt-1 block w-full"
                            autocomplete="observaciones"
                            v-model="form.observaciones"
                        />

                        <InputError :message="form.errors.observaciones" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal">
                            {{ $t("Cancel") }}
                        </SecondaryButton>

                        <PrimaryButton
                            class="ms-3"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="editarMovimiento"
                        >
                            Editar
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>