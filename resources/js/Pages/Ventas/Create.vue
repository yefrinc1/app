<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { useForm, Head, usePage } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import JuegoCatalogoInput from '@/Components/JuegoCatalogoInput.vue';
import { ref, watch } from "vue";
import axios from "axios";
import Swal from 'sweetalert2';

const props = defineProps({
    modelValue: String,
    cuenta_juego: Object,
});

const user = usePage().props.auth.user;

const form = useForm({
    juego: "",
    tipo_cuenta: "",
    consola: "",
    cliente: "",
    precio: "",
    moneda: "COP",
    medio_pago: "",
    id_usuario: user.id,
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

// Emitir cambios al padre
const emit = defineEmits(["update:modelValue"]);

// Variables reactivas
form.juego = ref(props.modelValue || "");
const juegoVendido = ref(false);
const codigoRef = ref(null);
const textoCopiado = ref(false);
const inventarioDisponible = ref(null);
const verificandoInventario = ref(false);
const alSeleccionarJuego = () => {
    if (form.juego && form.tipo_cuenta && form.consola) {
        verificarInventario();
    }}
;

// Observa cambios en modelValue y actualiza searchQuery
watch(
    [
        () => form.tipo_cuenta,
        () => form.consola
    ],
    ([tipoCuenta, consola]) => {
        if (form.juego != '' && tipoCuenta && consola) {
            verificarInventario();
        } else {
            inventarioDisponible.value = null;
        }
    }
);

// Función para manejar el envío manualmente
const submitForm = () => {
    swalWithTailwind.fire({
        title: 'Generando venta...',
        didOpen: () => {
            swalWithTailwind.showLoading();
        }
    });

    form.post(route("ventas.store"), {
        onSuccess: () => {
            swalWithTailwind.close();

            if (props.cuenta_juego.resultado == 1) {
                form.reset();
                juegoVendido.value = true;
                window.location.href = '#section-resultado-juego';
            } else {
                form.reset();
                juegoVendido.value = false;
                swalWithTailwind.fire({
                    title: props.cuenta_juego.mensaje,
                    icon: "error",
                });
            }
        },
        onError: (errors) => {
            swalWithTailwind.close();
            console.log("Errores en el formulario:", errors);
        },
    });
};

const copiarCodigo = async () => {
    if (!codigoRef.value) return;

    textoCopiado.value = false;
    const texto = codigoRef.value.innerText || codigoRef.value.textContent;

    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(texto);
            textoCopiado.value = true;
            return;
        } catch (err) {
            console.error("Error al copiar con clipboard API:", err);
        }
    }

    try {
        const input = document.createElement("textarea");
        input.value = texto;
        document.body.appendChild(input);
        input.select();
        document.execCommand("copy");
        document.body.removeChild(input);
        textoCopiado.value = true;
    } catch (error) {
        console.error("Error al copiar el código:", error);
    }
};

const verificarInventario = async () => {
    verificandoInventario.value = true;
    inventarioDisponible.value = null;

    try {
        const { data } = await axios.get(route("ventas.comprobar-existencia-juego"), {
            params: {
                juego: form.juego,
                tipo_cuenta: form.tipo_cuenta,
                consola: form.consola
            },
        });

        inventarioDisponible.value = Object.keys(data).length !== 0;
    } catch (error) {
        console.error("Error verificando inventario:", error);
        inventarioDisponible.value = false;
    } finally {
        verificandoInventario.value = false;
    }
};
</script>

