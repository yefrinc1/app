<script setup>
import { computed } from 'vue';
import { Link, Head, useForm } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    correos_madres: Object,
    mensaje_correo_creado: { type: String, default: '' },
});

const form = useForm({});
const usarMadreForm = useForm({});

if (props.mensaje_correo_creado) {
    Swal.fire({
        title: props.mensaje_correo_creado,
        icon: 'success',
        confirmButtonColor: '#16a34a',
    });
}

const hijos = computed(() => [...(props.correos_madres.correos_juegos ?? [])].sort(
    (a, b) => Number(Boolean(b.es_cuenta_madre)) - Number(Boolean(a.es_cuenta_madre)),
));
const hijosReales = computed(() => hijos.value.filter((hijo) => !hijo.es_cuenta_madre));
const usaMadreComoHijo = computed(() => hijos.value.some((hijo) => hijo.es_cuenta_madre));
const historial = computed(() => props.correos_madres.historial_hijos ?? []);

const usarComoHijo = () => {
    Swal.fire({
        title: '¿Usar esta madre como hijo?',
        text: 'Se crea una cuenta jugable con el mismo correo y contraseña. No se borra ningún hijo. Luego asígnale el juego para venderla y enviarla por el portal.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, usar como hijo',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#16a34a',
        reverseButtons: true,
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        usarMadreForm.post(route('correo-madre.usar-como-hijo', props.correos_madres.id), {
            preserveScroll: true,
            onError: (errores) => {
                Swal.fire({
                    title: Object.values(errores).flat().filter(Boolean)[0] || 'No se pudo registrar la madre como hijo',
                    icon: 'error',
                    confirmButtonColor: '#dc2626',
                });
            },
        });
    });
};

