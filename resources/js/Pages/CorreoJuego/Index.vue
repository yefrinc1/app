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
import JuegoCatalogoInput from '@/Components/JuegoCatalogoInput.vue';

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
    primaria_ps4: '',
    primaria_ps5: '',
    secundaria: '',
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

const editarModal = (id, contrasena, disponible, fecha_nacimiento, juego, precio_usd, precio_cop, primaria_ps5, primaria_ps4, secundaria) => {
    form.id = id;
    form.contrasena = contrasena;
    form.disponible = disponible == 1 ? true : false;
    form.fecha_nacimiento = new Date(fecha_nacimiento).toISOString().split('T')[0];
    form.juego = juego;
    form.precio_usd = precio_usd;
    form.precio_cop = precio_cop;
    form.primaria_ps5 = primaria_ps5;
    form.primaria_ps4 = primaria_ps4;
    form.secundaria = secundaria;
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
    const params = new URLSearchParams(window.location.search);

    form
        .transform((data) => ({
            ...data,
            page: params.get('page') || 1,
            search: params.get('search') || '',
        }))
        .put(route("correo-juegos.update", form.id), {
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
                                + Nuevo Correo Juego
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
                                    <th class="px-4 py-2 text-left">Juego</th>
                                    <th class="px-4 py-2 text-left">Licencias</th>
                                    <th class="px-4 py-2 text-left">Precios</th>
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
                                                @click="editarModal(correo.id, correo.contrasena, correo.disponible, correo.fecha_nacimiento, correo.juego, correo.precio_usd, correo.precio_cop, correo.primaria_ps5, correo.primaria_ps4, correo.secundaria)"
                                            >
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
                                                <i class="fa-solid fa-link text-purple-500"></i>
                                                <span>Madre #{{ correo.id_correo_madre }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="bg-gray-50 rounded-lg p-2 min-w-[220px]">
                                            <div class="flex items-center gap-2 text-md">
                                                <i class="fa-solid fa-gamepad text-purple-500"></i>

                                                <span class="font-semibold text-gray-900">
                                                    {{ correo.juego }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            <span class="inline-flex items-center bg-gradient-to-r from-blue-100 to-blue-200 text-blue-900 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                                PS4: {{ correo.primaria_ps4 }}
                                            </span>

                                            <span class="inline-flex items-center bg-gradient-to-r from-indigo-100 to-indigo-200 text-indigo-900 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                                PS5: {{ correo.primaria_ps5 }}
                                            </span>

                                            <span class="inline-flex items-center bg-gradient-to-r from-green-100 to-green-200 text-green-900 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                                SEC: {{ correo.secundaria }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="inline-flex flex-col gap-1">
                                            <div class="bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-900 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                                USD {{ correo.precio_usd }}
                                            </div>

                                            <div class="bg-gradient-to-r from-green-100 to-green-200 text-green-900 px-3 py-1 rounded-lg text-xs font-bold shadow-sm">
                                                COP ${{ Number(correo.precio_cop).toLocaleString('es-CO') }}
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
                                    <td class="px-4 py-2 text-center" colspan="12">
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
                        <InputLabel for="juego_editar" value="Juego" />
                        <JuegoCatalogoInput
                            v-if="confirmingCorreoEditar"
                            :key="form.id"
                            v-model="form.juego"
                            input-id="juego_editar"
                            :error="form.errors.juego"
                        />
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

                        <div class="relative">
                            <textarea
                                id="codigo"
                                ref="codigo"
                                rows="4"
                                v-model="form.codigo"
                                placeholder="Ingresa el código..."
                                class="
                                    mt-1
                                    block
                                    w-full

                                    rounded-xl

                                    border
                                    border-red-500/30

                                    bg-gradient-to-r
                                    from-white
                                    via-red-50
                                    to-amber-50

                                    px-4
                                    py-3
                                    pr-10

                                    text-gray-800
                                    font-medium

                                    placeholder:text-gray-400

                                    shadow-md

                                    transition-all
                                    duration-300

                                    hover:border-red-500/50

                                    focus:outline-none
                                    focus:border-amber-500

                                    focus:ring-4
                                    focus:ring-red-500/20

                                    focus:shadow-lg
                                    focus:shadow-red-500/20

                                    resize-y
                                "
                            ></textarea>

                            <div
                                class="
                                    pointer-events-none
                                    absolute
                                    top-4
                                    right-3

                                    text-amber-500
                                "
                            >
                                <i class="fa-solid fa-key"></i>
                            </div>
                        </div>

                        <InputError :message="form.errors.codigo" class="mt-2" />
                    </div>

                    <div class="mt-6 flex space-x-6">
                        <div class="flex flex-col w-1/3">
                            <InputLabel for="primaria_ps4" value="Primaria PS4" />
                            <TextInput
                                id="primaria_ps4"
                                ref="primaria_ps4"
                                v-model="form.primaria_ps4"
                                type="number"
                                class="mt-1 block w-full"
                                autocomplete="primaria_ps4"
                            />
                            <InputError :message="form.errors.primaria_ps4" class="mt-2" />
                        </div>

                        <div class="flex flex-col w-1/3">
                            <InputLabel for="primaria_ps5" value="Primaria PS5" />
                            <TextInput
                                id="primaria_ps5"
                                ref="primaria_ps5"
                                v-model="form.primaria_ps5"
                                type="number"
                                class="mt-1 block w-full"
                                autocomplete="primaria_ps5"
                            />
                            <InputError :message="form.errors.primaria_ps5" class="mt-2" />
                        </div>

                        <div class="flex flex-col w-1/3">
                            <InputLabel for="secundaria" value="Secundaria" />
                            <TextInput
                                id="secundaria"
                                ref="secundaria"
                                v-model="form.secundaria"
                                type="number"
                                class="mt-1 block w-full"
                                autocomplete="secundaria"
                            />
                            <InputError :message="form.errors.secundaria" class="mt-2" />
                        </div>
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
