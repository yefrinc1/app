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
import Checkbox from '@/Components/Checkbox.vue';

const confirmingCorreoEditar = ref(false);
const correoInput = ref(null);
const searchQuery = ref('');  // Añadimos la referencia para la búsqueda

const props = defineProps({
    correos: Object,
    search: String,
    mensaje_correo_creado: String,
});

const form = useForm({ 
    id: 0,
    correo: '', 
    contrasena: '', 
    disponible: '',
    fecha_nacimiento: '',
    saldo_usd: '',
    saldo_cop: '',
});

searchQuery.value = props.search;

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

if (props.mensaje_correo_creado != '') {
    swalWithTailwind.fire({
        title: props.mensaje_correo_creado,
        icon: "success",
    });
}

const editarModal = (id, contrasena, disponible, fecha_nacimiento, saldo_usd, saldo_cop) => {
    form.id = id;
    form.contrasena = contrasena;
    form.disponible = disponible == 1 ? true : false;
    form.fecha_nacimiento = new Date(fecha_nacimiento).toISOString().split('T')[0];
    form.saldo_usd = saldo_usd;
    form.saldo_cop = saldo_cop;
    confirmingCorreoEditar.value = true;
    nextTick(() => correoInput.value.focus());
};

// Función para realizar la búsqueda
const buscarCorreo = () => {
    form.get(route('correo-madre.index', { search: searchQuery.value }), {
        preserveScroll: true,
    });
};

const editarCorreo = () => {
    form.put(route("correo-madre.update", form.id), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: "Correo madre actualizado correctamente",
                icon: "success",
            });
            closeModal();
        },
    });
};

const closeModal = () => {
    confirmingCorreoEditar.value = false;
    form.reset();
};

// Confirmación antes de eliminar un correo
const eliminarCorreo = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas eliminar este correo madre?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("correo-madre.destroy", id), {
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

const formatearFecha = (fecha) => {
  if (!fecha) return "";
  return new Intl.DateTimeFormat("es-CO", { day: "2-digit", month: "2-digit", year: "numeric" }).format(new Date(fecha));
};

const formatearSaldo = (valor) => {
    if (valor === null || valor === undefined || isNaN(valor)) return "0.00";
    return Number(valor).toFixed(2);
};

const sumarSaldo = (tipo) => {
    if (tipo === 'usd') {
        // Convierte ambos valores a número y suma
        form.saldo_usd = (
            parseFloat(form.saldo_usd) || 0
        ) + (
            parseFloat(prompt('¿Cuánto quieres sumar al saldo USD?')) || 0
        );

        form.saldo_usd = formatearSaldo(form.saldo_usd);
    } else if (tipo === 'cop') {
        form.saldo_cop = (
            parseFloat(form.saldo_cop) || 0
        ) + (
            parseFloat(prompt('¿Cuánto quieres sumar al saldo COP?')) || 0
        );
    }
};
</script>

<template>

    <Head title="Correos Madres" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">👤 Correos Madres</h2>
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
                        <Link :href="route('correo-madre.create')">
                            <PrimaryButton>
                                + Nuevo Correo Madre
                            </PrimaryButton>
                        </Link>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="sticky right-0 bg-gray-100 px-4 py-2 z-10">
                                        Acciones
                                    </th>
                                    <th class="px-4 py-2 text-left">Cuenta</th>
                                    <th class="px-4 py-2 text-left">Hijos</th>
                                    <th class="px-4 py-2 text-left">Saldos</th>
                                    <th class="px-4 py-2 text-left">Nacimiento</th>
                                    <th class="px-4 py-2 text-left">Disponible</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="correo in props.correos.data" :key="correo.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="sticky right-0 bg-white px-4 py-2 z-10">
                                        <div class="flex flex-col items-center gap-2">
                                            <SecondaryButton class="w-8 h-8 flex items-center justify-center"
                                                @click="editarModal(correo.id, correo.contrasena, correo.disponible, correo.fecha_nacimiento, correo.saldo_usd, correo.saldo_cop)">
                                                <i class="fa-solid fa-user-pen"></i>
                                            </SecondaryButton>

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
                                                <span> #{{ correo.id }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <Link :href="route('correo-madre.show', correo.id)">
                                            <span class="inline-flex items-center gap-1 bg-gradient-to-r from-red-100 to-red-200 text-red-900 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                                <i class="fa-solid fa-gamepad"></i>
                                                {{ correo.correos_juegos_count }}
                                            </span>
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="inline-flex flex-col gap-1">
                                            <div class="bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-900 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                                USD {{ formatearSaldo(correo.saldo_usd) }}
                                            </div>

                                            <div class="bg-gradient-to-r from-green-100 to-green-200 text-green-900 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                                COP ${{ Number(correo.saldo_cop).toLocaleString('es-CO') }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">{{ formatearFecha(correo.fecha_nacimiento) }}</td>
                                    <td class="px-4 py-2">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold',
                                                correo.disponible == 1
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-red-100 text-red-800'
                                            ]"
                                        >
                                            <i
                                                :class="correo.disponible == 1
                                                    ? 'fa-solid fa-circle-check'
                                                    : 'fa-solid fa-circle-xmark'"
                                            ></i>

                                            {{ correo.disponible == 1 ? 'Disponible' : 'No disponible' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="props.correos.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="6">
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

            <Modal :show="confirmingCorreoEditar" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Editar correo madre
                    </h2>

                    <div class="mt-6">
                        <InputLabel for="contrasena" value="Contraseña" />

                        <TextInput id="contrasena" v-model="form.contrasena" type="text" class="mt-1 block w-full"
                            autocomplete="contrasena" />

                        <InputError :message="form.errors.contrasena" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="fecha_nacimiento" value="Fecha Nacimiento" />

                        <TextInput id="fecha_nacimiento" ref="fecha_nacimiento" v-model="form.fecha_nacimiento" type="date"
                            class="mt-1 block w-full" autocomplete="fecha_nacimiento"  min="1900-01-01" max="2100-12-31"/>

                        <InputError :message="form.errors.fecha_nacimiento" class="mt-2" />
                    </div>

                    <div class="mt-6 flex items-end gap-2">
                        <div class="w-full">
                            <InputLabel for="saldo_usd" value="Saldo USD" />
                            <TextInput id="saldo_usd" ref="saldo_usd" v-model="form.saldo_usd" type="text"
                                class="mt-1 block w-full" autocomplete="saldo_usd"/>
                            <InputError :message="form.errors.saldo_usd" class="mt-2" />
                        </div>
                        <PrimaryButton @click="sumarSaldo('usd')" type="button" class="mb-1">
                            <i class="fas fa-plus"></i>
                        </PrimaryButton>
                    </div>

                    <div class="mt-6 flex items-end gap-2">
                        <div class="w-full">
                            <InputLabel for="saldo_cop" value="Saldo COP" />
                            <TextInput id="saldo_cop" ref="saldo_cop" v-model="form.saldo_cop" type="text"
                                class="mt-1 block w-full" autocomplete="saldo_cop"/>
                            <InputError :message="form.errors.saldo_cop" class="mt-2" />
                        </div>
                        <PrimaryButton @click="sumarSaldo('cop')" type="button">
                            <i class="fas fa-plus"></i>
                        </PrimaryButton>
                    </div>

                    <div class="block mt-6">
                        <label class="flex items-center">
                            <Checkbox name="disponible" v-model:checked="form.disponible" />
                            <span class="ms-2 text-sm text-gray-600">Correo disponible</span>
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal"> {{ $t("Cancel") }}
                        </SecondaryButton>

                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing" @click="editarCorreo">
                            Editar
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>
