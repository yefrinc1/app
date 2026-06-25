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
    retiro_utilidades: Object,
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

function formatoPrecio(precio) {
    const esPositivo = precio >= 0;
    const color = esPositivo ? 'text-green-600' : 'text-red-600';
    const signo = esPositivo ? '+' : '-';

    return {
        texto: `${signo} $${Math.abs(precio)}`,
        color
    };
}

const formatoCop = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
};

const claseMedioPago = (medioPago) => {
    if (medioPago === 'Nequi') return 'bg-purple-100 text-purple-800';
    if (medioPago === 'Bancolombia') return 'bg-blue-100 text-blue-800';
    if (medioPago === 'Mercado Pago') return 'bg-cyan-100 text-cyan-800';
    if (medioPago === 'Efectivo') return 'bg-green-100 text-green-800';

    return 'bg-gray-100 text-gray-800';
};

const claseTipoCuenta = (tipoCuenta) => {
    if (tipoCuenta === 'Primaria') return 'bg-indigo-100 text-indigo-800';
    if (tipoCuenta === 'Secundaria') return 'bg-orange-100 text-orange-800';

    return 'bg-gray-100 text-gray-800';
};

const claseConsola = (consola) => {
    if (consola === 'PS4') return 'bg-blue-100 text-blue-800';
    if (consola === 'PS5') return 'bg-slate-100 text-slate-800';

    return 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head title="Cierre de Caja" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🎰 Cierre de Caja
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">

                <!-- Header principal -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">
                                Cierre diario
                            </h2>

                            <p class="text-sm text-gray-500 mt-1">
                                Información de ventas y movimientos del día:
                                <span class="font-bold text-gray-800">{{ fechaFormateada }}</span>
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                <p class="text-sm text-gray-500">Ventas registradas</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    {{ ventas.length }}
                                </p>
                            </div>

                            <div class="bg-green-50 border border-green-100 rounded-xl px-4 py-3">
                                <p class="text-sm text-green-700">Caja final</p>
                                <p class="text-2xl font-bold text-green-800">
                                    ${{ formatoCop(saldo_final) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cards resumen -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Caja inicial</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    ${{ formatoCop(saldo_inicial) }}
                                </p>
                            </div>

                            <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                                <i class="fa-solid fa-wallet text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Ingresos</p>
                                <p class="text-2xl font-bold text-green-700">
                                    ${{ formatoCop(ingresos) }}
                                </p>
                            </div>

                            <div class="w-12 h-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                <i class="fa-solid fa-arrow-trend-up text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Egresos</p>
                                <p class="text-2xl font-bold text-red-700">
                                    ${{ formatoCop(egresos) }}
                                </p>
                            </div>

                            <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center">
                                <i class="fa-solid fa-arrow-trend-down text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Retiro utilidades</p>
                                <p class="text-2xl font-bold text-amber-700">
                                    ${{ formatoCop(retiro_utilidades) }}
                                </p>
                            </div>

                            <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                                <i class="fa-solid fa-money-bill-transfer text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Caja final</p>
                                <p class="text-2xl font-bold text-gray-900">
                                    ${{ formatoCop(saldo_final) }}
                                </p>
                            </div>

                            <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center">
                                <i class="fa-solid fa-cash-register text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ventas -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">
                                    Ventas del día
                                </h2>

                                <p class="text-sm text-gray-500">
                                    Detalle de las ventas registradas para el cierre.
                                </p>
                            </div>

                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold w-fit">
                                {{ ventas.length }} ventas
                            </span>
                        </div>

                        <div class="overflow-x-auto shadow rounded-lg">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100 border-b">
                                        <th class="px-4 py-2 text-left">#</th>
                                        <th class="px-4 py-2 text-left">Juego</th>
                                        <th class="px-4 py-2 text-left">Cuenta</th>
                                        <th class="px-4 py-2 text-left">Cliente</th>
                                        <th class="px-4 py-2 text-left">Detalles</th>
                                        <th class="px-4 py-2 text-left">Pago</th>
                                        <th class="px-4 py-2 text-left">Valor</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="(venta, i) in ventas"
                                        :key="venta.id"
                                        class="border-b hover:bg-gray-100"
                                    >
                                        <td class="px-4 py-2">
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-sm font-bold">
                                                #{{ i + 1 }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <div class="bg-gray-50 rounded-lg p-2 min-w-[220px]">
                                                <div class="flex items-center gap-2 text-md">
                                                    <i class="fa-solid fa-gamepad text-blue-500"></i>
                                                    <span class="font-semibold text-gray-900">
                                                        {{ venta.correoJuego.juego }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-2">
                                            <div class="bg-gray-50 rounded-lg p-2 min-w-[240px]">
                                                <div class="flex items-center gap-2 text-sm text-gray-800">
                                                    <i class="fa-solid fa-envelope text-blue-500"></i>
                                                    <span class="truncate">
                                                        {{ venta.correoJuego.correo }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-sm font-bold">
                                                <i class="fa-solid fa-user"></i>
                                                {{ venta.cliente }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    :class="[
                                                        'inline-flex items-center px-3 py-1 rounded-lg text-sm font-bold',
                                                        claseTipoCuenta(venta.tipo_cuenta)
                                                    ]"
                                                >
                                                    {{ venta.tipo_cuenta }}
                                                </span>

                                                <span
                                                    :class="[
                                                        'inline-flex items-center px-3 py-1 rounded-lg text-sm font-bold',
                                                        claseConsola(venta.consola)
                                                    ]"
                                                >
                                                    {{ venta.consola }}
                                                </span>
                                            </div>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span
                                                :class="[
                                                    'inline-flex items-center gap-1 px-3 py-1 rounded-lg text-sm font-bold',
                                                    claseMedioPago(venta.medio_pago)
                                                ]"
                                            >
                                                <i class="fa-solid fa-credit-card"></i>
                                                {{ venta.medio_pago }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-2">
                                            <span
                                                :class="[
                                                    'inline-flex items-center px-3 py-1 rounded-lg text-sm font-bold shadow-sm',
                                                    venta.precio >= 0
                                                        ? 'bg-gradient-to-r from-green-100 to-green-200 text-green-900'
                                                        : 'bg-gradient-to-r from-red-100 to-red-200 text-red-900'
                                                ]"
                                            >
                                                {{ formatoPrecio(venta.precio).texto }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="ventas.length === 0">
                                        <td class="px-4 py-10 text-center" colspan="7">
                                            <div class="flex flex-col items-center gap-3">
                                                <div class="w-16 h-16 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                                                    <i class="fa-solid fa-cart-shopping text-2xl"></i>
                                                </div>

                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">
                                                        No hay ventas registradas
                                                    </h3>

                                                    <p class="text-sm text-gray-500">
                                                        No se encontraron ventas para esta fecha.
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

                <!-- Formulario cierre -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <div class="max-w-2xl">
                            <h2 class="text-lg font-bold text-gray-900">
                                Cierre de la caja diaria
                            </h2>

                            <p class="text-sm text-gray-500 mt-1">
                                Selecciona la fecha, agrega observaciones y confirma el cierre.
                            </p>

                            <div class="mt-6">
                                <InputLabel for="fecha" value="Fecha" />

                                <TextInput
                                    id="fecha"
                                    type="date"
                                    class="mt-1 block w-full"
                                    v-model="form.fecha"
                                    @change="consultarCierrePorFecha"
                                />

                                <InputError :message="form.errors.fecha" class="mt-2" />
                            </div>

                            <div class="mt-6">
                                <InputLabel for="observaciones" value="Observaciones" />

                                <textarea
                                    id="observaciones"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    rows="4"
                                    v-model="form.observaciones"
                                    placeholder="Escribe una observación opcional del cierre..."
                                ></textarea>

                                <InputError :message="form.errors.observaciones" class="mt-2" />
                            </div>

                            <div class="mt-6 flex flex-col sm:flex-row sm:items-center gap-3">
                                <Link :href="route('cierre-caja.index')" class="w-full sm:w-auto">
                                    <SecondaryButton class="w-full sm:w-auto justify-center">
                                        Volver
                                    </SecondaryButton>
                                </Link>

                                <PrimaryButton
                                    @click="cerrarCaja"
                                    class="w-full sm:w-auto justify-center"
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Cerrar caja
                                </PrimaryButton>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </LayoutPageHeader>
</template>