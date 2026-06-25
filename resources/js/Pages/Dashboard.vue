<script setup>
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { Head } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import {
    Chart as ChartJS,
    BarElement,
    CategoryScale,
    LinearScale,
    Title,
    Tooltip,
    Legend,
} from 'chart.js';
import { Bar } from 'vue-chartjs';

ChartJS.register(
    BarElement,
    CategoryScale,
    LinearScale,
    Title,
    Tooltip,
    Legend
);

const props = defineProps({
    cantidad_notificaciones: Number,
    ventas_hoy: Object,
    ventas_mes: Object,
    ventas_totales: Object,
    grafica_cumplimiento_diario: Array,
    grafica_cumplimiento_mensual: Array,
    presupuesto_actual: Object,
    mensaje: String,
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true,
});

if (props.mensaje !== '') {
    swalWithTailwind.fire({
        title: props.mensaje,
        icon: 'success',
    });
}

const formatoCop = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
};

const formatoNumero = (valor) => {
    return Number(valor || 0).toLocaleString('es-CO');
};

const calcularCumplimiento = (actual, objetivo) => {
    if (!objetivo || objetivo <= 0) return 0;

    return Math.round((actual / objetivo) * 100);
};

const claseCumplimiento = (porcentaje) => {
    if (porcentaje < 80) return 'bg-red-500';
    if (porcentaje < 100) return 'bg-yellow-500';
    if (porcentaje <= 129) return 'bg-green-500';
    return 'bg-blue-500';
};

const textoCumplimiento = (porcentaje) => {
    if (porcentaje < 80) return 'Bajo';
    if (porcentaje < 100) return 'Cerca';
    if (porcentaje <= 129) return 'Cumplido';
    return 'Superado';
};

const getColor = (valor) => {
    if (valor < 100) {
        return '#F44336';
    } else if (valor >= 100 && valor <= 129) {
        return '#4CAF50';
    } else {
        return '#2196F3';
    }
};

const cumplimientoDiario = props.grafica_cumplimiento_diario.map(item => parseFloat(item.suma_dividida));
const labelsDiario = props.grafica_cumplimiento_diario.map(item => item.fecha);
const backgroundColorDiario = cumplimientoDiario.map(getColor);

const chartDataDiario = {
    labels: labelsDiario,
    datasets: [
        {
            label: 'Cumplimiento diario (%)',
            data: cumplimientoDiario,
            backgroundColor: backgroundColorDiario,
            borderRadius: 8,
        },
    ],
};

const chartOptionsDiario = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        },
        title: {
            display: false,
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function (value) {
                    return value + '%';
                },
            },
        },
    },
};

const cumplimientoMensual = props.grafica_cumplimiento_mensual.map(item => parseFloat(item.suma_dividida));
const labelsMensual = props.grafica_cumplimiento_mensual.map(item => item.fecha);
const backgroundColorMensual = cumplimientoMensual.map(getColor);

const chartDataMensual = {
    labels: labelsMensual,
    datasets: [
        {
            label: 'Cumplimiento mensual (%)',
            data: cumplimientoMensual,
            backgroundColor: backgroundColorMensual,
            borderRadius: 8,
        },
    ],
};

const chartOptionsMensual = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        },
        title: {
            display: false,
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function (value) {
                    return value + '%';
                },
            },
        },
    },
};

const cumplimientoVentasMes = calcularCumplimiento(
    props.ventas_mes.cantidad_ventas,
    props.presupuesto_actual?.ventas_objetivo_mes
);

const cumplimientoIngresosMes = calcularCumplimiento(
    props.ventas_mes.ingresos,
    props.presupuesto_actual?.ingresos_objetivo_mes
);

const cumplimientoUtilidadMes = calcularCumplimiento(
    props.ventas_mes.diferencia,
    props.presupuesto_actual?.utilidad_objetivo_mes
);

