<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import FormSelect from '@/Pages/Pedidos/Components/FormSelect.vue';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';
import SectionCard from '@/Pages/Pedidos/Components/SectionCard.vue';
import { copiarTexto } from '@/Utils/portapapeles';

const props = defineProps({
    correos: Object,
    filtros: Object,
    resumen: Object,
    puede_ver_datos_completos: Boolean,
});

const form = reactive({
    search: props.filtros.search ?? '',
    disponibilidad: props.filtros.disponibilidad ?? '',
    orden: props.filtros.orden ?? 'recientes',
});
const sugerencias = ref([]);
const buscandoSugerencias = ref(false);
const contrasenasVisibles = ref(new Set());
const copiado = ref(null);

const params = () => Object.fromEntries(Object.entries(form).filter(([, valor]) => valor !== '' && valor !== null));
let temporizadorConsulta;
const consultar = (inmediato = false) => {
    clearTimeout(temporizadorConsulta);
    temporizadorConsulta = setTimeout(() => router.get(route('consultar-inventario'), params(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    }), inmediato ? 0 : 350);
};
const limpiar = () => {
    Object.assign(form, { search: '', disponibilidad: '', orden: 'recientes' });
    sugerencias.value = [];
    consultar(true);
};
let temporizadorSugerencias;
const buscarSugerencias = () => {
    clearTimeout(temporizadorSugerencias);
    consultar();
    if (form.search.trim().length < 2) {
        sugerencias.value = [];
        return;
    }
    temporizadorSugerencias = setTimeout(async () => {
        buscandoSugerencias.value = true;
        try {
            const { data } = await axios.get(route('buscar-juegos'), { params: { juego: form.search } });
            sugerencias.value = data.slice(0, 8);
        } finally {
            buscandoSugerencias.value = false;
        }
    }, 250);
};
const seleccionarJuego = (juego) => {
    form.search = juego;
    sugerencias.value = [];
    consultar(true);
};
const alternarContrasena = (id) => {
    const nuevas = new Set(contrasenasVisibles.value);
    nuevas.has(id) ? nuevas.delete(id) : nuevas.add(id);
    contrasenasVisibles.value = nuevas;
};
const copiar = async (texto, clave) => {
    await copiarTexto(texto);
    copiado.value = clave;
    setTimeout(() => { if (copiado.value === clave) copiado.value = null; }, 1400);
};
const moneda = (valor) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(valor || 0));
const totalCuposPrimarios = computed(() => props.resumen.cupos_primaria_ps4 + props.resumen.cupos_primaria_ps5);
const filaDesde = computed(() => props.correos.from ?? 0);
</script>

