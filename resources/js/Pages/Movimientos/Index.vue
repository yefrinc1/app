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

// Confirmación antes de eliminar un correo
const eliminarCorreo = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas eliminar este movimiento?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("movimientos.destroy", id), {
                onSuccess: () => {
                    // Acción después de la eliminación exitosa
                    swalWithTailwind.fire({
                        title: 'Movimiento eliminado correctamente',
                        icon: 'success',
                    });
                },
                onError: () => {
                    // Acción si hay un error
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
  return new Intl.DateTimeFormat("es-CO", { day: "2-digit", month: "2-digit", year: "numeric" }).format(new Date(fecha));
};
</script>

<template>

    <Head title="Movimientos" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🔄 Movimientos</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="flex justify-end mb-4">
                        <PrimaryButton @click="crearModal()">
                            + Nuevo Correo
                        </PrimaryButton>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Tipo</th>
                                    <th class="px-4 py-2 text-left">Descripcion</th>
                                    <th class="px-4 py-2 text-left">Valor</th>
                                    <th class="px-4 py-2 text-left">Observaciones</th>
                                    <th class="px-4 py-2 text-left">Fecha</th>
                                    <th class="px-4 py-2 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="movimiento in props.movimientos.data" :key="movimiento.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ movimiento.tipo }}</td>
                                    <td class="px-4 py-2">{{ movimiento.descripcion }}</td>
                                    <td class="px-4 py-2">{{ movimiento.valor }}</td>
                                    <td class="px-4 py-2">{{ movimiento.observaciones }}</td>
                                    <td class="px-4 py-2">{{ formatearFecha(movimiento.created_at) }}</td>
                                    <td class="px-4 py-2 flex justify-center space-x-2">
                                        <SecondaryButton
                                            @click="editarModal(movimiento.id, movimiento.tipo, movimiento.descripcion, movimiento.valor, movimiento.observaciones)">
                                            <i class="fa-solid fa-user-pen"></i>
                                        </SecondaryButton>
                                        <DangerButton @click="eliminarCorreo(movimiento.id)">
                                            <i class="fa-solid fa-trash"></i>
                                        </DangerButton>
                                    </td>
                                </tr>
                                <tr v-if="props.movimientos.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="8">
                                        No hay movimientos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="mt-4">
                        <div class="flex justify-center space-x-2">
                            <template v-for="(link, index) in props.movimientos.links" :key="index">
                                <Link v-if="link.url" :href="link.url" v-html="link.label"
                                    class="px-3 py-1 border rounded-md"
                                    :class="link.active ? 'px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150'
                                        : 'px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 transition ease-in-out duration-150'" />
                            </template>
                        </div>
                    </div>

                </section>
            </div>

            <Modal :show="confirmingCorreoCrear" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Crear Movimiento
                    </h2>

                    <div class="mt-6">
                        <InputLabel for="tipo" value="Tipo" />

                        <select
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            v-model="form.tipo" 
                            id="tipo"
                            required
                        >
                            <option value="">Seleccione un tipo</option>
                            <option value="Ingreso">Ingreso</option>
                            <option value="Egreso">Egreso</option>
                        </select>
                    
                        <InputError class="mt-2" :message="form.errors.tipo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="descripcion" value="Descripcion" />

                        <TextInput id="descripcion" type="text"
                            class="mt-1 block w-full" autocomplete="descripcion" v-model="form.descripcion" />

                        <InputError :message="form.errors.descripcion" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="valor" value="Valor" />

                        <TextInput id="valor" type="number"
                            class="mt-1 block w-full" autocomplete="valor" v-model="form.valor"/>

                        <InputError :message="form.errors.valor" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="observaciones" value="Observaciones" />

                        <TextInput id="observaciones" type="text"
                            class="mt-1 block w-full" autocomplete="observaciones" v-model="form.observaciones"/>

                        <InputError :message="form.errors.observaciones" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal"> {{ $t("Cancel") }}
                        </SecondaryButton>

                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing" @click="crearMovimiento">
                            Crear
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>

            <Modal :show="confirmingCorreoEditar" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Editar movimiento
                    </h2>

                    <div class="mt-6">
                        <InputLabel for="tipo" value="Tipo" />

                        <select
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            v-model="form.tipo" 
                            id="tipo"
                            required
                        >
                            <option value="">Seleccione un tipo</option>
                            <option value="Ingreso">Ingreso</option>
                            <option value="Egreso">Egreso</option>
                        </select>
                    
                        <InputError class="mt-2" :message="form.errors.tipo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="descripcion" value="Descripcion" />

                        <TextInput id="descripcion" type="text"
                            class="mt-1 block w-full" autocomplete="descripcion" v-model="form.descripcion" />

                        <InputError :message="form.errors.descripcion" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="valor" value="Valor" />

                        <TextInput id="valor" type="number"
                            class="mt-1 block w-full" autocomplete="valor" v-model="form.valor"/>

                        <InputError :message="form.errors.valor" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="observaciones" value="Observaciones" />

                        <TextInput id="observaciones" type="text"
                            class="mt-1 block w-full" autocomplete="observaciones" v-model="form.observaciones"/>

                        <InputError :message="form.errors.observaciones" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal"> {{ $t("Cancel") }}
                        </SecondaryButton>

                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing" @click="editarMovimiento">
                            Editar
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>