const cumplimientoVentasHoy = calcularCumplimiento(
    props.ventas_hoy.cantidad_ventas,
    props.presupuesto_actual?.ventas_objetivo_diario
);

const cumplimientoIngresosHoy = calcularCumplimiento(
    props.ventas_hoy.ingresos,
    props.presupuesto_actual?.ingresos_objetivo_diario
);

const cumplimientoUtilidadHoy = calcularCumplimiento(
    props.ventas_hoy.diferencia,
    props.presupuesto_actual?.utilidad_objetivo_diario
);
</script>

<template>
    <Head title="Panel" />

    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📊 Panel
            </h2>
        </template>

        <template #contenido-pagina>
            <div class="space-y-6">

                <!-- Notificaciones -->
                <div
                    v-if="cantidad_notificaciones > 0"
                    class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                >
                    <div class="flex items-start gap-3">
                        <i class="fa-solid fa-circle-exclamation text-yellow-500 text-xl mt-1"></i>
                        <div>
                            <p class="text-sm font-semibold text-yellow-800">
                                Tienes {{ cantidad_notificaciones }} tareas pendientes
                            </p>
                            <p class="text-sm text-yellow-700">
                                Revisa las tareas asignadas y márcalas como completadas.
                            </p>
                        </div>
                    </div>

                    <a
                        :href="route('notificaciones.index')"
                        class="text-sm font-semibold text-yellow-700 hover:underline"
                    >
                        Ir a ver →
                    </a>
                </div>

                <!-- Resumen principal del mes -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Ventas del mes</p>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    {{ formatoNumero(ventas_mes.cantidad_ventas) }}
                                </h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                                <i class="fa-solid fa-cart-shopping text-xl"></i>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Objetivo: {{ formatoNumero(presupuesto_actual?.ventas_objetivo_mes) }}</span>
                                <span>{{ cumplimientoVentasMes }}%</span>
                            </div>

                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    class="h-2 rounded-full"
                                    :class="claseCumplimiento(cumplimientoVentasMes)"
                                    :style="{ width: Math.min(cumplimientoVentasMes, 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Ingresos del mes</p>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    ${{ formatoCop(ventas_mes.ingresos) }}
                                </h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                <i class="fa-solid fa-sack-dollar text-xl"></i>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Objetivo: ${{ formatoCop(presupuesto_actual?.ingresos_objetivo_mes) }}</span>
                                <span>{{ cumplimientoIngresosMes }}%</span>
                            </div>

                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    class="h-2 rounded-full"
                                    :class="claseCumplimiento(cumplimientoIngresosMes)"
                                    :style="{ width: Math.min(cumplimientoIngresosMes, 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Utilidad del mes</p>
                                <h3 class="text-2xl font-bold text-gray-900">
                                    ${{ formatoCop(ventas_mes.diferencia) }}
                                </h3>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                                <i class="fa-solid fa-chart-line text-xl"></i>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Objetivo: ${{ formatoCop(presupuesto_actual?.utilidad_objetivo_mes) }}</span>
                                <span>{{ cumplimientoUtilidadMes }}%</span>
                            </div>

                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    class="h-2 rounded-full"
                                    :class="claseCumplimiento(cumplimientoUtilidadMes)"
                                    :style="{ width: Math.min(cumplimientoUtilidadMes, 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hoy -->
                <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">
                                Información de ventas HOY
                            </h2>
                            <p class="text-sm text-gray-500">
                                Comparación diaria contra el presupuesto del mes dividido por días.
                            </p>
                        </div>

                        <span
                            class="px-3 py-1 rounded-full text-xs font-bold w-fit"
                            :class="cumplimientoIngresosHoy >= 100 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                        >
                            {{ textoCumplimiento(cumplimientoIngresosHoy) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Ventas</p>
                            <p class="text-xl font-bold">{{ formatoNumero(ventas_hoy.cantidad_ventas) }}</p>
                            <p class="text-xs text-gray-500">
                                Obj: {{ Number(presupuesto_actual?.ventas_objetivo_diario || 0) }}
                                — {{ cumplimientoVentasHoy }}%
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Ingresos</p>
                            <p class="text-xl font-bold">${{ formatoCop(ventas_hoy.ingresos) }}</p>
                            <p class="text-xs text-gray-500">
                                Obj: ${{ formatoCop(presupuesto_actual?.ingresos_objetivo_diario) }}
                                — {{ cumplimientoIngresosHoy }}%
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Egresos</p>
                            <p class="text-xl font-bold">${{ formatoCop(ventas_hoy.egresos) }}</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Utilidad</p>
                            <p
                                class="text-xl font-bold"
                                :class="ventas_hoy.diferencia >= 0 ? 'text-green-700' : 'text-red-700'"
                            >
                                ${{ formatoCop(ventas_hoy.diferencia) }}
                            </p>
                            <p class="text-xs text-gray-500">
                                Obj: ${{ formatoCop(presupuesto_actual?.utilidad_objetivo_diario) }}
                                — {{ cumplimientoUtilidadHoy }}%
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Gráficas -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold text-gray-900">
                                Cumplimiento diario
                            </h2>
                            <p class="text-sm text-gray-500">
                                Porcentaje de ingresos diarios frente al objetivo diario.
                            </p>
                        </div>

                        <div class="h-80">
                            <Bar :data="chartDataDiario" :options="chartOptionsDiario" />
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold text-gray-900">
                                Cumplimiento mensual
                            </h2>
                            <p class="text-sm text-gray-500">
                                Porcentaje de ingresos mensuales frente al presupuesto de cada mes.
                            </p>
                        </div>

                        <div class="h-80">
                            <Bar :data="chartDataMensual" :options="chartOptionsMensual" />
                        </div>
                    </div>
                </div>

                <!-- Mes detallado -->
                <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">
                        Información de ventas MES
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Cantidad</p>
                            <p class="text-xl font-bold">{{ formatoNumero(ventas_mes.cantidad_ventas) }}</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Ingresos</p>
                            <p class="text-xl font-bold">${{ formatoCop(ventas_mes.ingresos) }}</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Egresos</p>
                            <p class="text-xl font-bold">${{ formatoCop(ventas_mes.egresos) }}</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Diferencia</p>
                            <p
                                class="text-xl font-bold"
                                :class="ventas_mes.diferencia >= 0 ? 'text-green-700' : 'text-red-700'"
                            >
                                ${{ formatoCop(ventas_mes.diferencia) }}
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Margen</p>
                            <p class="text-xl font-bold">{{ ventas_mes.margen_ganancia }}%</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">ROI</p>
                            <p class="text-xl font-bold">{{ ventas_mes.roi }}%</p>
                        </div>
                    </div>
                </div>

                <!-- Totales -->
                <div class="bg-white shadow rounded-xl p-5 border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">
                        Información de ventas TOTALES
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Cantidad</p>
                            <p class="text-xl font-bold">{{ formatoNumero(ventas_totales.cantidad_ventas) }}</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Ingresos</p>
                            <p class="text-xl font-bold">${{ formatoCop(ventas_totales.ingresos) }}</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Egresos</p>
                            <p class="text-xl font-bold">${{ formatoCop(ventas_totales.egresos) }}</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Diferencia</p>
                            <p
                                class="text-xl font-bold"
                                :class="ventas_totales.diferencia >= 0 ? 'text-green-700' : 'text-red-700'"
                            >
                                ${{ formatoCop(ventas_totales.diferencia) }}
                            </p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">Margen</p>
                            <p class="text-xl font-bold">{{ ventas_totales.margen_ganancia }}%</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-xs text-gray-500">ROI</p>
                            <p class="text-xl font-bold">{{ ventas_totales.roi }}%</p>
                        </div>
                    </div>
                </div>

            </div>
        </template>
    </LayoutPageHeader>
</template>