<script setup>
import { ref } from 'vue';
import { Link, Head, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Checkbox from '@/Components/Checkbox.vue';

const confirmingCrear = ref(false);
const confirmingEditar = ref(false);

const props = defineProps({
    juegos: Object,
    search: String,
});

const searchQuery = ref(props.search || '');

const form = useForm({
    id: 0,
    nombre: '',
    url_imagen: '',
    consola_ps4: false,
    consola_ps5: false,
    descripcion: '',
    activo: true,
});

const buildConsola = () => {
    const consolas = [];
    if (form.consola_ps4) consolas.push('PS4');
    if (form.consola_ps5) consolas.push('PS5');
    return consolas.join(', ');
};

const parseConsolas = (consola) => {
    if (!consola) {
        return { ps4: false, ps5: false };
    }

    const valor = consola.toUpperCase();
    return {
        ps4: valor.includes('PS4'),
        ps5: valor.includes('PS5'),
    };
};

const payloadJuego = (data) => ({
    nombre: data.nombre,
    url_imagen: data.url_imagen,
    consola: buildConsola(),
    descripcion: data.descripcion,
    activo: data.activo,
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true,
});

const buscarJuegos = () => {
    form.get(route('juegos.index', { search: searchQuery.value }), {
        preserveScroll: true,
    });
};

const crearModal = () => {
    form.reset();
    form.activo = true;
    confirmingCrear.value = true;
};

const editarModal = (juego) => {
    const consolas = parseConsolas(juego.consola);
    form.id = juego.id;
    form.nombre = juego.nombre;
    form.url_imagen = juego.url_imagen ?? '';
    form.consola_ps4 = consolas.ps4;
    form.consola_ps5 = consolas.ps5;
    form.descripcion = juego.descripcion ?? '';
    form.activo = juego.activo;
    confirmingEditar.value = true;
};

const crearJuego = () => {
    form.transform(payloadJuego).post(route('juegos.store'), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: 'Juego creado correctamente',
                icon: 'success',
            });
            closeModal();
        },
    });
};

const editarJuego = () => {
    form.transform(payloadJuego).put(route('juegos.update', form.id), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: 'Juego actualizado correctamente',
                icon: 'success',
            });
            closeModal();
        },
    });
};

const closeModal = () => {
    confirmingCrear.value = false;
    confirmingEditar.value = false;
    form.reset();
};

const eliminarJuego = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas eliminar este juego?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('juegos.destroy', id), {
                onSuccess: () => {
                    swalWithTailwind.fire({
                        title: 'Juego eliminado correctamente',
                        icon: 'success',
                    });
                },
                onError: () => {
                    swalWithTailwind.fire({
                        title: 'Hubo un error al eliminar el juego',
                        icon: 'error',
                    });
                },
            });
        }
    });
};
</script>

