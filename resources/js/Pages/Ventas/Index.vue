<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { ref, watch } from "vue";
import axios from "axios";
import Swal from 'sweetalert2';

const props = defineProps({
    modelValue: String,
    resultado_consulta: Object,
    filtros: Object,
    mensaje_edit: String,
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

if (props.mensaje_edit != '') {
    swalWithTailwind.fire({
        title: props.mensaje_edit,
        icon: 'success',
    })
}

const form = useForm({
    juego: "",
    cliente: "",
    correo: "",
    fecha: "",
});

// Emitir cambios al padre
const emit = defineEmits(["update:modelValue"]);

// Variables reactivas
form.juego = ref(props.modelValue || "");
const sugerencias = ref([]);
const juegoEncontrado = ref();
const sectionConsultar = ref(false);

if (props.resultado_consulta && props.resultado_consulta.length > 0) {
    form.juego = props.filtros.juego
    form.cliente = props.filtros.cliente
    form.correo = props.filtros.correo
    form.fecha = props.filtros.fecha
    sectionConsultar.value = true;
    window.location.href = '#section-resultado';
} else {
    form.juego = props.filtros.juego
    form.cliente = props.filtros.cliente
    form.correo = props.filtros.correo
    form.fecha = props.filtros.fecha

    swalWithTailwind.fire({
        title: 'No se encontro ninguna venta con estos filtros',
        icon: 'warning',
    })
}

const formatFecha = (fecha) => {
    if (!fecha) return "";

    const fechaLimpia = fecha.replace("T", " ").split(".")[0];
    const [fechaParte, horaParte] = fechaLimpia.split(" ");

    const [year, month, day] = fechaParte.split("-");
    const [hour, minute, second] = horaParte.split(":");

    let hora = Number(hour);
    const ampm = hora >= 12 ? "p. m." : "a. m.";

    hora = hora % 12;
    hora = hora ? hora : 12;

    return `${day}/${month}/${year}, ${hora}:${minute}:${second} ${ampm}`;
};

// Observa cambios en modelValue y actualiza searchQuery
watch(
    () => props.modelValue,
    (nuevoValor) => {
        form.juego = nuevoValor;
    }
);

// Función para buscar correos en el backend
const buscarJuegos = async () => {
    form.errors.juego = '';
    juegoEncontrado.value = false;

    if (form.juego.length < 1) {
        sugerencias.value = [];
        return;
    }

    try {
        const { data } = await axios.get(route("buscar-juegos"), {
            params: { juego: form.juego },
        });

        sugerencias.value = data;

        if (sugerencias.value.length == 0) {
            form.errors.juego = 'No se encontró ningún juego';
        } else {
            if (sugerencias.value[0]['juego'] == form.juego) {
                juegoEncontrado.value = true;
            }
        }
    } catch (error) {
        console.error("Error buscando juegos:", error);
    }
};

// Función para seleccionar un correo
const seleccionarJuego = async (nombreJuego) => {
    form.juego = nombreJuego;
    sugerencias.value = [];
    juegoEncontrado.value = true;
    emit("update:modelValue", nombreJuego);
};

const eliminarVenta = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas eliminar esta venta?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Si, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("ventas.destroy", id), {
                onSuccess: () => {
                    swalWithTailwind.fire({
                        title: 'Venta eliminada correctamente',
                        icon: 'success',
                    });
                },
                onError: () => {
                    swalWithTailwind.fire({
                        title: 'Hubo un error al eliminar la venta',
                        icon: 'error',
                    });
                },
                preserveScroll: true,
                preserveState: true,
            });
        }
    });
};

const formatoCop = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
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

