<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { useForm, Head } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import JuegoCatalogoInput from '@/Components/JuegoCatalogoInput.vue';
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
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🎮 Crear Juego Manual
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
                                    Registrar correo juego manual
                                </h2>

                                <p class="mt-1 text-sm text-gray-600">
                                    Crea una cuenta de juego manualmente con sus licencias, códigos y datos de acceso.
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-xs text-gray-500">
                                    Módulo
                                </p>

                                <p class="text-lg font-bold text-gray-900">
                                    Correo Juego
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Formulario -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <form @submit.prevent="submitForm" class="space-y-6">
                            <!-- Datos de acceso -->
                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-envelope text-blue-500"></i>
                                    Datos de acceso
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <InputLabel for="correo" value="Correo juego" />

                                        <TextInput
                                            id="correo"
                                            v-model="form.correo"
                                            type="text"
                                            class="mt-1 block w-full"
                                            autocomplete="off"
                                            required
                                            placeholder="correo@ejemplo.com"
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
                                            placeholder="Contraseña de la cuenta"
                                        />

                                        <InputError :message="form.errors.contrasena" class="mt-2" />
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
                                </div>
                            </div>

                            <!-- Juego -->
                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 overflow-visible">
                                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-gamepad text-purple-500"></i>
                                    Información del juego
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="relative z-30 overflow-visible">
                                        <InputLabel for="juego" value="Juego" />

                                        <JuegoCatalogoInput
                                            v-model="form.juego"
                                            input-id="juego_manual"
                                            :error="form.errors.juego"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Licencias -->
                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-key text-green-500"></i>
                                    Licencias disponibles
                                </h3>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
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

                                    <div>
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

                                    <div>
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

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
                                    <div class="bg-blue-100 text-blue-800 rounded-lg px-3 py-2 text-sm font-bold flex items-center justify-between">
                                        <span>PS4</span>
                                        <span>{{ form.primaria_ps4 }}</span>
                                    </div>

                                    <div class="bg-indigo-100 text-indigo-800 rounded-lg px-3 py-2 text-sm font-bold flex items-center justify-between">
                                        <span>PS5</span>
                                        <span>{{ form.primaria_ps5 }}</span>
                                    </div>

                                    <div class="bg-green-100 text-green-800 rounded-lg px-3 py-2 text-sm font-bold flex items-center justify-between">
                                        <span>Secundaria</span>
                                        <span>{{ form.secundaria }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Códigos -->
                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                <h3 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-shield-halved text-amber-500"></i>
                                    Códigos de verificación
                                </h3>

                                <div>
                                    <InputLabel for="codigo" value="Códigos" />

                                    <div class="relative">
                                        <textarea
                                            id="codigo"
                                            ref="codigo"
                                            rows="5"
                                            v-model="form.codigo"
                                            placeholder="Pega aquí los códigos de verificación, uno por línea..."
                                            class="
                                                mt-1
                                                block
                                                w-full

                                                rounded-xl

                                                border
                                                border-red-500/30

                                                bg-gradient-to-br
                                                from-white
                                                via-red-50
                                                to-amber-50

                                                px-4
                                                py-3
                                                pr-12

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
                                                right-4

                                                flex
                                                items-center
                                                justify-center

                                                h-8
                                                w-8

                                                rounded-lg

                                                bg-gradient-to-r
                                                from-red-500
                                                to-amber-500

                                                text-white

                                                shadow-md
                                            "
                                        >
                                            <i class="fa-solid fa-shield-halved"></i>
                                        </div>
                                    </div>

                                    <InputError :message="form.errors.codigo" class="mt-2" />

                                    <p class="text-xs text-gray-500 mt-2">
                                        Puedes guardar varios códigos separados por salto de línea.
                                    </p>
                                </div>
                            </div>

                            <!-- Acción -->
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                <PrimaryButton
                                    class="w-full sm:w-auto justify-center"
                                    :disabled="form.processing"
                                >
                                    <i class="fa-solid fa-floppy-disk mr-2"></i>
                                    {{ $t("Save") }}
                                </PrimaryButton>

                                <span
                                    v-if="form.processing"
                                    class="text-sm text-gray-500 font-semibold"
                                >
                                    Guardando correo juego...
                                </span>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </template>
    </LayoutPageHeader>
</template>