<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { confirmarOperacion, mostrarErrores } from '@/Utils/alertas';
import Swal from 'sweetalert2';

const props = defineProps({
    madres: { type: Array, default: () => [] },
    correosGlobales: { type: Array, default: () => [] },
    idUltimaMadre: { type: Number, default: 0 },
    mensaje: { type: String, default: '' },
});

const selectClass = `
    appearance-none mt-1 block w-full rounded-xl border border-red-500/30
    bg-gradient-to-r from-white via-red-50 to-amber-50
    px-4 py-2.5 pr-10 font-semibold text-gray-800 shadow-md
    transition-all duration-300 hover:border-red-500/50
    focus:outline-none focus:border-amber-500 focus:ring-4 focus:ring-red-500/20
    focus:shadow-lg focus:shadow-red-500/20 disabled:cursor-not-allowed disabled:opacity-60
`;

const form = useForm({
    modo: 'existente',
    madre_id: '',
    madre: {
        id_correo_globales: '',
        contrasena: props.idUltimaMadre ? `madre${props.idUltimaMadre + 1}oficial` : 'madreoficial1',
        saldo_usd: 0,
        saldo_cop: 0,
        fecha_nacimiento: '2001-01-01',
        codigo: '',
    },
    hijos: [],
});

const PALABRAS_CONTRASENA = [
    'paloma', 'juego', 'casa', 'moto', 'veloz', 'luna', 'fuego', 'gato',
    'nube', 'rana', 'sol', 'pez', 'auto', 'lobo', 'mar', 'oro',
    'rey', 'oso', 'nieve', 'tigre', 'perro', 'cielo', 'bosque', 'playa',
    'duna', 'faro', 'tren', 'bici', 'fresa', 'pera', 'uva', 'rosa',
    'mago', 'ninja', 'zorro', 'puma', 'brisa', 'coral', 'radio', 'piano',
    'salsa', 'cohete', 'limon', 'mango', 'barco', 'rueda', 'llama', 'nido',
];

const busquedaMadre = ref('');
const resultadosMadres = ref([...props.madres]);
const mostrandoResultados = ref(false);
const madreSeleccionada = ref(null);
const fechaComun = ref('2009-01-01');
let busquedaTimer = null;

const hijosExistentes = computed(() => madreSeleccionada.value?.hijos ?? []);
const cuposLibres = computed(() => Math.max(0, 6 - hijosExistentes.value.length));
const hijosACrear = computed(() => form.hijos.filter((hijo) => hijo.crear && hijo.id_correo_globales));
const totalOcupados = computed(() => hijosExistentes.value.length + hijosACrear.value.length);

const valorPorUsd = computed(() => {
    if (form.madre.saldo_cop && form.madre.saldo_usd && Number(form.madre.saldo_usd) !== 0) {
        return (form.madre.saldo_cop / form.madre.saldo_usd).toFixed(2);
    }
    return null;
});

if (props.mensaje) {
    Swal.fire({
        title: props.mensaje,
        icon: 'success',
        confirmButtonColor: '#16a34a',
    });
}

onMounted(() => {
    if (form.modo === 'nueva') {
        reconstruirCupos();
    }
});

watch(() => form.modo, (modo) => {
    form.clearErrors();
    busquedaMadre.value = '';
    resultadosMadres.value = [...props.madres];
    if (modo === 'nueva') {
        madreSeleccionada.value = null;
        form.madre_id = '';
        if (!form.madre.id_correo_globales && props.correosGlobales.length > 0) {
            form.madre.id_correo_globales = props.correosGlobales[0].id;
        }
        reconstruirCupos();
        return;
    }
    reconstruirCupos();
});

watch(() => form.madre.id_correo_globales, () => {
    if (form.modo !== 'nueva') {
        return;
    }
    reasignarCorreosColisionados();
});

const idsExcluidos = () => {
    const ids = new Set();
    if (form.modo === 'nueva' && form.madre.id_correo_globales) {
        ids.add(Number(form.madre.id_correo_globales));
    }
    return ids;
};

