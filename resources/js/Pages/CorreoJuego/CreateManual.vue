<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { useForm, Head } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    correosMadres: Array,
    correosGlobales: Array,
});

const form = useForm({
    correo: '',
    contrasena: '',
    juego: '',
    fecha_nacimiento: '',
    codigo: '',
    primaria_ps4: 0,
    primaria_ps5: 0,
    secundaria: 0,
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

const submitForm = () => {
    form.post(route('correo-juegos.store-manual'), {
        onSuccess: () => {
            swalWithTailwind.fire({
                title: "Correo registrado correctamente",
                icon: "success",
            });
            form.reset();
        },
        onError: (errors) => {
            console.log("Errores en el formulario:", errors);
        },
    });
};

</script>

<template>
    <Head title="🎮 Crear Juego Manual" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🎮 Crear Juego Manual</h2>
        </template>

        <template #contenido-pagina>       
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Información correo juego</h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Datos para la creación de correo juego
                        </p>
                    </header>
                    <form @submit.prevent="submitForm" class="mt-6 space-y-6 w-full md:w-1/2">
                        <div>
                            <InputLabel for="correo" value="Correo juego" />

                            <TextInput
                                id="correo"
                                v-model="form.correo"
                                type="text"
                                class="mt-1 block w-full"
                                autocomplete="off"
                                required
                            />

                            <InputError :message="form.errors.correo" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="contrasena" :value="$t('Password')" />

                            <TextInput
                                id="contrasena"
                                ref="contrasena"
                                v-model="form.contrasena"
                                type="text"
                                class="mt-1 block w-full"
                                autocomplete="contrasena"
                                required
                            />

                            <InputError :message="form.errors.contrasena" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="juego" value="Juego" />

                            <TextInput
                                id="juego"
                                ref="juego"
                                v-model="form.juego"
                                type="text"
                                class="mt-1 block w-full"
                                autocomplete="juego"
                                required
                            />

                            <InputError :message="form.errors.juego" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="fecha_nacimiento" value="Fecha nacimiento" />

                            <TextInput
                                id="fecha_nacimiento"
                                ref="fecha_nacimiento"
                                v-model="form.fecha_nacimiento"
                                type="date"
                                class="mt-1 block w-full"
                                autocomplete="fecha_nacimiento"
                                min="1900-01-01" 
                                max="2100-12-31"
                                required
                            />

                            <InputError :message="form.errors.fecha_nacimiento" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4 w-full">
                            <div class="w-full">
                                <InputLabel for="primaria_ps4" value="Primaria PS4" />
                                <TextInput
                                    id="primaria_ps4"
                                    v-model="form.primaria_ps4"
                                    type="number"
                                    class="mt-1 w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.primaria_ps4" />
                            </div>

                            <div class="w-full">
                                <InputLabel for="primaria_ps5" value="Primaria PS5" />
                                <TextInput
                                    id="primaria_ps5"
                                    v-model="form.primaria_ps5"
                                    type="number"
                                    class="mt-1 w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.primaria_ps5" />
                            </div>

                            <div class="w-full">
                                <InputLabel for="secundaria" value="Secundaria" />
                                <TextInput
                                    id="secundaria"
                                    v-model="form.secundaria"
                                    type="number"
                                    class="mt-1 w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.secundaria" />
                            </div>
                        </div>

                        <div>
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

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing">{{ $t("Save") }}</PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>