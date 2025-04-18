<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { useForm, Head } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';

const props = defineProps({
    venta: Object,
});

const form = useForm({
    id: props.venta.id,
    cliente: props.venta.cliente,
    medio_pago: props.venta.medio_pago,
    precio: props.venta.precio,
});
</script>

<template>
    <Head title="🛍️ Editar Ventas" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🛍️ Editar Ventas</h2>
        </template>

        <template #contenido-pagina>       
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Información de las ventas</h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Datos para consultar una venta
                        </p>
                    </header>
                    <form @submit.prevent="form.patch(route('ventas.update', form.id))" class="mt-6 space-y-6 w-full md:w-1/2">
                        <div>
                            <InputLabel for="cliente" value="Cliente" />

                            <div class="relative w-full">
                                <TextInput
                                    id="cliente"
                                    ref="cliente"
                                    v-model="form.cliente"
                                    type="text"
                                    class="mt-1 w-full"
                                    autocomplete="off"
                                />
                            </div>
                        
                            <InputError class="mt-2" :message="form.errors.cliente" />
                        </div>

                        <div>
                            <InputLabel for="medio_pago" value="Medio de pago" />

                            <select
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                v-model="form.medio_pago" 
                                id="medio_pago"
                                required
                            >
                                <option value="" >Selecciona un medio de pago</option>
                                <option value="Bancolombia">Bancolombia</option>
                                <option value="Nequi">Nequi</option>
                                <option value="Mercado Pago">Mercado Pago</option>
                                <option value="Efectivo">Efectivo</option>
                            </select>
                        
                            <InputError class="mt-2" :message="form.errors.medio_pago" />
                        </div>

                        <div>
                            <InputLabel for="precio" value="Precio" />
                            <TextInput
                                id="precio"
                                ref="precio"
                                v-model="form.precio"
                                type="number"
                                step="0.01"
                                class="mt-1 w-full"
                                autocomplete="off"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.precio" />
                        </div>

                        <div class="flex items-center gap-4">
                            <PrimaryButton>Editar venta</PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>