const palabraAleatoria = (evitar = '') => {
    const opciones = PALABRAS_CONTRASENA.filter((palabra) => palabra !== evitar);
    return opciones[Math.floor(Math.random() * opciones.length)];
};

const generarContrasena = (usadas = new Set()) => {
    for (let intento = 0; intento < 40; intento += 1) {
        const primera = palabraAleatoria();
        const segunda = palabraAleatoria(primera);
        const numeros = String(10 + Math.floor(Math.random() * 90));
        const contrasena = `${primera}${segunda}${numeros}`;
        if (!usadas.has(contrasena)) {
            return contrasena;
        }
    }

    return `${palabraAleatoria()}${palabraAleatoria()}${String(10 + Math.floor(Math.random() * 90))}`;
};

const sumarDias = (iso, dias) => {
    const [anio, mes, dia] = iso.split('-').map(Number);
    const fecha = new Date(anio, mes - 1, dia + dias);
    const mm = String(fecha.getMonth() + 1).padStart(2, '0');
    const dd = String(fecha.getDate()).padStart(2, '0');

    return `${fecha.getFullYear()}-${mm}-${dd}`;
};

const sugerirGlobales = (cantidad, excluirIds) => {
    const disponibles = props.correosGlobales.filter((global) => !excluirIds.has(Number(global.id)));
    const porPrincipal = new Map();

    disponibles.forEach((global) => {
        const principal = global.id_correo_principal == null ? Number.POSITIVE_INFINITY : Number(global.id_correo_principal);
        if (!porPrincipal.has(principal)) {
            porPrincipal.set(principal, []);
        }
        porPrincipal.get(principal).push(global);
    });

    const principales = [...porPrincipal.keys()].sort((a, b) => a - b);
    const sugeridos = [];
    const usados = new Set();

    principales.forEach((principal) => {
        porPrincipal.get(principal).slice(0, 2).forEach((global) => {
            if (sugeridos.length >= cantidad) {
                return;
            }
            sugeridos.push(global);
            usados.add(Number(global.id));
        });
    });

    disponibles.forEach((global) => {
        if (sugeridos.length >= cantidad || usados.has(Number(global.id))) {
            return;
        }
        sugeridos.push(global);
        usados.add(Number(global.id));
    });

    return sugeridos;
};

const reconstruirCupos = () => {
    const excluir = idsExcluidos();
    const sugeridos = sugerirGlobales(cuposLibres.value, excluir);
    const usadas = new Set();

    form.hijos = Array.from({ length: cuposLibres.value }, (_, index) => {
        const sugerido = sugeridos[index];
        const contrasena = generarContrasena(usadas);
        usadas.add(contrasena);

        return {
            crear: true,
            id_correo_globales: sugerido?.id ?? '',
            contrasena,
            fecha_nacimiento: sumarDias(fechaComun.value || '2009-01-01', index),
        };
    });
};

const reasignarCorreosColisionados = () => {
    const sugeridos = sugerirGlobales(form.hijos.length, idsExcluidos());
    form.hijos.forEach((hijo, index) => {
        hijo.id_correo_globales = sugeridos[index]?.id ?? '';
    });
};

const globalesParaHijo = (index) => {
    const usados = idsExcluidos();
    form.hijos.forEach((hijo, i) => {
        if (i !== index && hijo.id_correo_globales) {
            usados.add(Number(hijo.id_correo_globales));
        }
    });
    const actual = Number(form.hijos[index]?.id_correo_globales);
    return props.correosGlobales.filter((global) => !usados.has(Number(global.id)) || Number(global.id) === actual);
};

const seleccionarMadre = (madre) => {
    madreSeleccionada.value = madre;
    form.madre_id = madre.id;
    busquedaMadre.value = madre.correo;
    mostrandoResultados.value = false;
    reconstruirCupos();
};

const limpiarMadre = () => {
    madreSeleccionada.value = null;
    form.madre_id = '';
    busquedaMadre.value = '';
    resultadosMadres.value = [...props.madres];
    form.hijos = [];
};