const quitarHijo = (hijo) => {
    Swal.fire({
        title: hijo.es_cuenta_madre ? '¿Dejar de usar la madre como hijo?' : '¿Seguro que deseas quitar este hijo?',
        text: hijo.es_cuenta_madre
            ? 'La madre sigue existiendo. Solo se quita la cuenta jugable.'
            : 'Quedará en el historial de esta madre.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        reverseButtons: true,
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        form.get(route('correo-madre.quitarHijo', hijo.id), {
            onSuccess: () => {
                Swal.fire({
                    title: hijo.es_cuenta_madre ? 'La madre ya no se usa como hijo' : 'Hijo quitado. Quedó en el historial.',
                    icon: 'success',
                    confirmButtonColor: '#16a34a',
                });
            },
            onError: () => {
                Swal.fire({
                    title: 'Hubo un error al quitar el hijo',
                    icon: 'error',
                    confirmButtonColor: '#dc2626',
                });
            },
        });
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

</script>

<template>
    <Head :title="`Madre ${correos_madres.correo}`" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">👤 Correo madre</h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6 p-4 sm:p-8">
                <section class="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-md">
                    <div class="h-1 bg-gradient-to-r from-red-600 via-red-500 to-amber-500"></div>
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-red-600">Madre</p>
                                <div class="mt-3 flex items-center gap-2 text-lg font-semibold text-gray-900">
                                    <i class="fa-solid fa-envelope text-red-500"></i>
                                    <span class="break-all">{{ correos_madres.correo }}</span>
                                </div>
                                <div class="mt-2 flex items-center gap-2 text-base text-gray-700">
                                    <i class="fa-solid fa-key text-amber-500"></i>
                                    <span>{{ correos_madres.contrasena }}</span>
                                </div>
                            </div>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold',
                                    correos_madres.disponible == 1
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-red-100 text-red-800',
                                ]"
                            >
                                <i :class="correos_madres.disponible == 1 ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark'"></i>
                                {{ correos_madres.disponible == 1 ? 'Disponible' : 'Completa' }}
                            </span>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-3 py-1 text-xs font-bold text-red-800">
                                <i class="fa-solid fa-users"></i>
                                {{ hijosReales.length }}/6 hijos
                            </span>
                            <span
                                v-if="usaMadreComoHijo"
                                class="inline-flex items-center gap-1 rounded-lg bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900"
                            >
                                <i class="fa-solid fa-crown"></i>
                                Madre como hijo
                            </span>
                            <PrimaryButton
                                v-else
                                type="button"
                                class="h-8 px-3 text-xs"
                                :disabled="usarMadreForm.processing"
                                @click="usarComoHijo"
                            >
                                <i class="fa-solid fa-user-plus mr-2"></i>
                                Usar madre como hijo
                            </PrimaryButton>
                            <span class="rounded-lg bg-yellow-100 px-3 py-1 text-xs font-bold text-yellow-900">
                                USD {{ correos_madres.saldo_usd }}
                            </span>
                            <span class="rounded-lg bg-green-100 px-3 py-1 text-xs font-bold text-green-900">
                                COP ${{ Number(correos_madres.saldo_cop).toLocaleString('es-CO') }}
                            </span>
                            <span class="rounded-lg bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                                Nacimiento {{ formatearFecha(correos_madres.fecha_nacimiento) }}
                            </span>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl bg-white p-4 shadow-md sm:p-6">
                    <header class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Hijos</h3>
                        <p class="mt-1 text-sm text-gray-600">Hasta 6 hijos más la madre si la usas como cuenta jugable.</p>
                    </header>

                    <div v-if="hijos.length" class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gradient-to-r from-red-50 via-white to-amber-50 text-left text-gray-600">
                                    <th class="px-4 py-3 font-semibold">Correo</th>
                                    <th class="px-4 py-3 font-semibold">Contraseña</th>
                                    <th class="px-4 py-3 font-semibold">Juego</th>
                                    <th class="px-4 py-3 font-semibold">Nacimiento</th>
                                    <th class="px-4 py-3 text-center font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="correo in hijos"
                                    :key="correo.id"
                                    class="border-t border-gray-100 transition hover:bg-amber-50/40"
                                >
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-2 font-medium text-gray-800">
                                            <i class="fa-solid fa-envelope text-red-400"></i>
                                            <span class="break-all">{{ correo.correo }}</span>
                                            <span
                                                v-if="correo.es_cuenta_madre"
                                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-900"
                                            >
                                                <i class="fa-solid fa-crown"></i>
                                                Madre
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2 text-gray-700">
                                            <i class="fa-solid fa-key text-amber-500"></i>
                                            <span>{{ correo.contrasena }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            v-if="correo.juego"
                                            class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800"
                                        >
                                            <i class="fa-solid fa-gamepad"></i>
                                            {{ correo.juego }}
                                        </span>
                                        <span v-else class="text-gray-400">
                                            Sin juego
                                            <Link
                                                :href="route('correo-juegos.index', { search: correo.correo })"
                                                class="ml-2 text-xs font-semibold text-red-700 hover:underline"
                                            >Asignar</Link>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ formatearFecha(correo.fecha_nacimiento) }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-center">
                                            <DangerButton class="h-8 w-8" @click="quitarHijo(correo)">
                                                <i class="fa-solid fa-trash"></i>
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                        No tiene hijos registrados.
                    </p>
                </section>

                <section class="rounded-2xl bg-white p-4 shadow-md sm:p-6">
                    <header class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Historial de hijos</h3>
                        <p class="mt-1 text-sm text-gray-600">Quién se quitó o eliminó y de esta madre.</p>
                    </header>

                    <div v-if="historial.length" class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left text-gray-600">
                                    <th class="px-4 py-3 font-semibold">Correo</th>
                                    <th class="px-4 py-3 font-semibold">Juego</th>
                                    <th class="px-4 py-3 font-semibold">Acción</th>
                                    <th class="px-4 py-3 font-semibold">Quién</th>
                                    <th class="px-4 py-3 font-semibold">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="evento in historial" :key="evento.id" class="border-t border-gray-100">
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="break-all font-medium text-gray-800">{{ evento.correo }}</span>
                                            <span
                                                v-if="evento.es_cuenta_madre"
                                                class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-900"
                                            >Madre</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">Contraseña: {{ evento.contrasena || '—' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ evento.juego || 'Sin juego' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
                                            {{ evento.accion === 'eliminado' ? 'Eliminado' : 'Quitado' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ evento.usuario?.name || 'Sistema' }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ formatearFecha(evento.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                        Aún no se ha quitado ningún hijo de esta madre.
                    </p>
                </section>

                <div class="flex flex-wrap justify-center gap-3">
                    <Link :href="route('correo-madre.index')">
                        <SecondaryButton>Ver madres</SecondaryButton>
                    </Link>
                    <Link :href="route('correo-juegos.creacion-completa')">
                        <PrimaryButton>Creación completa</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>
    </LayoutPageHeader>
</template>
