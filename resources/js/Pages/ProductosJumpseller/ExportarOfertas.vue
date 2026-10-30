<script setup>
import { Head } from '@inertiajs/vue3';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    total: Number,
    productos: Array,
    consultado_at: String,
    error: String,
});

const fechaConsulta = (valor) => valor
    ? new Date(valor).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' })
    : '';

const nombreProducto = (item) => item?.product?.name || 'Producto sin nombre';
const idProducto = (item) => item?.product?.id || '—';
const imagenProducto = (item) => item?.product?.images?.[0]?.url || null;

const descargarJson = () => {
    const payload = {
        consultado_at: props.consultado_at,
        total: props.total,
        productos: props.productos,
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    const fecha = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');

    enlace.href = url;
    enlace.download = `ofertas-jumpseller-${fecha}.json`;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <Head title="Exportar ofertas JSON" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Exportar ofertas JSON
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Resultado de Jumpseller</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Consulta todas las ofertas de la categoría de Jumpseller y descarga el resultado en JSON.
                        </p>
                        <p v-if="consultado_at" class="mt-2 text-xs text-gray-400">
                            Consultado el {{ fechaConsulta(consultado_at) }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3">
                            <p class="text-sm text-gray-500">Productos en oferta</p>
                            <p class="text-2xl font-bold text-gray-900">{{ total }}</p>
                        </div>

                        <PrimaryButton
                            type="button"
                            :disabled="!productos.length"
                            class="justify-center"
                            @click="descargarJson"
                        >
                            <i class="fa-solid fa-download mr-2"></i>
                            Descargar JSON
                        </PrimaryButton>
                    </div>
                </section>
            </div>

            <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <div v-else class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div v-if="productos.length" class="overflow-x-auto rounded-lg shadow">
                    <table class="min-w-full border border-gray-200">
                        <thead>
                            <tr class="border-b bg-gray-100">
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Producto</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">ID</th>
                                <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700">Variantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in productos" :key="idProducto(item) + '-' + index" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="imagenProducto(item)"
                                            :src="imagenProducto(item)"
                                            :alt="nombreProducto(item)"
                                            class="h-12 w-12 rounded-lg object-cover"
                                        />
                                        <div v-else class="flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100 text-lg">🎮</div>
                                        <span class="font-medium text-gray-900">{{ nombreProducto(item) }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ idProducto(item) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ item?.product?.variants?.length || 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="text-sm text-gray-500">
                    Jumpseller no devolvió productos en oferta.
                </p>
            </div>
        </template>
    </LayoutPageHeader>
</template>
