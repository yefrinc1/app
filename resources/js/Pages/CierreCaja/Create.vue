<script setup>
import { defineProps } from "vue";
import { Link, Head, useForm } from "@inertiajs/vue3";
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    ventas: Object,
    ingresos: Object,
    egresos: Object,
    saldo_inicial: Number,
    saldo_final: Number,
    fecha: Date,
});

const fechaFormateada = ref(new Date(props.fecha).toISOString().split('T')[0]);

const form = useForm({
  fecha: fechaFormateada,
  saldo_inicial: props.saldo_inicial,
  ingresos: props.ingresos,
  egresos: props.egresos,
  saldo_final: props.saldo_final,
  observaciones: '',
});

const consultarCierrePorFecha = () => {
    fechaFormateada.value = new Date(form.fecha).toISOString().split('T')[0]
    router.get(route('cierre-caja.create'), { fecha: form.fecha }, {
    preserveState: true,
    preserveScroll: true,
  })
};

const cerrarCaja = () => {
    form.saldo_inicial = props.saldo_inicial;
    form.ingresos = props.ingresos;
    form.egresos = props.egresos;
    form.saldo_final = props.saldo_final;
    form.post(route('cierre-caja.store'));
};

</script>

<template>

    <Head title="Cierre de Caja" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">🎰 Cierre de Caja</h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <h2 class="text-lg font-medium text-gray-900 mb-6">
                        Informacion de ventas:  {{ fechaFormateada }}
                    </h2>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">#</th>
                                    <th class="px-4 py-2 text-left">Juego</th>
                                    <th class="px-4 py-2 text-left">Correo</th>
                                    <th class="px-4 py-2 text-center">Cliente</th>
                                    <th class="px-4 py-2 text-center">Cuenta</th>
                                    <th class="px-4 py-2 text-center">Consola</th>
                                    <th class="px-4 py-2 text-center">Forma Pago</th>
                                    <th class="px-4 py-2 text-center">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(venta, i) in ventas" :key="venta.id"
                                    class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ i + 1 }}</td>
                                    <td class="px-4 py-2">{{ venta.correoJuego.juego }}</td>
                                    <td class="px-4 py-2">{{ venta.correoJuego.correo }}</td>
                                    <td class="px-4 py-2">{{ venta.cliente }}</td>
                                    <td class="px-4 py-2">{{ venta.tipo_cuenta }}</td>
                                    <td class="px-4 py-2">{{ venta.consola }}</td>
                                    <td class="px-4 py-2">{{ venta.medio_pago }}</td>
                                    <td class="px-4 py-2">{{ venta.precio }}</td>
                                </tr>
                                <tr v-if="ventas.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="8">
                                        No hay ventas registradas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <h2 class="text-lg font-medium text-gray-900 mb-6">
                        Totales de la caja diaria
                    </h2>
                    
                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Caja Inicial</th>
                                    <th class="px-4 py-2 text-left">Ingresos</th>
                                    <th class="px-4 py-2 text-left">Egresos</th>
                                    <th class="px-4 py-2 text-left">Caja Final</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">$ {{ saldo_inicial }}</td>
                                    <td class="px-4 py-2">$ {{ ingresos }}</td>
                                    <td class="px-4 py-2">$ {{ egresos }}</td>
                                    <td class="px-4 py-2">$ {{ saldo_final }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section class="w-full md:w-1/2">
                    <h2 class="text-lg font-medium text-gray-900">
                        Cierre de la caja diaria
                    </h2>

                    <div class="mt-6">
                        <InputLabel for="fecha" value="Fecha" />

                        <TextInput id="fecha" type="date" class="mt-1 block w-full" v-model="form.fecha" @change="consultarCierrePorFecha"/>

                        <InputError :message="form.errors.fecha" class="mt-2" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="observaciones" value="Observaciones" />

                        <textarea
                            id="observaciones"
                            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                            rows="4"
                            v-model="form.observaciones"
                        ></textarea>

                        <InputError :message="form.errors.observaciones" class="mt-2" />
                    </div>

                    <div class="mt-6 flex items-center gap-4">
                        <Link :href="route('cierre-caja.index')">
                            <SecondaryButton>Volver</SecondaryButton>
                        </Link>
                        <PrimaryButton @click="cerrarCaja">
                            Cerrar caja
                        </PrimaryButton>
                    </div>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>
