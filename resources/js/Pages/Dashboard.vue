<script setup>
import LayoutPageHeader from '@/Layouts/LayoutPageHeader.vue';
import { Head } from '@inertiajs/vue3';
import { defineProps } from "vue";
import Swal from 'sweetalert2';
import {
  Chart as ChartJS,
  BarElement,
  CategoryScale,
  LinearScale,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'
import { Bar } from 'vue-chartjs'

// Registrar módulos necesarios
ChartJS.register(
  BarElement,
  CategoryScale,
  LinearScale,
  Title,
  Tooltip,
  Legend
)

const props = defineProps({
    cantidad_notificaciones: Number,
    ventas_hoy: Array,
    ventas_mes: Array,
    ventas_totales: Array,
    grafica_cumplimiento_diario: Array,
    grafica_cumplimiento_mensual: Array,
    mensaje: String,
});

const swalWithTailwind = Swal.mixin({
    buttonsStyling: true
});

if (props.mensaje != '') {
    swalWithTailwind.fire({
        title: props.mensaje,
        icon: "success",
    });
}

// Colores dinámicos
const getColor = (valor) => {
  if (valor < 100) {
    return '#F44336'; // Rojo
  } else if (valor >= 100 && valor <= 129) {
    return '#4CAF50'; // Verde
  } else {
    return '#2196F3'; // Azul
  }
};

// Cumplimiento Diario
const cumplimientoDiario = props.grafica_cumplimiento_diario.map(item => parseFloat(item.suma_dividida));  // Convertir a números
const labelsDiario = props.grafica_cumplimiento_diario.map(item => item.fecha);
const backgroundColorDiario = cumplimientoDiario.map(getColor);

// Data para el gráfico
const chartDataDiario = {
  labels: labelsDiario,  // Etiquetas con las fechas
  datasets: [
    {
      label: 'Cumplimiento (%)',
      data: cumplimientoDiario,  // Datos de suma_dividida
      backgroundColor: backgroundColorDiario,  // Colores basados en los valores
      borderRadius: 5,
    },
  ],
};

// Opciones de visualización
const chartOptionsDiario = {
  responsive: true,
  plugins: {
    legend: {
      position: 'top',
    },
    title: {
      display: true,
      text: 'Porcentaje de Cumplimiento Diario',
    },
  },
}

// Cumplimiento Mensual
const cumplimientoMensual = props.grafica_cumplimiento_mensual.map(item => parseFloat(item.suma_dividida));  // Convertir a números
const labelsMensual = props.grafica_cumplimiento_mensual.map(item => item.fecha);
const backgroundColorMensual= cumplimientoMensual.map(getColor);

// Data para el gráfico
const chartDataMensual = {
  labels: labelsMensual,  // Etiquetas con las fechas
  datasets: [
    {
      label: 'Cumplimiento (%)',
      data: cumplimientoMensual,  // Datos de suma_dividida
      backgroundColor: backgroundColorMensual,  // Colores basados en los valores
      borderRadius: 5,
    },
  ],
};

// Opciones de visualización
const chartOptionsMensual = {
  responsive: true,
  plugins: {
    legend: {
      position: 'top',
    },
    title: {
      display: true,
      text: 'Porcentaje de Cumplimiento Mensual',
    },
  },
}

</script>

<template>

    <Head title="Panel" />
    <LayoutPageHeader>
        <template #titulo-pagina>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">📊 Panel</h2>
        </template>

        <template #contenido-pagina>
            
            <!-- Notificación con acción -->
            <div v-if="cantidad_notificaciones > 0" class="mt-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg shadow-sm flex items-start justify-between">
                <div class="flex items-start space-x-3">
                    <i class="fa-solid fa-circle-exclamation text-yellow-500 text-xl mt-1"></i>
                    <div>
                        <p class="text-sm font-medium text-yellow-800">Tienes {{ cantidad_notificaciones }} tareas pendientes</p>
                        <p class="text-sm text-yellow-700">Revisa las tareas asignadas y márcalas como completadas.</p>
                    </div>
                </div>
                <div class="ml-4">
                    <a :href="route('notificaciones.index')" class="text-sm font-semibold text-yellow-700 hover:underline">
                        Ir a ver →
                    </a>
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <h2 class="text-lg font-medium text-gray-900 mb-6">
                        Informacion de ventas HOY
                    </h2>

                    <div class="mb-6 w-full">
                        <div>
                            <Bar :data="chartDataDiario" :options="chartOptionsDiario"/>
                        </div>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Cantidad</th>
                                    <th class="px-4 py-2 text-left">Ingresos</th>
                                    <th class="px-4 py-2 text-left">Egresos</th>
                                    <th class="px-4 py-2 text-left">Diferencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ ventas_hoy["cantidad_ventas"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_hoy["ingresos"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_hoy["egresos"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_hoy["diferencia"] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <h2 class="text-lg font-medium text-gray-900 mb-6">
                        Informacion de ventas MES
                    </h2>

                    <div class="mb-6 w-full">
                        <div>
                            <Bar :data="chartDataMensual" :options="chartOptionsMensual"/>
                        </div>
                    </div>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Cantidad</th>
                                    <th class="px-4 py-2 text-left">Ingresos</th>
                                    <th class="px-4 py-2 text-left">Egresos</th>
                                    <th class="px-4 py-2 text-left">Diferencia</th>
                                    <th class="px-4 py-2 text-left">Margen Ganancia</th>
                                    <th class="px-4 py-2 text-left">ROI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ ventas_mes["cantidad_ventas"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_mes["ingresos"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_mes["egresos"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_mes["diferencia"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_mes["margen_ganancia"] }} %</td>
                                    <td class="px-4 py-2">{{ ventas_mes["roi"] }} %</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <section>
                    <h2 class="text-lg font-medium text-gray-900 mb-6">
                        Informacion de ventas TOTALES
                    </h2>

                    <div class="overflow-x-auto shadow rounded-lg">
                        <table class="min-w-full border border-gray-200">
                            <thead>
                                <tr class="bg-gray-100 border-b">
                                    <th class="px-4 py-2 text-left">Cantidad</th>
                                    <th class="px-4 py-2 text-left">Ingresos</th>
                                    <th class="px-4 py-2 text-left">Egresos</th>
                                    <th class="px-4 py-2 text-left">Diferencia</th>
                                    <th class="px-4 py-2 text-left">Margen Ganancia</th>
                                    <th class="px-4 py-2 text-left">ROI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b hover:bg-gray-100">
                                    <td class="px-4 py-2">{{ ventas_totales["cantidad_ventas"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_totales["ingresos"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_totales["egresos"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_totales["diferencia"] }}</td>
                                    <td class="px-4 py-2">{{ ventas_totales["margen_ganancia"] }} %</td>
                                    <td class="px-4 py-2">{{ ventas_totales["roi"] }} %</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </template>
    </LayoutPageHeader>
</template>