const buscarMadres = () => {
    clearTimeout(busquedaTimer);
    const query = busquedaMadre.value.trim();
    mostrandoResultados.value = true;
    busquedaTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route('correo-juegos.creacion-completa.madres'), {
                params: { q: query },
            });
            resultadosMadres.value = data;
        } catch {
            resultadosMadres.value = props.madres.filter((madre) =>
                madre.correo.toLowerCase().includes(query.toLowerCase())
            );
        }
    }, 250);
};

const generarContrasenasHijos = () => {
    const usadas = new Set();
    form.hijos.forEach((hijo) => {
        if (!hijo.crear) {
            return;
        }
        const contrasena = generarContrasena(usadas);
        usadas.add(contrasena);
        hijo.contrasena = contrasena;
    });
};

const aplicarFechaComun = () => {
    if (!fechaComun.value) {
        return;
    }
    let offset = 0;
    form.hijos.forEach((hijo) => {
        if (!hijo.crear) {
            return;
        }
        hijo.fecha_nacimiento = sumarDias(fechaComun.value, offset);
        offset += 1;
    });
};

const marcarTodos = (valor) => {
    form.hijos.forEach((hijo) => {
        hijo.crear = valor;
    });
};

const formatearFecha = (fecha) => {
    if (!fecha) {
        return '—';
    }
    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(fecha));
};

const errorHijo = (index, campo) => form.errors[`hijos.${index}.${campo}`];

const guardar = async () => {
    const payloadHijos = hijosACrear.value.map((hijo) => ({
        id_correo_globales: hijo.id_correo_globales,
        contrasena: hijo.contrasena,
        fecha_nacimiento: hijo.fecha_nacimiento,
    }));

    if (form.modo === 'existente' && !form.madre_id) {
        await Swal.fire({
            title: 'Elige una madre',
            text: 'Busca y selecciona una madre existente, o cambia a crear una nueva.',
            icon: 'warning',
            confirmButtonColor: '#d97706',
        });
        return;
    }

    if (form.modo === 'existente' && payloadHijos.length === 0) {
        await Swal.fire({
            title: 'Sin hijos nuevos',
            text: 'Marca al menos un cupo libre y completa correo, contraseña y fecha.',
            icon: 'warning',
            confirmButtonColor: '#d97706',
        });
        return;
    }

    const resumen = form.modo === 'nueva'
        ? `Se creará la madre y ${payloadHijos.length} hijo(s).`
        : `Se agregarán ${payloadHijos.length} hijo(s) a ${madreSeleccionada.value?.correo}.`;

    const confirmar = await confirmarOperacion({
        titulo: '¿Crear cuentas?',
        texto: resumen,
        confirmButtonText: 'Sí, crear',
    });

    if (!confirmar) {
        return;
    }

    form.transform((data) => ({
        ...data,
        hijos: payloadHijos,
    })).post(route('correo-juegos.creacion-completa.store'), {
        onError: (errors) => mostrarErrores(errors),
        onFinish: () => form.transform((data) => data),
    });
};
</script>

