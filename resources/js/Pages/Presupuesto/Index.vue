<script setup>
import { ref } from 'vue';
import { Link, Head, useForm, router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import Swal from 'sweetalert2';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const confirmingCrear = ref(false);
const confirmingEditar = ref(false);

const props = defineProps({
    presupuestos: Object,
    search: String,
});

const searchQuery = ref(props.search || '');

const form = useForm({
    id: 0,
    anio: new Date().getFullYear(),
    mes: '',
    ventas_objetivo: '',
    ingresos_objetivo: '',
    utilidad_objetivo: '',
    observaciones: '',
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true,
});

const meses = [
    { id: 1, nombre: 'Enero' },
    { id: 2, nombre: 'Febrero' },
    { id: 3, nombre: 'Marzo' },
    { id: 4, nombre: 'Abril' },
    { id: 5, nombre: 'Mayo' },
    { id: 6, nombre: 'Junio' },
    { id: 7, nombre: 'Julio' },
    { id: 8, nombre: 'Agosto' },
    { id: 9, nombre: 'Septiembre' },
    { id: 10, nombre: 'Octubre' },
    { id: 11, nombre: 'Noviembre' },
    { id: 12, nombre: 'Diciembre' },
];

const nombreMes = (mes) => {
    return meses.find(item => item.id == mes)?.nombre || '—';
};

const formatoCop = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
};

const buscarPresupuestos = () => {
    router.get(
        route('presupuestos.index'),
        searchQuery.value ? { search: searchQuery.value } : {},
        {
            preserveScroll: true,
            preserveState: true,
        }
    );
};

const crearModal = () => {
    form.reset();
    form.anio = new Date().getFullYear();
    confirmingCrear.value = true;
};

const editarModal = (presupuesto) => {
    form.id = presupuesto.id;
    form.anio = presupuesto.anio;
    form.mes = presupuesto.mes;
    form.ventas_objetivo = presupuesto.ventas_objetivo;
    form.ingresos_objetivo = presupuesto.ingresos_objetivo;
    form.utilidad_objetivo = presupuesto.utilidad_objetivo;
    form.observaciones = presupuesto.observaciones ?? '';
    confirmingEditar.value = true;
};

const crearPresupuesto = () => {
    form.post(route('presupuestos.store'), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: 'Presupuesto creado correctamente',
                icon: 'success',
            });
            closeModal();
        },
    });
};

const editarPresupuesto = () => {
    form.put(route('presupuestos.update', form.id), {
        preserveScroll: true,
        onSuccess: () => {
            swalWithTailwind.fire({
                title: 'Presupuesto actualizado correctamente',
                icon: 'success',
            });
            closeModal();
        },
    });
};

const closeModal = () => {
    confirmingCrear.value = false;
    confirmingEditar.value = false;
    form.reset();
};

const eliminarPresupuesto = (id) => {
    swalWithTailwind.fire({
        title: '¿Seguro que deseas eliminar este presupuesto?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Sí, eliminar',
        cancelButtonText: '<i class="fa-solid fa-ban"></i> Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route('presupuestos.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    swalWithTailwind.fire({
                        title: 'Presupuesto eliminado correctamente',
                        icon: 'success',
                    });
                },
                onError: () => {
                    swalWithTailwind.fire({
                        title: 'Hubo un error al eliminar el presupuesto',
                        icon: 'error',
                    });
                },
            });
        }
    });
};
</script>

