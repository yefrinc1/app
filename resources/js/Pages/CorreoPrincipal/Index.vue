<script setup>
import { defineProps } from "vue";
import { Link, Head, useForm } from "@inertiajs/vue3";
import Modal from '@/Components/Modal.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import { nextTick, ref } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const confirmingCorreoCrear = ref(false);
const correoInput = ref(null);

const searchQuery = ref('');  // Añadimos la referencia para la búsqueda

const props = defineProps({
    correos: Object,
    search: String,
});

const form = useForm({ 
    id: 0,
    correo: '', 
    contrasena: '', 
    disponible: '',
});

searchQuery.value = props.search;

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

const abrirModalCrear = () => {
    form.reset();
    confirmingCorreoCrear.value = true;
    nextTick(() => correoInput.value.focus());
};

// Función para realizar la búsqueda
const buscarCorreo = () => {
    form.get(route('correo-principal.index', { search: searchQuery.value }), {
        preserveScroll: true,
    });
};

const crearCorreo = () => {
    form.post(route("correo-principal.store"), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: "Correo creado correctamente",
                icon: "success",
            });
            closeModal();
        },
    });
};

const closeModal = () => {
    confirmingCorreoCrear.value = false;
    form.reset();
};

// Confirmación antes de eliminar un correo
const eliminarCorreo = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas eliminar este correo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("correo-principal.destroy", id), {
                onSuccess: () => {
                    // Acción después de la eliminación exitosa
                    swalWithTailwind.fire({
                        title: 'Correo eliminado correctamente',
                        icon: 'success',
                    });
                },
                onError: () => {
                    // Acción si hay un error
                    swalWithTailwind.fire({
                        title: 'Hubo un error al eliminar el correo',
                        icon: 'error',
                    });
                },
            });
        }
    });
};

</script>

<template>

    <Head title="Correos Principales" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">📩 Correos Principales</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <!-- Filtro de búsqueda -->
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex space-x-4 w-3/6">
                            <TextInput v-model="searchQuery" type="text" placeholder="Buscar correo..." class="block w-full" />
                            <PrimaryButton @click="buscarCorreo">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </PrimaryButton>
                        </div>
                        <PrimaryButton @click="abrirModalCrear">
                            + Nuevo Correo
                        </PrimaryButton>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="sticky right-0 bg-gray-100 px-4 py-2 z-10">
                                        Acciones
                                    </th>
                                    <th class="px-4 py-2 text-left">Cuenta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="correo in props.correos.data" :key="correo.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="sticky right-0 bg-white px-4 py-2 z-10">
                                        <div class="flex flex-col items-center gap-2">
                                            <DangerButton class="w-8 h-8 flex items-center justify-center"
                                                @click="eliminarCorreo(correo.id)">
                                                <i class="fa-solid fa-trash"></i>
                                            </DangerButton>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <div class="bg-gray-50 rounded-lg p-2 min-w-[220px]">
                                            <div class="flex items-center gap-2 text-md">
                                                <i class="fa-solid fa-envelope text-blue-500"></i>
                                                <span class="truncate">{{ correo.correo }}</span>
                                            </div>

                                            <div class="flex items-center gap-2 text-md text-gray-600 mt-1">
                                                <i class="fa-solid fa-key text-amber-500"></i>
                                                <span>{{ correo.contrasena }}</span>
                                            </div>

                                            <div class="flex items-center gap-2 text-sm text-purple-700 mt-1">
                                                <i class="fa-solid fa-id-card text-purple-500"></i>
                                                <span>Principal #{{ correo.id }}</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="props.correos.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="2">
                                        No hay correos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="mt-4">
                        <div class="flex flex-wrap justify-center gap-2">
                            <template v-for="(link, index) in props.correos.links" :key="index">
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

            <Modal :show="confirmingCorreoCrear" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Crear nuevo correo principal</h2>

                    <div class="mt-6">
                        <InputLabel for="correo" value="Correo" />
                        <TextInput id="correo" ref="correoInput" v-model="form.correo" type="text" class="mt-1 block w-full" autocomplete="correo" />
                        <InputError :message="form.errors.correo" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="contrasena" value="Contraseña" />
                        <TextInput id="contrasena" v-model="form.contrasena" type="text" class="mt-1 block w-full"
                            autocomplete="contrasena" />
                        <InputError :message="form.errors.contrasena" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal">Cancelar</SecondaryButton>

                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing"
                            @click="crearCorreo">
                            Crear
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>

        </template>
    </LayoutPageHeader>
</template>
