<script setup>
import { defineProps, ref, watch } from "vue";
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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🎮 Juegos Sincronizar ({{ juegos.length }})</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-center">#</th>
                                    <th class="px-4 py-2 text-center">Juego</th>
                                    <th class="px-4 py-2 text-center">Estado</th>
                                    <th class="px-4 py-2 text-center">Sincronizar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(juego, i) in juegos" :key="juego.id" class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2 text-center">{{ i + 1 }}</td>
                                    <td class="px-4 py-2">{{ juego.nombre }}</td>
                                    <td class="px-4 py-2 text-red-600 font-bold">
                                        {{ juego.estado }}
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <PrimaryButton @click="sincronizarModal(juego.nombre)"
                                        >
                                            <i class="fas fa-spell-check"></i>
                                        </PrimaryButton>
                                    </td>
                                </tr>
                                <tr v-if="juegos.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="12">
                                        Todos los productos sincronizados con Jumpseller
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Modal :show="confirmingSincronizarJuego" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Sincronizar Juego
                    </h2>

                    <div class="mt-6">
                        <InputLabel for="juego" value="Juego" />

                        <TextInput id="juego" type="text"
                            class="mt-1 block w-full" autocomplete="juego" v-model="form.juego" disabled/>

                        <InputError :message="form.errors.juego" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="juego_jumpseller" value="Jumpseller" />

                        <select
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            v-model="form.juego_jumpseller"
                            id="juego_jumpseller"
                            required
                        >
                            <option value="">Selecciona un juego</option>
                            <option v-for="juego in props.juegosJumpseller" :key="juego" :value="juego">
                                {{ juego }}
                            </option>
                        </select>
                    
                        <InputError class="mt-2" :message="form.errors.juego_jumpseller" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal"> {{ $t("Cancel") }}
                        </SecondaryButton>

                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing" @click="crearSincronizar">
                            Sincronizar
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>