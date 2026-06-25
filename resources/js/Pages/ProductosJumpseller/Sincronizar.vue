<script setup>
import { defineProps, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    juegosJumpseller: Array,
    juegos: Array,
    mensaje: String,
    tipo: String,
});

const form = useForm({
    juego: '',
    juego_jumpseller: '',
});

const confirmingSincronizarJuego = ref(false);

const sincronizarModal = (juego) => {
    confirmingSincronizarJuego.value = true;
    form.juego = juego;
};

const closeModal = () => {
    confirmingSincronizarJuego.value = false;
    form.reset();
};

const crearSincronizar = () => {
    Swal.fire({
        title: 'Sincronizando...',
        text: 'Por favor espera',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    form.patch(route("productos-sincronizar.update"), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.close();

            Swal.fire({
                icon: props.tipo,
                title: props.mensaje,
                timer: 2000,
                showConfirmButton: false
            });

            closeModal();
        },
        onError: () => {
            Swal.close();

            Swal.fire({
                title: 'Error',
                text: 'Ocurrió un error al sincronizar',
                icon: 'error',
            });
        }
    });
};
</script>

<template>
    <Head title="🎮 Juegos Sincronizar" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🎮 Juegos Sincronizar
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
                                    Juegos pendientes por sincronizar
                                </h2>

                                <p class="text-sm text-gray-500 mt-1">
                                    Relaciona los juegos internos con el producto correspondiente de Jumpseller.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                    <p class="text-xs text-gray-500">
                                        Pendientes
                                    </p>

                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ juegos.length }}
                                    </p>
                                </div>

                                <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
                                    <p class="text-xs text-blue-700">
                                        Jumpseller
                                    </p>

                                    <p class="text-2xl font-bold text-blue-800">
                                        {{ juegosJumpseller.length }}
                                    </p>
                                </div>
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
                                    Detalle de sincronización
                                </h3>

                                <p class="text-sm text-gray-500">
                                    Selecciona cada juego y vincúlalo con el producto correcto.
                                </p>
                            </div>

                            <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-bold w-fit">
                                {{ juegos.length }} sin sincronizar
                            </span>
                        </div>

                        <div class="overflow-x-auto shadow rounded-lg">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100 border-b">
                                        <th class="sticky right-0 bg-gray-100 px-4 py-2 z-10 text-center">
                                            Acción
                                        </th>
                                        <th class="px-4 py-2 text-left">#</th>
                                        <th class="px-4 py-2 text-left">Juego</th>
                                        <th class="px-4 py-2 text-left">Estado</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="(juego, i) in juegos"
                                        :key="juego.id"
                                        class="border-b hover:bg-gray-100"
                                    >
                                        <!-- Acción -->
                                        <td class="sticky right-0 bg-white px-4 py-2 z-10">
                                            <div class="flex justify-center">
                                                <PrimaryButton
                                                    class="w-10 h-10 flex items-center justify-center !px-0 !py-0"
                                                    @click="sincronizarModal(juego.nombre)"
                                                    title="Sincronizar juego"
                                                >
                                                    <i class="fas fa-spell-check"></i>
                                                </PrimaryButton>
                                            </div>
                                        </td>

                                        <!-- Número -->
                                        <td class="px-4 py-2">
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-xs font-bold">
                                                #{{ i + 1 }}
                                            </span>
                                        </td>

                                        <!-- Juego -->
                                        <td class="px-4 py-2">
                                            <div class="bg-gray-50 rounded-lg p-2 min-w-[280px]">
                                                <div class="flex items-center gap-2">
                                                    <i class="fa-solid fa-gamepad text-blue-500"></i>

                                                    <span class="font-bold text-gray-900 break-words">
                                                        {{ juego.nombre }}
                                                    </span>
                                                </div>

                                                <div class="flex items-center gap-2 text-xs text-gray-500 mt-1">
                                                    <i class="fa-solid fa-id-badge text-gray-400"></i>
                                                    <span>ID: {{ juego.id }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Estado -->
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-red-100 text-red-800 px-3 py-1 rounded-full text-xs font-bold">
                                                <i class="fa-solid fa-triangle-exclamation"></i>
                                                {{ juego.estado }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="juegos.length === 0">
                                        <td class="px-4 py-10 text-center" colspan="4">
                                            <div class="flex flex-col items-center gap-3">
                                                <div class="w-16 h-16 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                                    <i class="fa-solid fa-circle-check text-3xl"></i>
                                                </div>

                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">
                                                        Todo está sincronizado
                                                    </h3>

                                                    <p class="text-sm text-gray-500">
                                                        Todos los productos están sincronizados con Jumpseller.
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

            <!-- Modal sincronizar -->
            <Modal :show="confirmingSincronizarJuego" @close="closeModal">
                <div class="p-6">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-link text-xl"></i>
                        </div>

                        <div>
                            <h2 class="text-lg font-bold text-gray-900">
                                Sincronizar juego
                            </h2>

                            <p class="text-sm text-gray-500 mt-1">
                                Selecciona el producto de Jumpseller que corresponde a este juego.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <InputLabel for="juego" value="Juego interno" />

                        <TextInput
                            id="juego"
                            type="text"
                            class="mt-1 block w-full bg-gray-100"
                            autocomplete="juego"
                            v-model="form.juego"
                            disabled
                        />

                        <InputError :message="form.errors.juego" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="juego_jumpseller" value="Producto en Jumpseller" />

                        <select
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            v-model="form.juego_jumpseller"
                            id="juego_jumpseller"
                            required
                        >
                            <option value="">Selecciona un juego</option>

                            <option
                                v-for="juego in props.juegosJumpseller"
                                :key="juego"
                                :value="juego"
                            >
                                {{ juego }}
                            </option>
                        </select>

                        <InputError class="mt-2" :message="form.errors.juego_jumpseller" />
                    </div>

                    <div
                        v-if="form.juego_jumpseller"
                        class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4"
                    >
                        <p class="text-sm text-green-800 font-semibold">
                            <i class="fa-solid fa-circle-check mr-1"></i>
                            Se sincronizará:
                        </p>

                        <p class="text-sm text-gray-700 mt-2">
                            <strong>{{ form.juego }}</strong>
                            con
                            <strong>{{ form.juego_jumpseller }}</strong>
                        </p>
                    </div>

                    <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3">
                        <SecondaryButton
                            @click="closeModal"
                            class="w-full sm:w-auto justify-center"
                        >
                            {{ $t("Cancel") }}
                        </SecondaryButton>

                        <PrimaryButton
                            class="w-full sm:w-auto justify-center"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="crearSincronizar"
                        >
                            <i class="fa-solid fa-link mr-2"></i>
                            Sincronizar
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>