const claseMedioPago = (medioPago) => {
    if (medioPago === 'Nequi') return 'bg-purple-100 text-purple-800';
    if (medioPago === 'Bancolombia') return 'bg-blue-100 text-blue-800';
    if (medioPago === 'Mercado Pago') return 'bg-cyan-100 text-cyan-800';
    if (medioPago === 'Efectivo') return 'bg-green-100 text-green-800';

    return 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head title="🛍️ Consultar Ventas" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🛍️ Consultar Ventas
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <!-- Filtros -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <header class="mb-6">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-bold text-gray-900">
                                        Información de las ventas
                                    </h2>

                                    <p class="mt-1 text-sm text-gray-600">
                                        Usa los filtros para consultar una venta por juego, cliente, correo o fecha.
                                    </p>
                                </div>

                                <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                                    <p class="text-sm text-gray-500">
                                        Módulo
                                    </p>

                                    <p class="text-lg font-bold text-gray-900">
                                        Consulta
                                    </p>
                                </div>
                            </div>
                        </header>

                        <form
                            @submit.prevent="form.get(route('ventas.index'))"
                            class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4"
                        >
                            <!-- Juego -->
                            <div>
                                <InputLabel for="juego" value="Juego" />

                                <div class="relative">
                                    <TextInput
                                        id="juego"
                                        ref="juego"
                                        v-model="form.juego"
                                        @input="buscarJuegos"
                                        type="text"
                                        class="mt-1 w-full"
                                        autocomplete="off"
                                        placeholder="Buscar juego..."
                                    />

                                    <ul
                                        v-if="form.juego !== '' && sugerencias.length !== 0 && juegoEncontrado == false"
                                        class="absolute bg-white border border-gray-300 w-full mt-1 rounded-md shadow-md z-20 max-h-56 overflow-y-auto"
                                    >
                                        <li
                                            v-for="Datosjuego in sugerencias"
                                            :key="Datosjuego.id"
                                            @click="seleccionarJuego(Datosjuego.juego)"
                                            class="p-3 cursor-pointer bg-white hover:bg-gray-100 text-black text-sm"
                                        >
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-gamepad text-blue-500"></i>
                                                <span>{{ Datosjuego.juego }}</span>
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <InputError class="mt-2" :message="form.errors.juego" />
                            </div>

                            <!-- Cliente -->
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
                                        placeholder="Nombre del cliente..."
                                    />
                                </div>

                                <InputError class="mt-2" :message="form.errors.cliente" />
                            </div>

                            <!-- Correo -->
                            <div>
                                <InputLabel for="correo" value="Correo" />

                                <div class="relative w-full">
                                    <TextInput
                                        id="correo"
                                        ref="correo"
                                        v-model="form.correo"
                                        type="text"
                                        class="mt-1 w-full"
                                        autocomplete="off"
                                        placeholder="Correo de la cuenta..."
                                    />
                                </div>

                                <InputError class="mt-2" :message="form.errors.correo" />
                            </div>

                            <!-- Fecha -->
                            <div>
                                <InputLabel for="fecha" value="Fecha" />

                                <div class="relative w-full">
                                    <TextInput
                                        id="fecha"
                                        ref="fecha"
                                        v-model="form.fecha"
                                        type="date"
                                        class="mt-1 w-full"
                                        autocomplete="off"
                                    />
                                </div>

                                <InputError class="mt-2" :message="form.errors.fecha" />
                            </div>

                            <div class="md:col-span-2 xl:col-span-4 flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                                <PrimaryButton
                                    class="w-full sm:w-auto justify-center"
                                >
                                    <i class="fa-solid fa-magnifying-glass mr-2"></i>
                                    Consultar ventas
                                </PrimaryButton>
                            </div>
                        </form>
                    </section>
                </div>

                <!-- Resultados -->
                <div
                    v-if="sectionConsultar"
                    class="p-4 sm:p-8 bg-white shadow sm:rounded-lg"
                    id="section-resultado"
                >
                    <section>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">
                                    Resultado de las ventas
                                </h2>

                                <p class="text-sm text-gray-500">
                                    Ventas encontradas según los filtros aplicados.
                                </p>
                            </div>

                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold w-fit">
                                {{ resultado_consulta.length }} resultados
                            </span>
                        </div>

                        <div class="overflow-x-auto shadow rounded-lg">
                            <table class="min-w-full border border-gray-200">
                                <thead>
                                    <tr class="bg-gray-100 border-b">
                                        <th class="sticky right-0 bg-gray-100 px-4 py-2 z-10 text-center">
                                            Acciones
                                        </th>
                                        <th class="px-4 py-2 text-left">#</th>
                                        <th class="px-4 py-2 text-left">Fecha</th>
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
                                        v-for="(venta, i) in resultado_consulta"
                                        :key="venta.id"
                                        class="border-b hover:bg-gray-100"
                                    >
                                        <!-- Acciones -->
                                        <td class="sticky right-0 bg-white px-4 py-2 z-10">
                                            <div class="flex flex-col items-center gap-2">
                                                <Link :href="route('ventas.edit', venta.id)">
                                                    <SecondaryButton class="w-8 h-8 flex items-center justify-center">
                                                        <i class="fa-solid fa-user-pen"></i>
                                                    </SecondaryButton>
                                                </Link>

                                                <DangerButton
                                                    class="w-8 h-8 flex items-center justify-center"
                                                    @click="eliminarVenta(venta.id)"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </DangerButton>
                                            </div>
                                        </td>

                                        <!-- Número -->
                                        <td class="px-4 py-2">
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-sm font-bold">
                                                #{{ i + 1 }}
                                            </span>
                                        </td>

                                        <!-- Fecha -->
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-sm font-bold min-w-[170px]">
                                                <i class="fa-regular fa-clock"></i>
                                                {{ formatFecha(venta.created_at) }}
                                            </span>
                                        </td>

                                        <!-- Juego -->
                                        <td class="px-4 py-2">
                                            <div class="bg-gray-50 rounded-lg p-2 min-w-[220px]">
                                                <div class="flex items-center gap-2 text-md">
                                                    <i class="fa-solid fa-gamepad text-blue-500"></i>

                                                    <span class="font-semibold text-gray-900">
                                                        {{ venta.correo_juego.juego }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Cuenta -->
                                        <td class="px-4 py-2">
                                            <div class="bg-gray-50 rounded-lg p-2 min-w-[250px]">
                                                <div class="flex items-center gap-2 text-sm text-gray-800">
                                                    <i class="fa-solid fa-envelope text-blue-500"></i>

                                                    <span class="truncate">
                                                        {{ venta.correo_juego.correo }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Cliente -->
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-3 py-1 rounded-lg text-sm font-bold">
                                                <i class="fa-solid fa-user"></i>
                                                {{ venta.cliente }}
                                            </span>
                                        </td>

                                        <!-- Detalles -->
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

                                        <!-- Pago -->
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

                                        <!-- Valor -->
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-bold shadow-sm bg-gradient-to-r from-green-100 to-green-200 text-green-900">
                                                ${{ formatoCop(venta.precio) }}
                                            </span>
                                        </td>
                                    </tr>

                                    <tr v-if="resultado_consulta.length === 0">
                                        <td class="px-4 py-10 text-center" colspan="9">
                                            <div class="flex flex-col items-center gap-3">
                                                <div class="w-16 h-16 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center">
                                                    <i class="fa-solid fa-cart-shopping text-2xl"></i>
                                                </div>

                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">
                                                        No hay ventas registradas
                                                    </h3>

                                                    <p class="text-sm text-gray-500">
                                                        No se encontraron ventas con los filtros seleccionados.
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
        </template>
    </LayoutPageHeader>
</template>