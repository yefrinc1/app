<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, Head } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { ref, onMounted } from 'vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import JuegoCatalogoInput from '@/Components/JuegoCatalogoInput.vue';

const props = defineProps({
    correosMadres: Array,
    correosGlobales: Array,
});

const form = useForm({
    id_correo_globales: '',
    id_correo_madre: '',
    contrasena: '',
    juego: '',
    precio_usd: '',
    fecha_nacimiento: '',
    codigo: '',
});

const selectedOptionMadre = ref("");
const selectedOption = ref("");

onMounted(() => {
    if (props.correosMadres.length > 0) {
        selectedOptionMadre.value = props.correosMadres[0].id;
        form.id_correo_madre = selectedOptionMadre;
    }

    if (props.correosGlobales.length > 0) {
        selectedOption.value = props.correosGlobales[0].id;
        form.id_correo_globales = selectedOption;
    }
});

</script>

<template>
    <Head title="Crear Correo Juego" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🎮 Crear Correo Juego</h2>
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
                    <form @submit.prevent="form.post(route('correo-juegos.store'))" class="mt-6 space-y-6 w-full md:w-1/2">
                        <div>
                            <InputLabel for="id_correo_madre" value="Correo madre" />

                            <div class="relative">
                                <select
                                    id="id_correo_madre"
                                    v-model="form.id_correo_madre"
                                    required
                                    class="
                                        appearance-none

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
                                        py-2.5
                                        pr-10

                                        text-gray-800
                                        font-semibold

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
                                    "
                                >
                                    <option value="">
                                        👤 Selecciona un correo madre
                                    </option>

                                    <option
                                        v-for="opcion in correosMadres"
                                        :key="opcion.id"
                                        :value="opcion.id"
                                    >
                                        {{ opcion.correo }} | 💰 {{ opcion.saldo_usd }} USD
                                    </option>
                                </select>

                                <div
                                    class="
                                        pointer-events-none
                                        absolute
                                        inset-y-0
                                        right-3

                                        flex
                                        items-center

                                        text-amber-500
                                    "
                                >
                                    <i class="fa-solid fa-user-shield"></i>
                                </div>
                            </div>
                        
                            <InputError class="mt-2" :message="form.errors.id_correo_madre" />
                        </div>

                        <div>
                            <InputLabel for="id_correo_globales" value="Correo juego" />

                            <div class="relative">
                                <select
                                    id="id_correo_globales"
                                    v-model="form.id_correo_globales"
                                    required
                                    class="
                                        appearance-none

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
                                        py-2.5
                                        pr-10

                                        text-gray-800
                                        font-semibold

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
                                    "
                                >
                                    <option value="">
                                        🌐 Selecciona un correo global
                                    </option>

                                    <option
                                        v-for="opcion in correosGlobales"
                                        :key="opcion.id"
                                        :value="opcion.id"
                                    >
                                        {{ opcion.correo }}
                                    </option>
                                </select>

                                <div
                                    class="
                                        pointer-events-none
                                        absolute
                                        inset-y-0
                                        right-3

                                        flex
                                        items-center

                                        text-amber-500
                                    "
                                >
                                    <i class="fa-solid fa-globe"></i>
                                </div>
                            </div>
                        
                            <InputError class="mt-2" :message="form.errors.id_correo_globales" />
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
                            <JuegoCatalogoInput
                                v-model="form.juego"
                                input-id="juego"
                                :error="form.errors.juego"
                            />
                        </div>

                        <div>
                            <InputLabel for="precio_usd" value="Precio USD" />

                            <TextInput
                                id="precio_usd"
                                ref="precio_usd"
                                v-model="form.precio_usd"
                                type="number"
                                step="0.01"
                                inputmode="decimal"
                                class="mt-1 block w-full"
                                autocomplete="precio_usd"
                            />

                            <InputError :message="form.errors.precio_usd" class="mt-2" />
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

                        <div>
                            <InputLabel for="codigo" value="Codigos" />

                            <div class="relative">
                                <textarea
                                    id="codigo"
                                    ref="codigo"
                                    rows="4"
                                    v-model="form.codigo"
                                    placeholder="Ingresa o pega aquí el código..."
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

                        <div class="flex items-center gap-4">
                            <Link :href="route('correo-juegos.index')">
                                <SecondaryButton>Volver</SecondaryButton>
                            </Link>
                            <PrimaryButton :disabled="form.processing">{{ $t("Save") }}</PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>