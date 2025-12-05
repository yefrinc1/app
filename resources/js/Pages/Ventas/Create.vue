<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { defineProps } from "vue";
import TextInput from '@/Components/TextInput.vue';
import { useForm, Head, usePage } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
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
const sugerencias = ref([]);
const juegoEncontrado = ref();
const juegoVendido = ref(false);
const codigoRef = ref(null); // Referencia al h2
const textoCopiado = ref(false); // Referencia al h2

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
    form.juego = nombreJuego; // Asigna el valor seleccionado
    sugerencias.value = []; // Oculta la lista
    juegoEncontrado.value = true;
    emit("update:modelValue", nombreJuego); // Actualiza el v-model
};

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
    const texto = codigoRef.value.innerText || codigoRef.value.textContent; // Obtener todo el texto

    // Intentar copiar con Clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(texto);
            textoCopiado.value = true;
            return;
        } catch (err) {
            console.error("Error al copiar con clipboard API:", err);
        }
    }

    // Alternativa con execCommand si Clipboard API falla
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
    swalWithTailwind.fire({
        title: 'Verificando inventario...',
        didOpen: () => {
            swalWithTailwind.showLoading();
        },
        timer: 300,
    }).then(async () => {
        try {
            const { data } = await axios.get(route("ventas.comprobar-existencia-juego"), {
                params: { 
                    juego: form.juego, 
                    tipo_cuenta: form.tipo_cuenta, 
                    consola: form.consola 
                },
            });

            if (Object.keys(data).length !== 0) {
                swalWithTailwind.fire({
                    title: 'El juego está disponible en el inventario',
                    icon: "success",
                });
            } else {
                swalWithTailwind.fire({
                    title: 'El juego no está disponible en el inventario',
                    icon: "error",
                });
            }
        } catch (error) {
            console.error("Error verificando inventario:", error);
        }
    });
};

</script>

<template>
    <Head title="Venta de Juego" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">💸 Venta de Juego</h2>
        </template>

        <template #contenido-pagina>       
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Información para la venta del juego</h2>

                        <p class="mt-1 text-sm text-gray-600">
                            Datos para la venta del juego
                        </p>
                    </header>
                    <form @submit.prevent="submitForm" class="mt-6 space-y-6 w-full md:w-1/2">
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
                                    required
                                />

                                <ul
                                    v-if="form.juego !== '' && sugerencias.length !== 0 && juegoEncontrado == false"
                                    class="absolute bg-white border border-gray-300 w-full mt-1 rounded-md shadow-md z-50"
                                >
                                    <li
                                        v-for="Datosjuego in sugerencias"
                                        :key="Datosjuego.id"
                                        @click="seleccionarJuego(Datosjuego.juego)"
                                        class="p-2 cursor-pointer bg-white hover:bg-gray-100 text-black"
                                    >
                                        {{ Datosjuego.juego }}
                                    </li>
                                </ul>
                            </div>
                        
                            <InputError class="mt-2" :message="form.errors.juego" />
                        </div>

                        <div>
                            <InputLabel for="tipo_cuenta" value="Tipo de cuenta" />

                            <select
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                v-model="form.tipo_cuenta" 
                                id="tipo_cuenta"
                                required
                            >
                                <option value="" >Selecciona un tipo de cuenta</option>
                                <option value="Primaria">Primaria</option>
                                <option value="Secundaria">Secundaria</option>
                            </select>
                        
                            <InputError class="mt-2" :message="form.errors.tipo_cuenta" />
                        </div>

                        <div class="flex items-end gap-4">
                            <div class="w-full">
                                <InputLabel for="consola" value="Consola" />
                                <select
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full"
                                    v-model="form.consola" 
                                    id="consola"
                                    required
                                >
                                    <option value="" >Selecciona una consola</option>
                                    <option value="PS4">PS4</option>
                                    <option value="PS5">PS5</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.consola" />
                            </div>
                            <SecondaryButton v-if="form.juego && form.tipo_cuenta && form.consola"
                            @click="verificarInventario">
                                Verificar <i class="fas fa-clipboard-check ml-2"></i>
                            </SecondaryButton>
                        </div>

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

                        <div class="flex items-center gap-4 w-full">
                            <!-- Campo de precio -->
                            <div class="w-full">
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

                            <!-- Selector de moneda -->
                            <div class="w-full">
                                <InputLabel for="moneda" value="Moneda" />
                                <select
                                    id="moneda"
                                    v-model="form.moneda"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                >
                                    <option value="COP">COP</option>
                                    <option value="USD">USD</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.moneda" />
                            </div>
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

                        <div class="flex items-center gap-4">
                            <PrimaryButton :disabled="form.processing || juegoEncontrado == false">Generar Venta</PrimaryButton>
                        </div>
                    </form>
                </section>
                
            </div>

            <div v-if="juegoVendido" class="p-4 sm:p-8 bg-white shadow sm:rounded-lg" id="section-resultado-juego">
                <section>
                    <header class="items-center space-x-3">
                        <!-- Botón Copiar -->
                        <div class="relative flex items-center ml-3 mb-5">
                            <button 
                                @click="copiarCodigo"
                                class="bg-gray-600 hover:bg-gray-700 text-white font-semibold p-2 rounded-md shadow-md transition-all duration-300 active:scale-90 flex items-center justify-center"
                            >
                                <i class="fa-regular fa-copy text-sm"></i>
                            </button>

                            <Transition
                                enter-active-class="transition-opacity duration-300 ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition-opacity duration-300 ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p v-if="textoCopiado" class="text-sm text-gray-600 ml-2">Copiado.</p>
                            </Transition>
                        </div>

                        <!-- Contenido -->
                        <h2 ref="codigoRef" class="text-lg font-medium text-gray-900 ml-4">
                            ¡IMPORTANTE! Lee esto antes de continuar con los pasos 🛑 <br><br>
                            1️⃣ El código de verificación es de un solo uso. Utilízalo exclusivamente en la consola donde vas a descargar el juego. <br>
                            2️⃣ No inicies sesión como invitado - El juego no funcionará si lo haces. <br>
                            3️⃣ No modifiques los datos de la cuenta para evitar inconvenientes. <br><br>

                            {{ cuenta_juego.juego }} <br>
                            Cuenta: {{ cuenta_juego.tipo_cuenta }} {{ cuenta_juego.consola }} <br><br>

                            👤 Usuario: {{ cuenta_juego.correo }} <br>
                            🔒 Contraseña: {{ cuenta_juego.contrasena }} <br>
                            <span v-if="cuenta_juego.codigo && cuenta_juego.codigo.trim() !== ''">🔑 Código de verificación: {{ cuenta_juego.codigo }}</span> <br>
                        </h2>

                        <p v-if="cuenta_juego.ultimo_codigo == 1" class="mt-5 text-sm text-gray-600">
                            Ya no quedan más códigos de verificación, notificar al administrador 🚨
                        </p>
                    </header>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>