<template>
    <Head title="Consultar Juegos" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🎮 Consultar Juegos</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="flex flex-col sm:flex-row justify-between gap-4 mb-4">
                        <div class="flex gap-2">
                            <TextInput
                                v-model="searchQuery"
                                placeholder="Buscar por nombre..."
                                class="w-full sm:w-64"
                                @keyup.enter="buscarJuegos"
                            />
                            <PrimaryButton @click="buscarJuegos">Buscar</PrimaryButton>
                        </div>
                        <PrimaryButton @click="crearModal">+ Nuevo Juego</PrimaryButton>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Imagen</th>
                                    <th class="px-4 py-2 text-left">Nombre</th>
                                    <th class="px-4 py-2 text-left">Consola</th>
                                    <th class="px-4 py-2 text-left">Descripción</th>
                                    <th class="px-4 py-2 text-left">Estado</th>
                                    <th class="px-4 py-2 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="juego in props.juegos.data"
                                    :key="juego.id"
                                    class="border-b hover:bg-gray-100"
                                >
                                    <td class="px-4 py-2">
                                        <img
                                            v-if="juego.url_imagen"
                                            :src="juego.url_imagen"
                                            :alt="juego.nombre"
                                            class="h-10 w-10 object-cover rounded"
                                        />
                                        <span v-else class="text-gray-400 text-sm">Sin imagen</span>
                                    </td>
                                    <td class="px-4 py-2">{{ juego.nombre }}</td>
                                    <td class="px-4 py-2">{{ juego.consola || '—' }}</td>
                                    <td class="px-4 py-2 max-w-xs truncate">{{ juego.descripcion || '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="juego.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                        >
                                            {{ juego.activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 flex justify-center space-x-2">
                                        <SecondaryButton @click="editarModal(juego)">
                                            <i class="fa-solid fa-pen"></i>
                                        </SecondaryButton>
                                        <DangerButton @click="eliminarJuego(juego.id)">
                                            <i class="fa-solid fa-trash"></i>
                                        </DangerButton>
                                    </td>
                                </tr>
                                <tr v-if="props.juegos.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="6">
                                        No hay juegos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <div class="flex justify-center space-x-2">
                            <template v-for="(link, index) in props.juegos.links" :key="index">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    v-html="link.label"
                                    class="px-3 py-1 border rounded-md"
                                    :class="link.active
                                        ? 'px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150'
                                        : 'px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 transition ease-in-out duration-150'"
                                />
                            </template>
                        </div>
                    </div>
                </section>
            </div>

            <Modal :show="confirmingCrear" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Crear Juego</h2>

                    <div class="mt-6">
                        <InputLabel for="nombre_crear" value="Nombre" />
                        <TextInput
                            id="nombre_crear"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.nombre"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.nombre" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="url_imagen_crear" value="URL Imagen" />
                        <TextInput
                            id="url_imagen_crear"
                            type="url"
                            class="mt-1 block w-full"
                            v-model="form.url_imagen"
                        />
                        <InputError class="mt-2" :message="form.errors.url_imagen" />
                    </div>

                    <div class="mt-6">
                        <InputLabel value="Consolas" />

                        <div class="flex gap-4 mt-3">
                            <label
                                class="
                                    flex
                                    items-center
                                    gap-3

                                    px-4
                                    py-3

                                    rounded-xl

                                    border
                                    border-red-100

                                    bg-gradient-to-r
                                    from-white
                                    to-red-50

                                    cursor-pointer

                                    transition-all
                                    duration-300

                                    hover:border-red-300
                                    hover:shadow-md
                                "
                            >
                                <Checkbox
                                    v-model:checked="form.consola_ps4"
                                />

                                <span class="font-semibold text-gray-700">
                                    🎮 PS4
                                </span>
                            </label>

                            <label
                                class="
                                    flex
                                    items-center
                                    gap-3

                                    px-4
                                    py-3

                                    rounded-xl

                                    border
                                    border-red-100

                                    bg-gradient-to-r
                                    from-white
                                    to-amber-50

                                    cursor-pointer

                                    transition-all
                                    duration-300

                                    hover:border-amber-300
                                    hover:shadow-md
                                "
                            >
                                <Checkbox
                                    v-model:checked="form.consola_ps5"
                                />

                                <span class="font-semibold text-gray-700">
                                    🚀 PS5
                                </span>
                            </label>
                        </div>
                        
                        <InputError class="mt-2" :message="form.errors.consola" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="descripcion_crear" value="Descripción" />
                        <textarea
                            id="descripcion_crear"
                            ref="descripcion_crear"
                            rows="4"
                            v-model="form.descripcion"
                            placeholder="Ingresa la descripción..."
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
                        <InputError class="mt-2" :message="form.errors.descripcion" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="activo_crear" value="Activo" />

                        <div class="flex mt-3">
                            <Checkbox
                                v-model:checked="form.activo"
                            />
                            <InputError class="mt-2" :message="form.errors.activo" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal">{{ $t('Cancel') }}</SecondaryButton>
                        <PrimaryButton
                            class="ms-3"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="crearJuego"
                        >
                            Crear
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>

            <Modal :show="confirmingEditar" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">Editar Juego</h2>

                    <div class="mt-6">
                        <InputLabel for="nombre_editar" value="Nombre" />
                        <TextInput
                            id="nombre_editar"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.nombre"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.nombre" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="url_imagen_editar" value="URL Imagen" />
                        <TextInput
                            id="url_imagen_editar"
                            type="url"
                            class="mt-1 block w-full"
                            v-model="form.url_imagen"
                        />
                        <InputError class="mt-2" :message="form.errors.url_imagen" />
                    </div>

                    <div class="mt-6">
                        <InputLabel value="Consolas" />

                        <div class="flex gap-4 mt-3">
                            <label
                                class="
                                    flex
                                    items-center
                                    gap-3

                                    px-4
                                    py-3

                                    rounded-xl

                                    border
                                    border-red-100

                                    bg-gradient-to-r
                                    from-white
                                    to-red-50

                                    cursor-pointer

                                    transition-all
                                    duration-300

                                    hover:border-red-300
                                    hover:shadow-md
                                "
                            >
                                <Checkbox
                                    v-model:checked="form.consola_ps4"
                                />

                                <span class="font-semibold text-gray-700">
                                    🎮 PS4
                                </span>
                            </label>

                            <label
                                class="
                                    flex
                                    items-center
                                    gap-3

                                    px-4
                                    py-3

                                    rounded-xl

                                    border
                                    border-red-100

                                    bg-gradient-to-r
                                    from-white
                                    to-amber-50

                                    cursor-pointer

                                    transition-all
                                    duration-300

                                    hover:border-amber-300
                                    hover:shadow-md
                                "
                            >
                                <Checkbox
                                    v-model:checked="form.consola_ps5"
                                />

                                <span class="font-semibold text-gray-700">
                                    🚀 PS5
                                </span>
                            </label>
                        </div>

                        <InputError
                            class="mt-2"
                            :message="form.errors.consola"
                        />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="descripcion_editar" value="Descripción" />

                        <textarea
                            id="descripcion_editar"
                            ref="descripcion_editar"
                            rows="4"
                            v-model="form.descripcion"
                            placeholder="Ingresa la descripción..."
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

                        <InputError class="mt-2" :message="form.errors.descripcion" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="activo_editar" value="Activo" />

                        <div class="flex mt-3">
                            <Checkbox
                                v-model:checked="form.activo"
                            />
                            <InputError class="mt-2" :message="form.errors.activo" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal">{{ $t('Cancel') }}</SecondaryButton>
                        <PrimaryButton
                            class="ms-3"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="editarJuego"
                        >
                            Guardar
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>
