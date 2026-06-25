<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, Head } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { ref, onMounted,computed } from 'vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    correosGlobales: Array,
    idUltimaMadre: Number,
});

const form = useForm({
    id_correo_globales: '',
    contrasena: props.idUltimaMadre ? `madre${props.idUltimaMadre + 1}oficial` : 'madreoficial1',
    saldo_usd: 0,
    saldo_cop: 0,
    fecha_nacimiento: '2001-01-01',
    codigo: '',
});
const selectedOption = ref("");

onMounted(() => {
    if (props.correosGlobales.length > 0) {
        selectedOption.value = props.correosGlobales[0].id;
        form.id_correo_globales = selectedOption;
    }
});

const valorPorUsd = computed(() => {
    if (form.saldo_cop && form.saldo_usd && form.saldo_usd !== 0) {
        return (form.saldo_cop / form.saldo_usd).toFixed(2);
    }
});

</script>

<template>
    <Head title="Crear Correo Madre" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">👤 Crear Correo Madre</h2>
        </template>

        <template #contenido-pagina>       
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Información correo madre</h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Datos para la creación de correo madre
                        </p>
                    </header>
                    <form @submit.prevent="form.post(route('correo-madre.store'))" class="mt-6 space-y-6 w-full md:w-1/2">
                        <div>
                            <InputLabel for="id_correo_globales" :value="$t('Email')" />

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
                                    <option value="">📧 Selecciona un correo global</option>

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
                                    <i class="fa-solid fa-envelope"></i>
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
                            <InputLabel for="saldo_usd" value="Saldo USD" />

                            <TextInput
                                id="saldo_usd"
                                ref="saldo_usd"
                                v-model="form.saldo_usd"
                                step="0.01"
                                type="number"
                                class="mt-1 block w-full"
                                autocomplete="saldo_usd"
                                required
                            />

                            <InputError :message="form.errors.saldo_usd" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="saldo_cop" value="Saldo COP" />

                            <div class="flex items-center space-x-2">
                                <TextInput
                                    id="saldo_cop"
                                    ref="saldo_cop"
                                    v-model="form.saldo_cop"
                                    type="number"
                                    class="mt-1 block w-full"
                                    autocomplete="saldo_cop"
                                    required
                                />
                                <span v-if="valorPorUsd" class="text-gray-600">{{ valorPorUsd }} ≈ 1 USD</span>
                            </div>

                            <InputError :message="form.errors.saldo_cop" class="mt-2" />
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
                                    placeholder="Ingresa aquí el código..."
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

                                        resize-none
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
                                    <i class="fa-solid fa-code"></i>
                                </div>
                            </div>

                            <InputError :message="form.errors.codigo" class="mt-2" />
                        </div>

                        <div class="flex items-center gap-4">
                            <Link :href="route('correo-madre.index')">
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