<template>
    <Head title="Presupuestos" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📊 Presupuestos
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <div class="flex flex-col sm:flex-row justify-between gap-4 mb-4">
                        <div class="flex gap-2">
                            <TextInput
                                v-model="searchQuery"
                                placeholder="Buscar por año..."
                                class="w-full sm:w-64"
                                @keyup.enter="buscarPresupuestos"
                            />

                            <PrimaryButton @click="buscarPresupuestos">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </PrimaryButton>
                        </div>

                        <PrimaryButton @click="crearModal">
                            + Nuevo Presupuesto
                        </PrimaryButton>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Periodo</th>
                                    <th class="px-4 py-2 text-left">Ventas objetivo</th>
                                    <th class="px-4 py-2 text-left">Ingresos objetivo</th>
                                    <th class="px-4 py-2 text-left">Utilidad objetivo</th>
                                    <th class="px-4 py-2 text-left">Observaciones</th>
                                    <th class="px-4 py-2 text-center">Acciones</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="presupuesto in props.presupuestos.data"
                                    :key="presupuesto.id"
                                    class="border-b hover:bg-gray-100"
                                >
                                    <td class="px-4 py-2">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-bold text-gray-900">
                                                {{ nombreMes(presupuesto.mes) }}
                                            </span>
                                            <span class="bg-slate-100 text-slate-700 px-2 py-1 rounded-full text-sm font-semibold w-fit">
                                                {{ presupuesto.anio }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2">
                                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-lg text-sm font-bold">
                                            {{ presupuesto.ventas_objetivo }} ventas
                                        </span>
                                    </td>

                                    <td class="px-4 py-2">
                                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-lg text-sm font-bold">
                                            ${{ formatoCop(presupuesto.ingresos_objetivo) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2">
                                        <span class="bg-amber-100 text-amber-800 px-3 py-1 rounded-lg text-sm font-bold">
                                            ${{ formatoCop(presupuesto.utilidad_objetivo) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-2 max-w-xs truncate">
                                        {{ presupuesto.observaciones || '—' }}
                                    </td>

                                    <td class="px-4 py-2">
                                        <div class="flex justify-center gap-2">
                                            <SecondaryButton @click="editarModal(presupuesto)">
                                                <i class="fa-solid fa-pen"></i>
                                            </SecondaryButton>

                                            <DangerButton @click="eliminarPresupuesto(presupuesto.id)">
                                                <i class="fa-solid fa-trash"></i>
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="props.presupuestos.data.length === 0">
                                    <td class="px-4 py-2 text-center" colspan="6">
                                        No hay presupuestos registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <div class="flex flex-wrap justify-center gap-2">
                            <template v-for="(link, index) in props.presupuestos.links" :key="index">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    v-html="link.label"
                                    class="px-3 py-2 border rounded-md text-xs font-semibold transition ease-in-out duration-150"
                                    :class="link.active
                                        ? 'bg-gray-800 text-white border-gray-800'
                                        : 'bg-gray-200 text-gray-700 border-transparent hover:bg-gray-300'"
                                />
                            </template>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal Crear -->
            <Modal :show="confirmingCrear" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Crear Presupuesto
                    </h2>

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="anio_crear" value="Año" />
                            <TextInput
                                id="anio_crear"
                                type="number"
                                class="mt-1 block w-full"
                                v-model="form.anio"
                            />
                            <InputError class="mt-2" :message="form.errors.anio" />
                        </div>

                        <div>
                            <InputLabel for="mes_crear" value="Mes" />

                            <div class="relative">
                                <select
                                    id="mes_crear"
                                    v-model="form.mes"
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
                                    <option value="">Selecciona un mes</option>
                                    <option
                                        v-for="mes in meses"
                                        :key="mes.id"
                                        :value="mes.id"
                                    >
                                        {{ mes.nombre }}
                                    </option>
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

                            <InputError class="mt-2" :message="form.errors.mes" />
                        </div>
                    </div>

                    <div class="mt-6">
                        <InputLabel for="ventas_objetivo_crear" value="Ventas objetivo" />
                        <TextInput
                            id="ventas_objetivo_crear"
                            type="number"
                            class="mt-1 block w-full"
                            v-model="form.ventas_objetivo"
                        />
                        <InputError class="mt-2" :message="form.errors.ventas_objetivo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="ingresos_objetivo_crear" value="Ingresos objetivo" />
                        <TextInput
                            id="ingresos_objetivo_crear"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.ingresos_objetivo"
                        />
                        <InputError class="mt-2" :message="form.errors.ingresos_objetivo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="utilidad_objetivo_crear" value="Utilidad objetivo" />
                        <TextInput
                            id="utilidad_objetivo_crear"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.utilidad_objetivo"
                        />
                        <InputError class="mt-2" :message="form.errors.utilidad_objetivo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="observaciones_crear" value="Observaciones" />

                        <textarea
                            id="observaciones_crear"
                            ref="observaciones_crear"
                            rows="4"
                            v-model="form.observaciones"
                            placeholder="Ingresa las observaciones..."
                            class="
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
                                py-3
                                pr-10

                                text-gray-800
                                font-medium

                                placeholder:text-gray-400

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

                                resize-y
                            "
                        ></textarea>
                        <InputError class="mt-2" :message="form.errors.observaciones" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal">
                            {{ $t('Cancel') }}
                        </SecondaryButton>

                        <PrimaryButton
                            class="ms-3"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="crearPresupuesto"
                        >
                            Crear
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>

            <!-- Modal Editar -->
            <Modal :show="confirmingEditar" @close="closeModal">
                <div class="p-6">
                    <h2 class="text-lg font-medium text-gray-900">
                        Editar Presupuesto
                    </h2>

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="anio_editar" value="Año" />
                            <TextInput
                                id="anio_editar"
                                type="number"
                                class="mt-1 block w-full"
                                v-model="form.anio"
                            />
                            <InputError class="mt-2" :message="form.errors.anio" />
                        </div>

                        <div>
                            <InputLabel for="mes_editar" value="Mes" />

                            <div class="relative">
                                <select
                                    id="mes_editar"
                                    v-model="form.mes"
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
                                    <option value="">Selecciona un mes</option>
                                    <option
                                        v-for="mes in meses"
                                        :key="mes.id"
                                        :value="mes.id"
                                    >
                                        {{ mes.nombre }}
                                    </option>
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
                            <InputError class="mt-2" :message="form.errors.mes" />
                        </div>
                    </div>

                    <div class="mt-6">
                        <InputLabel for="ventas_objetivo_editar" value="Ventas objetivo" />
                        <TextInput
                            id="ventas_objetivo_editar"
                            type="number"
                            class="mt-1 block w-full"
                            v-model="form.ventas_objetivo"
                        />
                        <InputError class="mt-2" :message="form.errors.ventas_objetivo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="ingresos_objetivo_editar" value="Ingresos objetivo" />
                        <TextInput
                            id="ingresos_objetivo_editar"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.ingresos_objetivo"
                        />
                        <InputError class="mt-2" :message="form.errors.ingresos_objetivo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="utilidad_objetivo_editar" value="Utilidad objetivo" />
                        <TextInput
                            id="utilidad_objetivo_editar"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            v-model="form.utilidad_objetivo"
                        />
                        <InputError class="mt-2" :message="form.errors.utilidad_objetivo" />
                    </div>

                    <div class="mt-6">
                        <InputLabel for="observaciones_editar" value="Observaciones" />
                        <textarea
                            id="observaciones_editar"
                            ref="observaciones_editar"
                            rows="4"
                            v-model="form.observaciones"
                            placeholder="Ingresa las observaciones..."
                            class="
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
                                py-3
                                pr-10

                                text-gray-800
                                font-medium

                                placeholder:text-gray-400

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

                                resize-y
                            "
                        ></textarea>
                        <InputError class="mt-2" :message="form.errors.observaciones" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="closeModal">
                            {{ $t('Cancel') }}
                        </SecondaryButton>

                        <PrimaryButton
                            class="ms-3"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                            @click="editarPresupuesto"
                        >
                            Guardar
                        </PrimaryButton>
                    </div>
                </div>
            </Modal>
        </template>
    </LayoutPageHeader>
</template>