<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import FormSelect from '@/Pages/Pedidos/Components/FormSelect.vue';
import Pagination from '@/Pages/Pedidos/Components/Pagination.vue';
import SectionCard from '@/Pages/Pedidos/Components/SectionCard.vue';
import ClienteFormModal from './Components/ClienteFormModal.vue';

const props = defineProps({ clientes: Object, filtros: Object, puedeEditar: Boolean });
const filtros = reactive({ buscar: props.filtros?.buscar ?? '', datos: props.filtros?.datos ?? '' });
const editando = ref(null);
const consultar = () => router.get(route('clientes.index'), filtros, { preserveState: true, replace: true });
const limpiar = () => { Object.assign(filtros, { buscar: '', datos: '' }); consultar(); };
const fechaHora = (valor) => valor ? new Date(valor).toLocaleString('es-CO', { dateStyle: 'medium', timeStyle: 'short' }) : 'Sin pedidos';
const incompleto = (cliente) => !cliente.nombre || (!cliente.email && !cliente.usuario);
const clienteActualizado = (cliente) => {
    const indice = props.clientes.data.findIndex((item) => Number(item.id) === Number(cliente.id));
    if (indice !== -1) Object.assign(props.clientes.data[indice], cliente);
};
</script>

<template>
    <Head title="👥 Clientes" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">👥 Clientes</h2>
                <Link :href="route('pedidos.create')"><PrimaryButton type="button" class="w-full justify-center sm:w-auto"><i class="fa-solid fa-cart-plus mr-2"></i>Crear pedido</PrimaryButton></Link>
            </div>
        </template>
        <template #contenido-pagina>
            <div class="space-y-6">
                <nav class="flex flex-wrap gap-2" aria-label="Clientes">
                    <PrimaryButton type="button" aria-current="page">Directorio de clientes</PrimaryButton>
                    <Link v-if="puedeEditar" :href="route('clientes.unificar.index')"><SecondaryButton type="button"><i class="fa-solid fa-link mr-2"></i>Unificar clientes</SecondaryButton></Link>
                </nav>
                <SectionCard title="Consultar clientes" description="Busca por nombre, teléfono, Instagram o correo." icon="fa-solid fa-magnifying-glass text-blue-500">
                    <template #actions><div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3"><p class="text-sm text-gray-500">Clientes encontrados</p><p class="text-lg font-bold text-gray-900">{{ clientes.total ?? clientes.data.length }}</p></div></template>
                    <form class="grid grid-cols-1 gap-4 md:grid-cols-3" @submit.prevent="consultar">
                        <div class="md:col-span-2"><InputLabel for="buscar-cliente" value="Buscar" /><TextInput id="buscar-cliente" v-model="filtros.buscar" class="mt-1 block w-full" placeholder="Nombre, teléfono, Instagram o correo..." /></div>
                        <FormSelect id="estado-datos" v-model="filtros.datos" label="Estado de los datos" icon="fa-solid fa-address-card"><option value="">Todos</option><option value="completos">Datos completos</option><option value="incompletos">Datos incompletos</option></FormSelect>
                        <div class="flex flex-col gap-3 sm:flex-row md:col-span-3"><PrimaryButton class="w-full justify-center sm:w-auto"><i class="fa-solid fa-magnifying-glass mr-2"></i>Consultar</PrimaryButton><SecondaryButton type="button" class="w-full justify-center sm:w-auto" @click="limpiar"><i class="fa-solid fa-eraser mr-2"></i>Limpiar</SecondaryButton></div>
                    </form>
                </SectionCard>

                <SectionCard title="Directorio de clientes" description="Actualiza los datos o consulta el historial de pedidos." icon="fa-solid fa-address-book text-red-500">
                    <div class="overflow-x-auto rounded-lg shadow">
                        <table class="min-w-full border border-gray-200 text-sm">
                            <thead><tr class="border-b bg-gray-100"><th class="px-4 py-3 text-left">Cliente</th><th class="px-4 py-3 text-left">Contacto</th><th class="px-4 py-3 text-left">Pedidos</th><th class="px-4 py-3 text-left">Último pedido</th><th class="px-4 py-3 text-left">Datos</th><th class="sticky right-0 z-10 bg-gray-100 px-4 py-3 text-center">Acciones</th></tr></thead>
                            <tbody>
                                <tr v-for="cliente in clientes.data" :key="cliente.id" class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3"><div class="min-w-[190px]"><p class="font-bold text-gray-900"><i class="fa-solid fa-user mr-2 text-blue-500"></i>{{ cliente.nombre || 'Sin nombre' }}</p><p class="mt-1 text-xs text-gray-500">Cliente #{{ cliente.id }}</p></div></td>
                                    <td class="px-4 py-3"><div class="min-w-[230px] space-y-1 text-gray-700"><p><i class="fa-solid fa-phone mr-2 text-green-600"></i>{{ cliente.telefono ? `+${cliente.codigo_pais || ''} ${cliente.telefono}` : 'Sin teléfono' }}</p><p><i class="fa-brands fa-instagram mr-2 text-pink-600"></i>{{ cliente.usuario ? `@${cliente.usuario}` : 'Sin Instagram' }}</p><p><i class="fa-solid fa-envelope mr-2 text-amber-600"></i>{{ cliente.email || 'Sin correo' }}</p></div></td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-lg bg-blue-100 px-3 py-1 font-bold text-blue-800">{{ cliente.pedidos_count }} pedido(s)</span><p class="mt-1 text-xs text-gray-500">{{ cliente.ventas_count }} venta(s)</p></td>
                                    <td class="px-4 py-3 text-gray-600"><span class="min-w-[170px] inline-block">{{ fechaHora(cliente.pedidos_max_created_at) }}</span></td>
                                    <td class="px-4 py-3"><span class="inline-flex rounded-lg px-3 py-1 text-xs font-bold" :class="incompleto(cliente) ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'"><i class="mr-2" :class="incompleto(cliente) ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-circle-check'"></i>{{ incompleto(cliente) ? 'Incompletos' : 'Completos' }}</span></td>
                                    <td class="sticky right-0 z-10 bg-white px-4 py-3"><div class="flex justify-center gap-2"><Link :href="route('clientes.show', cliente.id)"><SecondaryButton type="button" class="h-9 w-9 justify-center" title="Ver historial"><i class="fa-solid fa-eye"></i></SecondaryButton></Link><PrimaryButton v-if="puedeEditar" type="button" class="h-9 w-9 justify-center" title="Editar cliente" @click="editando = cliente"><i class="fa-solid fa-pen"></i></PrimaryButton></div></td>
                                </tr>
                                <tr v-if="!clientes.data.length"><td colspan="6" class="px-4 py-12 text-center"><i class="fa-solid fa-users-slash text-3xl text-gray-400"></i><h3 class="mt-3 font-bold text-gray-900">No se encontraron clientes</h3><p class="text-sm text-gray-500">Cambia los filtros e intenta nuevamente.</p></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="clientes.links" />
                </SectionCard>
            </div>
            <ClienteFormModal :show="Boolean(editando)" :cliente="editando" @close="editando = null" @updated="clienteActualizado" />
        </template>
    </LayoutPageHeader>
</template>