<template>
    <Head title="Venta de Juego" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                💸 Venta de Juego
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <!-- Formulario principal -->
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <section>
                        <header class="mb-6">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900">
                                        Información para la venta
                                    </h2>

                                    <p class="mt-1 text-sm text-gray-600">
                                        Completa los datos del cliente, juego, licencia y medio de pago.
                                    </p>
                                </div>

                                <div
                                    v-if="inventarioDisponible !== null || verificandoInventario"
                                    class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3"
                                >
                                    <p class="text-xs text-gray-500">
                                        Estado del inventario
                                    </p>

                                    <div class="mt-1">
                                        <span
                                            v-if="verificandoInventario"
                                            class="inline-flex items-center gap-2 text-gray-600 font-bold text-sm"
                                        >
                                            <i class="fa-solid fa-spinner fa-spin"></i>
                                            Verificando...
                                        </span>

                                        <span
                                            v-else-if="inventarioDisponible === true"
                                            class="inline-flex items-center gap-2 text-green-700 font-bold text-sm"
                                        >
                                            <i class="fa-solid fa-circle-check"></i>
                                            Disponible
                                        </span>

                                        <span
                                            v-else-if="inventarioDisponible === false"
                                            class="inline-flex items-center gap-2 text-red-700 font-bold text-sm"
                                        >
                                            <i class="fa-solid fa-circle-xmark"></i>
                                            No disponible
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </header>

                        <form @submit.prevent="submitForm" class="space-y-6">
                            <!-- Datos del juego -->
                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                <h3 class="text-md font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-gamepad text-blue-500"></i>
                                    Datos del juego
                                </h3>

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                    <div class="lg:col-span-1">
                                        <InputLabel for="juego" value="Juego" />

                                        <JuegoCatalogoInput
                                            v-model="form.juego"
                                            input-id="juego_manual"
                                            :error="form.errors.juego"
                                            @juego-seleccionado="alSeleccionarJuego"
                                        />
                                    </div>

                                    <div>
                                        <InputLabel for="tipo_cuenta" value="Tipo de cuenta" />

                                        <div class="relative">
                                            <select
                                                id="tipo_cuenta"
                                                v-model="form.tipo_cuenta"
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
                                                <option value="">Selecciona un tipo de cuenta</option>
                                                <option value="Primaria">👑 Cuenta Primaria</option>
                                                <option value="Secundaria">🎮 Cuenta Secundaria</option>
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
                                                <i class="fa-solid fa-key"></i>
                                            </div>
                                        </div>

                                        <InputError class="mt-2" :message="form.errors.tipo_cuenta" />
                                    </div>

                                    <div>
                                        <InputLabel for="consola" value="Consola" />

                                        <div class="flex items-end gap-3">
                                            <div class="w-full">
                                                <div class="relative">
                                                    <select
                                                        id="consola"
                                                        v-model="form.consola"
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
                                                        <option value="">Selecciona una consola</option>
                                                        <option value="PS4">PlayStation 4 (PS4)</option>
                                                        <option value="PS5">PlayStation 5 (PS5)</option>
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
                                                        <i class="fa-solid fa-gamepad"></i>
                                                    </div>
                                                </div>

                                                <InputError class="mt-2" :message="form.errors.consola" />
                                            </div>

                                            <div class="mb-2 w-8 flex justify-center">
                                                <i
                                                    v-if="verificandoInventario"
                                                    class="fa-solid fa-spinner fa-spin text-gray-500 text-xl"
                                                />

                                                <span
                                                    v-else-if="inventarioDisponible === true"
                                                    class="text-green-600 text-2xl"
                                                    title="Disponible en inventario"
                                                >
                                                    <i class="fa-solid fa-circle-check"></i>
                                                </span>

                                                <span
                                                    v-else-if="inventarioDisponible === false"
                                                    class="text-red-600 text-2xl"
                                                    title="No disponible en inventario"
                                                >
                                                    <i class="fa-solid fa-circle-xmark"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Datos del cliente y precio -->
                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4">
                                <h3 class="text-md font-bold text-gray-900 mb-4 flex items-center gap-2">
                                    <i class="fa-solid fa-user text-purple-500"></i>
                                    Cliente y valor
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                                                required
                                            />
                                        </div>

                                        <InputError class="mt-2" :message="form.errors.cliente" />
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

                                    <div>
                                        <InputLabel for="moneda" value="Moneda" />

                                        <div class="relative">
                                            <select
                                                id="moneda"
                                                v-model="form.moneda"
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

                                                    focus:outline-none
                                                    focus:border-amber-500
                                                    focus:ring-4
                                                    focus:ring-red-500/20
                                                "
                                            >
                                                <option value="COP">COP</option>
                                                <option value="USD">USD</option>
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
                                                <i class="fa-solid fa-chevron-down"></i>
                                            </div>
                                        </div>

                                        <InputError class="mt-2" :message="form.errors.moneda" />
                                    </div>
                                </div>
                            </div>

                            <!-- Medio de pago -->
                            <div class="bg-gradient-to-br from-white via-red-50 to-amber-50 border border-red-100 rounded-2xl p-4 shadow-md">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                                    <div>
                                        <InputLabel value="Medio de pago" />

                                        <p class="text-xs text-gray-500 mt-1">
                                            Selecciona el método usado por el cliente.
                                        </p>
                                    </div>

                                    <span
                                        v-if="form.medio_pago"
                                        class="bg-gradient-to-r from-red-600 to-amber-500 text-white px-3 py-1 rounded-full text-xs font-bold w-fit shadow-md shadow-red-500/20"
                                    >
                                        {{ form.medio_pago }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div
                                        @click="form.medio_pago = 'Bancolombia'"
                                        :class="[
                                            'relative cursor-pointer border rounded-2xl p-4 flex flex-col items-center transition-all duration-300 bg-white hover:shadow-lg hover:-translate-y-0.5',
                                            form.medio_pago === 'Bancolombia'
                                                ? 'border-amber-500 ring-4 ring-red-500/20 bg-gradient-to-br from-red-50 to-amber-50 shadow-xl shadow-red-500/15'
                                                : 'border-red-100 hover:border-red-300'
                                        ]"
                                    >
                                        <div
                                            v-if="form.medio_pago === 'Bancolombia'"
                                            class="absolute -top-2 -right-2 h-7 w-7 flex items-center justify-center rounded-full bg-gradient-to-r from-red-600 to-amber-500 text-white shadow-md"
                                        >
                                            <i class="fa-solid fa-check text-sm"></i>
                                        </div>

                                        <img src="/images/medios-pago/bancolombia.png" class="h-12 object-contain">

                                        <span class="mt-2 text-sm font-bold text-gray-700">
                                            Bancolombia
                                        </span>
                                    </div>

                                    <div
                                        @click="form.medio_pago = 'Nequi'"
                                        :class="[
                                            'relative cursor-pointer border rounded-2xl p-4 flex flex-col items-center transition-all duration-300 bg-white hover:shadow-lg hover:-translate-y-0.5',
                                            form.medio_pago === 'Nequi'
                                                ? 'border-amber-500 ring-4 ring-red-500/20 bg-gradient-to-br from-red-50 to-amber-50 shadow-xl shadow-red-500/15'
                                                : 'border-red-100 hover:border-red-300'
                                        ]"
                                    >
                                        <div
                                            v-if="form.medio_pago === 'Nequi'"
                                            class="absolute -top-2 -right-2 h-7 w-7 flex items-center justify-center rounded-full bg-gradient-to-r from-red-600 to-amber-500 text-white shadow-md"
                                        >
                                            <i class="fa-solid fa-check text-sm"></i>
                                        </div>

                                        <img src="/images/medios-pago/nequi.png" class="h-12 object-contain">

                                        <span class="mt-2 text-sm font-bold text-gray-700">
                                            Nequi
                                        </span>
                                    </div>

                                    <div
                                        @click="form.medio_pago = 'Mercado Pago'"
                                        :class="[
                                            'relative cursor-pointer border rounded-2xl p-4 flex flex-col items-center transition-all duration-300 bg-white hover:shadow-lg hover:-translate-y-0.5',
                                            form.medio_pago === 'Mercado Pago'
                                                ? 'border-amber-500 ring-4 ring-red-500/20 bg-gradient-to-br from-red-50 to-amber-50 shadow-xl shadow-red-500/15'
                                                : 'border-red-100 hover:border-red-300'
                                        ]"
                                    >
                                        <div
                                            v-if="form.medio_pago === 'Mercado Pago'"
                                            class="absolute -top-2 -right-2 h-7 w-7 flex items-center justify-center rounded-full bg-gradient-to-r from-red-600 to-amber-500 text-white shadow-md"
                                        >
                                            <i class="fa-solid fa-check text-sm"></i>
                                        </div>

                                        <img src="/images/medios-pago/mercadopago.png" class="h-12 object-contain">

                                        <span class="mt-2 text-sm font-bold text-gray-700">
                                            Mercado Pago
                                        </span>
                                    </div>

                                    <div
                                        @click="form.medio_pago = 'Efectivo'"
                                        :class="[
                                            'relative cursor-pointer border rounded-2xl p-4 flex flex-col items-center transition-all duration-300 bg-white hover:shadow-lg hover:-translate-y-0.5',
                                            form.medio_pago === 'Efectivo'
                                                ? 'border-amber-500 ring-4 ring-red-500/20 bg-gradient-to-br from-red-50 to-amber-50 shadow-xl shadow-red-500/15'
                                                : 'border-red-100 hover:border-red-300'
                                        ]"
                                    >
                                        <div
                                            v-if="form.medio_pago === 'Efectivo'"
                                            class="absolute -top-2 -right-2 h-7 w-7 flex items-center justify-center rounded-full bg-gradient-to-r from-red-600 to-amber-500 text-white shadow-md"
                                        >
                                            <i class="fa-solid fa-check text-sm"></i>
                                        </div>

                                        <img src="/images/medios-pago/efectivo.png" class="h-12 object-contain">

                                        <span class="mt-2 text-sm font-bold text-gray-700">
                                            Efectivo
                                        </span>
                                    </div>
                                </div>

                                <InputError class="mt-2" :message="form.errors.medio_pago" />
                            </div>

                            <!-- Acción -->
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                <PrimaryButton
                                    class="w-full sm:w-auto justify-center"
                                    :disabled="form.processing || inventarioDisponible == false"
                                >
                                    <i class="fa-solid fa-cash-register mr-2"></i>
                                    Generar Venta
                                </PrimaryButton>

                                <span
                                    v-if="inventarioDisponible === false"
                                    class="text-sm text-red-600 font-semibold"
                                >
                                    No puedes generar la venta porque no hay inventario disponible.
                                </span>
                            </div>
                        </form>
                    </section>
                </div>

                <!-- Resultado venta -->
                <div
                    v-if="juegoVendido"
                    class="p-4 sm:p-8 bg-white shadow sm:rounded-lg"
                    id="section-resultado-juego"
                >
                    <section>
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-6">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">
                                    ✅ Venta generada correctamente
                                </h2>

                                <p class="text-sm text-gray-500 mt-1">
                                    Copia y envía estos datos al cliente.
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    @click="copiarCodigo"
                                    class="bg-gray-800 hover:bg-gray-900 text-white font-semibold px-4 py-2 rounded-lg shadow-md transition-all duration-300 active:scale-95 flex items-center gap-2"
                                >
                                    <i class="fa-regular fa-copy text-sm"></i>
                                    Copiar datos
                                </button>

                                <Transition
                                    enter-active-class="transition-opacity duration-300 ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition-opacity duration-300 ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p
                                        v-if="textoCopiado"
                                        class="text-sm text-green-600 font-bold"
                                    >
                                        Copiado.
                                    </p>
                                </Transition>
                            </div>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-5">
                            <h2
                                ref="codigoRef"
                                class="text-base text-gray-800 leading-relaxed"
                            >
                                ¡IMPORTANTE! Lee esto antes de continuar con los pasos 🛑 <br><br>

                                1️⃣ El código de verificación es de un solo uso. Utilízalo exclusivamente en la consola donde vas a descargar el juego. <br>
                                2️⃣ No inicies sesión como invitado - El juego no funcionará si lo haces. <br>
                                3️⃣ No modifiques los datos de la cuenta para evitar inconvenientes. <br><br>

                                {{ cuenta_juego.juego }} <br>
                                Cuenta: {{ cuenta_juego.tipo_cuenta }} {{ cuenta_juego.consola }} <br><br>

                                👤 Usuario: {{ cuenta_juego.correo }} <br>
                                🔒 Contraseña: {{ cuenta_juego.contrasena }} <br>

                                <span
                                    v-if="cuenta_juego.codigo && cuenta_juego.codigo.trim() !== ''"
                                >
                                    🔑 Código de verificación: {{ cuenta_juego.codigo }}
                                </span>
                                <br>
                            </h2>
                        </div>

                        <p
                            v-if="cuenta_juego.ultimo_codigo == 1"
                            class="mt-5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm font-semibold"
                        >
                            🚨 Ya no quedan más códigos de verificación, notificar al administrador.
                        </p>
                    </section>
                </div>
            </div>
        </template>
    </LayoutPageHeader>
</template>