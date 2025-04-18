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
    icon_mensaje: String,
});

const form = useForm({
    id: 0,
    correo: '', 
    contrasena: '', 
    disponible: '',
    fecha_nacimiento: '',
    juego: '',
    precio_usd: '',
    codigo: '',
});

searchQuery.value = props.search;

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

if (props.mensaje_correo_creado != '') {
    swalWithTailwind.fire({
        title: props.mensaje_correo_creado,
        icon: props.icon_mensaje,
    });
}

const editarModal = (id, contrasena, disponible, fecha_nacimiento, juego, precio_usd, precio_cop) => {
    form.id = id;
    form.contrasena = contrasena;
    form.disponible = disponible == 1 ? true : false;
    form.fecha_nacimiento = new Date(fecha_nacimiento).toISOString().split('T')[0];
    form.juego = juego;
    form.precio_usd = precio_usd;
    form.precio_cop = precio_cop;
    confirmingCorreoEditar.value = true;
    nextTick(() => correoInput.value.focus());
};

// Función para realizar la búsqueda
const buscarCorreo = () => {
    form.get(route('correo-juegos.index', { search: searchQuery.value }), {
        preserveScroll: true,
    });
};

const editarCorreo = () => {
    form.put(route("correo-juegos.update", form.id), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: props.mensaje_correo_creado,
                icon: props.icon_mensaje,
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
        title: '¿Seguro que deseas eliminar este correo juego?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("correo-juegos.destroy", id), {
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
</script>

<template>

    <Head title="Correos Juegos" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🎮 Correos Juegos</h2>
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
                        <Link :href="route('correo-juegos.create')">
                            <PrimaryButton>
                                + Nuevo Correo
                            </PrimaryButton>
                        </Link>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Correo</th>
                                    <th class="px-4 py-2 text-left">Contraseña</th>
                                    <th class="px-4 py-2 text-left">Madre</th>
                                    <th class="px-4 py-2 text-left">Juego</th>
                                    <th class="px-4 py-2 text-left">Primaria PS4</th>
                                    <th class="px-4 py-2 text-left">Primaria PS5</th>
                                    <th class="px-4 py-2 text-left">Secundaria</th>
                                    <th class="px-4 py-2 text-left">Precio USD</th>
                                    <th class="px-4 py-2 text-left">Precio COP</th>
                                    <th class="px-4 py-2 text-left">Nacimiento</th>
                                    <th class="px-4 py-2 text-left">Disponible</th>
                                    <th class="px-4 py-2 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="correo in props.correos.data" :key="correo.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ correo.correo }}</td>
                                    <td class="px-4 py-2">{{ correo.contrasena }}</td>
                                    <td class="px-4 py-2">{{ correo.id_correo_madre }}</td>
                                    <td class="px-4 py-2">{{ correo.juego }}</td>
                                    <td class="px-4 py-2">{{ correo.primaria_ps4 }}</td>
                                    <td class="px-4 py-2">{{ correo.primaria_ps5 }}</td>
                                    <td class="px-4 py-2">{{ correo.secundaria }}</td>
                                    <td class="px-4 py-2">{{ correo.precio_usd }}</td>
                                    <td class="px-4 py-2">{{ correo.precio_cop }}</td>
                                    <td class="px-4 py-2">{{ formatearFecha(correo.fecha_nacimiento) }}</td>
                                    <td class="px-4 py-2">{{ correo.disponible }}</td>
                                    <td class="px-4 py-2 flex justify-center space-x-2">
                                        <SecondaryButton
                                            @click="editarModal(correo.id, correo.contrasena, correo.disponible, correo.fecha_nacimiento, correo.juego, correo.precio_usd, correo.precio_cop)">
                                            <i class="fa-solid fa-user-pen"></i>
                                        </SecondaryButton>
                                        <DangerButton @click="eliminarCorreo(correo.id)">
                                            <i class="fa-solid fa-trash"></i>
                                        </DangerButton>
                                    </td>
                                </tr>
                                <tr v-if="props.correos.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="12">
                                        No hay correos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div class="mt-4">
                        <div class="flex justify-center space-x-2">
                            <template v-for="(link, index) in props.correos.links" :key="index">
                                <Link v-if="link.url" :href="link.url" v-html="link.label"
                                    class="px-3 py-1 border rounded-md"
                                    :class="link.active ? 'px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150'
                                        : 'px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 transition ease-in-out duration-150'" />
                            </template>
                        </div>
                    </div>

                </section>
            </div>

            <Modal :show="confirmingCorreoEditar" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Editar correo juego
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

                    <div class="mt-6">
                        <InputLabel for="juego" value="Juego" />

                        <TextInput id="juego" ref="juego" v-model="form.juego" type="text"
                            class="mt-1 block w-full" autocomplete="juego"/>

                        <InputError :message="form.errors.juego" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="precio_usd" value="Precio USD" />

                        <TextInput id="precio_usd" ref="precio_usd" v-model="form.precio_usd" type="number" 
                            step="0.01"
                            inputmode="decimal"
                            class="mt-1 block w-full" 
                            autocomplete="precio_usd"/>

                        <InputError :message="form.errors.precio_usd" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="codigo" value="Codigos" />

                        <textarea
                            id="codigo"
                            ref="codigo"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            rows="4"
                            v-model="form.codigo"
                        ></textarea>
                        <InputError :message="form.errors.codigo" class="mt-2" />
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