<template>
    <Head title="Creación completa" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl leading-tight text-gray-800">👥 Creación completa de cuentas</h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6 p-4 sm:p-8">
                <div class="rounded-2xl border border-amber-200 bg-gradient-to-r from-white via-red-50 to-amber-50 p-4 text-sm text-gray-700 shadow-sm">
                    Elige una madre existente o créala aquí. Luego completa los cupos libres hasta 6 hijos con correo sugerido, contraseña y fecha de nacimiento.
                </div>

                <div class="flex flex-wrap gap-3">
                    <PrimaryButton
                        v-if="form.modo === 'existente'"
                        type="button"
                    >
                        Madre existente
                    </PrimaryButton>
                    <SecondaryButton
                        v-else
                        type="button"
                        @click="form.modo = 'existente'"
                    >
                        Madre existente
                    </SecondaryButton>

                    <PrimaryButton
                        v-if="form.modo === 'nueva'"
                        type="button"
                    >
                        Nueva madre
                    </PrimaryButton>
                    <SecondaryButton
                        v-else
                        type="button"
                        @click="form.modo = 'nueva'"
                    >
                        Nueva madre
                    </SecondaryButton>
                </div>

                <form class="space-y-6" @submit.prevent="guardar">
                    <section v-if="form.modo === 'existente'" class="rounded-2xl bg-white p-4 shadow sm:p-6">
                        <header class="mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Madre</h3>
                            <p class="mt-1 text-sm text-gray-600">Busca por correo y elige la madre a completar.</p>
                        </header>

                        <div v-if="!madreSeleccionada" class="relative max-w-xl">
                            <InputLabel for="busqueda_madre" value="Buscar madre" />
                            <TextInput
                                id="busqueda_madre"
                                v-model="busquedaMadre"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="Escribe el correo de la madre..."
                                autocomplete="off"
                                @focus="mostrandoResultados = true"
                                @blur="mostrandoResultados = false"
                                @input="buscarMadres"
                            />
                            <InputError class="mt-2" :message="form.errors.madre_id" />

                            <div
                                v-if="mostrandoResultados"
                                class="absolute z-30 mt-1 max-h-72 w-full overflow-auto rounded-xl border border-red-100 bg-white shadow-lg"
                            >
                                <button
                                    v-for="madre in resultadosMadres"
                                    :key="madre.id"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 border-b border-gray-50 px-4 py-3 text-left last:border-0 hover:bg-amber-50"
                                    @mousedown.prevent="seleccionarMadre(madre)"
                                >
                                    <span class="font-medium text-gray-800">{{ madre.correo }}</span>
                                    <span class="shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">
                                        {{ madre.hijos_count }}/6 hijos
                                    </span>
                                </button>
                                <p v-if="!resultadosMadres.length" class="px-4 py-3 text-sm text-gray-500">
                                    No hay madres con cupos libres.
                                </p>
                            </div>
                        </div>

                        <div v-else class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-red-100 bg-red-50/40 p-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-red-600">Madre seleccionada</p>
                                <p class="mt-1 text-lg font-semibold text-gray-900">{{ madreSeleccionada.correo }}</p>
                                <p class="mt-1 text-sm text-gray-600">
                                    Contraseña: <span class="font-medium text-gray-800">{{ madreSeleccionada.contrasena }}</span>
                                    · {{ madreSeleccionada.hijos_count }}/6 hijos
                                    · {{ madreSeleccionada.saldo_usd }} USD
                                </p>
                                <p v-if="madreSeleccionada.usa_madre_como_hijo" class="mt-1 text-sm font-semibold text-amber-800">
                                    Esta madre ya se usa como hijo. No ocupa cupo de los 6.
                                </p>
                            </div>
                            <SecondaryButton type="button" @click="limpiarMadre">Cambiar madre</SecondaryButton>
                        </div>
                    </section>

                    <section v-else class="rounded-2xl bg-white p-4 shadow sm:p-6">
                        <header class="mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Nueva madre</h3>
                            <p class="mt-1 text-sm text-gray-600">Se crea junto con los hijos en el mismo envío.</p>
                        </header>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <InputLabel for="madre_correo" :value="$t('Email')" />
                                <div class="relative">
                                    <select id="madre_correo" v-model="form.madre.id_correo_globales" required :class="selectClass">
                                        <option value="">📧 Selecciona un correo global</option>
                                        <option v-for="opcion in correosGlobales" :key="opcion.id" :value="opcion.id">
                                            {{ opcion.correo }}
                                        </option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center pt-1 text-amber-500">
                                        <i class="fa-solid fa-envelope"></i>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form.errors['madre.id_correo_globales']" />
                            </div>

                            <div>
                                <InputLabel for="madre_contrasena" :value="$t('Password')" />
                                <TextInput id="madre_contrasena" v-model="form.madre.contrasena" type="text" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors['madre.contrasena']" />
                            </div>

                            <div>
                                <InputLabel for="madre_saldo_usd" value="Saldo USD" />
                                <TextInput id="madre_saldo_usd" v-model="form.madre.saldo_usd" type="number" step="0.01" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors['madre.saldo_usd']" />
                            </div>

                            <div>
                                <InputLabel for="madre_saldo_cop" value="Saldo COP" />
                                <div class="flex items-center gap-2">
                                    <TextInput id="madre_saldo_cop" v-model="form.madre.saldo_cop" type="number" class="mt-1 block w-full" required />
                                    <span v-if="valorPorUsd" class="whitespace-nowrap text-sm text-gray-600">{{ valorPorUsd }} ≈ 1 USD</span>
                                </div>
                                <InputError class="mt-2" :message="form.errors['madre.saldo_cop']" />
                            </div>

                            <div>
                                <InputLabel for="madre_fecha" value="Fecha nacimiento" />
                                <TextInput
                                    id="madre_fecha"
                                    v-model="form.madre.fecha_nacimiento"
                                    type="date"
                                    class="mt-1 block w-full"
                                    min="1900-01-01"
                                    max="2100-12-31"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors['madre.fecha_nacimiento']" />
                            </div>

                            <div>
                                <InputLabel for="madre_codigo" value="Códigos (opcional)" />
                                <textarea
                                    id="madre_codigo"
                                    v-model="form.madre.codigo"
                                    rows="3"
                                    placeholder="Uno por línea..."
                                    class="mt-1 block w-full resize-y rounded-xl border border-red-500/30 bg-gradient-to-r from-white via-red-50 to-amber-50 px-4 py-3 font-medium text-gray-800 shadow-md placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-red-500/20"
                                />
                            </div>
                        </div>
                    </section>

                    <section v-if="form.modo === 'nueva' || madreSeleccionada" class="rounded-2xl bg-white p-4 shadow sm:p-6">
                        <header class="mb-4 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Hijos ({{ totalOcupados }}/6)</h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    Los que ya existen se muestran bloqueados. Los cupos libres vienen con correo sugerido.
                                </p>
                            </div>
                            <p class="rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-800">
                                {{ hijosExistentes.length }} existentes · {{ cuposLibres }} por crear
                            </p>
                        </header>

                        <InputError class="mb-4" :message="form.errors.hijos" />

                        <div v-if="hijosExistentes.length" class="mb-6 overflow-x-auto rounded-xl border border-gray-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 text-left text-gray-600">
                                    <tr>
                                        <th class="px-4 py-2">Hijo</th>
                                        <th class="px-4 py-2">Correo</th>
                                        <th class="px-4 py-2">Contraseña</th>
                                        <th class="px-4 py-2">Nacimiento</th>
                                        <th class="px-4 py-2">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(hijo, index) in hijosExistentes" :key="hijo.id" class="border-t border-gray-100">
                                        <td class="px-4 py-2 font-semibold text-gray-700">{{ index + 1 }}</td>
                                        <td class="px-4 py-2">{{ hijo.correo }}</td>
                                        <td class="px-4 py-2">{{ hijo.contrasena }}</td>
                                        <td class="px-4 py-2">{{ formatearFecha(hijo.fecha_nacimiento) }}</td>
                                        <td class="px-4 py-2">
                                            <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">Ya creado</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="form.hijos.length" class="mb-5 grid gap-3 rounded-xl border border-dashed border-amber-200 bg-amber-50/40 p-4 md:grid-cols-3">
                            <div>
                                <InputLabel value="Contraseñas (2 palabras + 2 números)" />
                                <p class="mt-1 text-xs text-gray-500">Ejemplo: palomajuego26, juegocasa29</p>
                                <div class="mt-2">
                                    <PrimaryButton type="button" @click="generarContrasenasHijos">
                                        Generar contraseñas
                                    </PrimaryButton>
                                </div>
                            </div>
                            <div>
                                <InputLabel for="fecha_comun" value="Fecha inicial (cada hijo +1 día)" />
                                <div class="mt-1 flex items-center gap-2">
                                    <TextInput id="fecha_comun" v-model="fechaComun" type="date" class="block min-w-0 flex-1" min="1900-01-01" max="2100-12-31" />
                                    <PrimaryButton type="button" class="shrink-0" @click="aplicarFechaComun">Aplicar</PrimaryButton>
                                </div>
                            </div>
                            <div class="flex items-end gap-2">
                                <SecondaryButton type="button" @click="marcarTodos(true)">Marcar todos</SecondaryButton>
                                <SecondaryButton type="button" @click="marcarTodos(false)">Desmarcar</SecondaryButton>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <article
                                v-for="(hijo, index) in form.hijos"
                                :key="index"
                                class="rounded-xl border p-4"
                                :class="hijo.crear ? 'border-red-200 bg-white' : 'border-gray-200 bg-gray-50'"
                            >
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="font-semibold text-gray-800">
                                        Hijo {{ hijosExistentes.length + index + 1 }}
                                        <span class="ml-2 text-xs font-medium text-amber-700">sugerencia para crear</span>
                                    </h4>
                                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                        <input v-model="hijo.crear" type="checkbox" class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
                                        Crear este hijo
                                    </label>
                                </div>

                                <div class="grid gap-4 md:grid-cols-3" :class="{ 'pointer-events-none opacity-50': !hijo.crear }">
                                    <div>
                                        <InputLabel :for="`hijo_correo_${index}`" value="Correo" />
                                        <div class="relative">
                                            <select
                                                :id="`hijo_correo_${index}`"
                                                v-model="hijo.id_correo_globales"
                                                :required="hijo.crear"
                                                :disabled="!hijo.crear"
                                                :class="selectClass"
                                            >
                                                <option value="">🌐 Selecciona un correo global</option>
                                                <option
                                                    v-for="opcion in globalesParaHijo(index)"
                                                    :key="opcion.id"
                                                    :value="opcion.id"
                                                >
                                                    {{ opcion.correo }}
                                                </option>
                                            </select>
                                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center pt-1 text-amber-500">
                                                <i class="fa-solid fa-globe"></i>
                                            </div>
                                        </div>
                                        <InputError class="mt-2" :message="errorHijo(index, 'id_correo_globales')" />
                                    </div>

                                    <div>
                                        <InputLabel :for="`hijo_pass_${index}`" :value="$t('Password')" />
                                        <TextInput
                                            :id="`hijo_pass_${index}`"
                                            v-model="hijo.contrasena"
                                            type="text"
                                            class="mt-1 block w-full"
                                            :required="hijo.crear"
                                            :disabled="!hijo.crear"
                                            placeholder="Contraseña"
                                        />
                                        <InputError class="mt-2" :message="errorHijo(index, 'contrasena')" />
                                    </div>

                                    <div>
                                        <InputLabel :for="`hijo_fecha_${index}`" value="Fecha nacimiento" />
                                        <TextInput
                                            :id="`hijo_fecha_${index}`"
                                            v-model="hijo.fecha_nacimiento"
                                            type="date"
                                            class="mt-1 block w-full"
                                            min="1900-01-01"
                                            max="2100-12-31"
                                            :required="hijo.crear"
                                            :disabled="!hijo.crear"
                                        />
                                        <InputError class="mt-2" :message="errorHijo(index, 'fecha_nacimiento')" />
                                    </div>
                                </div>
                            </article>
                        </div>

                        <p v-if="!hijosExistentes.length && !form.hijos.length" class="text-sm text-gray-500">
                            No hay cupos para mostrar.
                        </p>
                        <p v-else-if="!form.hijos.length" class="mt-4 text-sm text-gray-500">
                            Esta madre ya tiene los 6 hijos.
                        </p>
                        <p v-else-if="correosGlobales.length < form.hijos.length" class="mt-4 text-sm text-amber-700">
                            Hay menos correos globales disponibles que cupos. Completa o desmarca los que falten.
                        </p>
                    </section>

                    <div class="flex items-center gap-3">
                        <PrimaryButton :disabled="form.processing">
                            {{ form.processing ? 'Creando…' : 'Crear cuentas' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </template>
    </LayoutPageHeader>
</template>