<template>
    <Head title="Consultar inventario" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">🧰 Consultar inventario</h2>
                <p class="mt-1 text-sm font-normal text-gray-500">Consulta cuentas disponibles y distingue claramente ocupación de cupos libres.</p>
            </div>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">
                <SectionCard title="Buscar inventario" description="Filtra por título, disponibilidad o antigüedad de la cuenta." icon="fa-solid fa-filter text-red-500">
                    <template #actions>
                        <div class="flex flex-wrap gap-2">
                            <Link :href="route('inventario.analisis.index')" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-100"><i class="fa-solid fa-chart-line mr-2"></i>Demanda</Link>
                            <Link :href="route('inventario.rotacion.index')" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-700 hover:bg-amber-100"><i class="fa-solid fa-rotate mr-2"></i>Rotación</Link>
                        </div>
                    </template>
                    <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="consultar(true)">
                        <div class="relative">
                            <InputLabel for="inventario-juego" value="Videojuego" />
                            <TextInput id="inventario-juego" v-model="form.search" class="mt-1 block w-full" autocomplete="off" placeholder="Buscar título..." @input="buscarSugerencias" />
                            <ul v-if="sugerencias.length" class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white py-1 shadow-xl">
                                <li v-for="item in sugerencias" :key="item.id"><button type="button" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm hover:bg-red-50" @click="seleccionarJuego(item.juego)"><i class="fa-solid fa-gamepad text-red-500"></i><span>{{ item.juego }}</span></button></li>
                            </ul>
                            <span v-if="buscandoSugerencias" class="absolute right-3 top-10 text-gray-400"><i class="fa-solid fa-spinner fa-spin"></i></span>
                        </div>
                        <FormSelect id="inventario-disponibilidad" v-model="form.disponibilidad" label="Disponibilidad" icon="fa-solid fa-box-open" @change="consultar(true)">
                            <option value="">Todas las cuentas</option><option value="con_cupos">Con algún cupo</option><option value="ps4">Con primaria PS4</option><option value="ps5">Con primaria PS5</option><option value="secundaria">Con secundaria</option><option value="sin_cupos">Sin cupos libres</option>
                        </FormSelect>
                        <FormSelect id="inventario-orden" v-model="form.orden" label="Ordenar por" icon="fa-solid fa-arrow-down-wide-short" @change="consultar(true)">
                            <option value="recientes">Más recientes</option><option value="antiguos">Más antiguas</option><option value="juego">Nombre del juego</option><option value="costo">Mayor costo</option>
                        </FormSelect>
                        <div class="flex flex-col gap-3 sm:flex-row lg:col-span-3">
                            <PrimaryButton type="submit" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-magnifying-glass mr-2"></i>Consultar</PrimaryButton>
                            <SecondaryButton type="button" class="w-full justify-center sm:w-auto" @click="limpiar"><i class="fa-solid fa-eraser mr-2"></i>Limpiar</SecondaryButton>
                        </div>
                    </form>
                </SectionCard>

                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-xl border border-gray-100 bg-white p-5 shadow"><p class="text-sm text-gray-500">Cuentas disponibles</p><p class="mt-1 text-2xl font-black text-gray-900">{{ resumen.cuentas }}</p><p class="mt-1 text-xs text-gray-400">{{ resumen.juegos }} títulos diferentes</p></article>
                    <article class="rounded-xl border border-blue-100 bg-blue-50 p-5"><p class="text-sm font-semibold text-blue-700">Cupos primarios</p><p class="mt-1 text-2xl font-black text-blue-900">{{ totalCuposPrimarios }}</p><p class="mt-1 text-xs text-blue-600">PS4 {{ resumen.cupos_primaria_ps4 }} · PS5 {{ resumen.cupos_primaria_ps5 }}</p></article>
                    <article class="rounded-xl border border-green-100 bg-green-50 p-5"><p class="text-sm font-semibold text-green-700">Cupos secundarios</p><p class="mt-1 text-2xl font-black text-green-900">{{ resumen.cupos_secundaria }}</p><p class="mt-1 text-xs text-green-600">Cuentas con secundaria libre</p></article>
                    <article class="rounded-xl border border-amber-100 bg-amber-50 p-5"><p class="text-sm font-semibold text-amber-700">Valor del inventario</p><p class="mt-1 text-2xl font-black text-amber-900">{{ moneda(resumen.valor_inventario) }}</p><p class="mt-1 text-xs text-amber-600">Según el costo registrado</p></article>
                </section>

                <SectionCard title="Cuentas del inventario" :description="`${correos.total} cuentas coinciden con los filtros aplicados.`" icon="fa-solid fa-boxes-stacked text-red-500">
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-[1050px] w-full text-sm">
                            <thead class="bg-gray-100 text-left text-xs uppercase tracking-wide text-gray-600"><tr>
                                <th class="px-4 py-3">#</th><th class="px-4 py-3">Juego</th><th v-if="puede_ver_datos_completos" class="px-4 py-3">Cuenta</th><th class="px-4 py-3">Primarias disponibles</th><th class="px-4 py-3">Secundaria</th><th class="px-4 py-3">Costo</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(correo, indice) in correos.data" :key="correo.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3"><span class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-gray-100 px-2 font-bold text-gray-700">{{ filaDesde + indice }}</span></td>
                                    <td class="px-4 py-3"><div class="flex min-w-[260px] items-center gap-3"><img v-if="correo.imagen" :src="correo.imagen" class="h-12 w-12 rounded-lg object-cover" /><div v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600"><i class="fa-solid fa-gamepad"></i></div><div><p class="font-bold text-gray-900">{{ correo.juego }}</p><p class="text-xs text-gray-400">Cuenta #{{ correo.id }}</p></div></div></td>
                                    <td v-if="puede_ver_datos_completos" class="px-4 py-3"><div class="min-w-[260px] space-y-2 rounded-xl bg-gray-50 p-3"><div class="flex items-center justify-between gap-2"><span class="truncate">{{ correo.correo }}</span><button type="button" class="text-gray-400 hover:text-red-600" @click="copiar(correo.correo, `correo-${correo.id}`)"><i :class="copiado === `correo-${correo.id}` ? 'fa-check text-green-600' : 'fa-copy'" class="fa-solid"></i></button></div><div class="flex items-center justify-between gap-2"><span class="font-mono">{{ contrasenasVisibles.has(correo.id) ? correo.contrasena : '••••••••••' }}</span><div class="flex gap-2"><button type="button" class="text-gray-400 hover:text-gray-700" @click="alternarContrasena(correo.id)"><i :class="contrasenasVisibles.has(correo.id) ? 'fa-eye-slash' : 'fa-eye'" class="fa-solid"></i></button><button type="button" class="text-gray-400 hover:text-red-600" @click="copiar(correo.contrasena, `clave-${correo.id}`)"><i :class="copiado === `clave-${correo.id}` ? 'fa-check text-green-600' : 'fa-copy'" class="fa-solid"></i></button></div></div></div></td>
                                    <td class="px-4 py-3"><div class="flex gap-2"><span :class="correo.cupos_primaria_ps4 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-500'" class="rounded-lg px-3 py-1.5 font-bold">PS4 {{ correo.cupos_primaria_ps4 }}</span><span :class="correo.cupos_primaria_ps5 ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-500'" class="rounded-lg px-3 py-1.5 font-bold">PS5 {{ correo.cupos_primaria_ps5 }}</span></div><p class="mt-1 text-xs text-gray-400">Ocupados: PS4 {{ correo.primaria_ps4 }}/2 · PS5 {{ correo.primaria_ps5 }}/2</p></td>
                                    <td class="px-4 py-3"><span :class="correo.cupo_secundaria ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'" class="rounded-lg px-3 py-1.5 font-bold">{{ correo.cupo_secundaria ? 'Disponible' : 'Ocupada' }}</span></td>
                                    <td class="px-4 py-3 font-bold text-gray-900">{{ correo.precio_cop ? moneda(correo.precio_cop) : 'Sin costo' }}</td>
                                </tr>
                                <tr v-if="correos.data.length === 0"><td :colspan="puede_ver_datos_completos ? 6 : 5" class="px-6 py-16 text-center text-gray-500"><i class="fa-solid fa-box-open mb-3 block text-4xl text-gray-300"></i><p class="font-bold text-gray-700">No hay cuentas con estos filtros</p><p class="mt-1 text-sm">Prueba otra disponibilidad o limpia la búsqueda.</p></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="correos.links" />
                </SectionCard>
            </div>
        </template>
    </LayoutPageHeader>
</template>
