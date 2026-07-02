<?php

namespace App\Http\Controllers;

use App\Models\Movimientos;
use App\Models\Notificaciones;
use App\Models\ResumenMensual;
use App\Models\Ventas;
use Inertia\Inertia;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Presupuesto;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $mensaje = $request->get('mensaje', '');
        $cantidadNotificaciones = Notificaciones::count();
        $hoy = Carbon::today();
        $mesActual = $hoy->month;
        $anioActual = $hoy->year;

        // Ventas de hoy
        $ventasHoy = Ventas::whereDate('created_at', $hoy);
        $movimientoIngresoHoy = Movimientos::whereDate('created_at', $hoy)
        ->where('tipo', 'Ingreso')
        ->sum('valor');

        $cantidadVentasHoy = $ventasHoy->count();
        $sumaIngresosHoy = $ventasHoy->sum('precio');

        $sumaEgresosHoy = Movimientos::whereDate('created_at', $hoy)
        ->where('tipo', 'Egreso')
        ->sum('valor');

        $ventasHoyCompletas = [
            'cantidad_ventas' => $cantidadVentasHoy,
            'ingresos' => intval($sumaIngresosHoy + $movimientoIngresoHoy),
            'egresos' => intval($sumaEgresosHoy),
            'diferencia' => intval(($sumaIngresosHoy + $movimientoIngresoHoy) - $sumaEgresosHoy),
        ];

        // Ventas del mes
        $ventasMes = Ventas::whereMonth('created_at', $mesActual)
            ->whereYear('created_at', $anioActual);

        // Suma de ingresos por movimientos tipo Ingreso del mes
        $movimientoIngresoMes = Movimientos::whereMonth('created_at', $mesActual)
            ->whereYear('created_at', $anioActual)
            ->where('tipo', 'Ingreso')
            ->sum('valor');

        // Cantidad de ventas y suma total del campo precio
        $cantidadVentasMes = $ventasMes->count();
        $sumaIngresosMes = $ventasMes->sum('precio');

        // Suma de egresos del mes
        $sumaEgresosMes = Movimientos::whereMonth('created_at', $mesActual)
            ->whereYear('created_at', $anioActual)
            ->where('tipo', 'Egreso')
            ->sum('valor');

        $ingresosTotalesMes = $sumaIngresosMes + $movimientoIngresoMes;
        $margenGananciaMes = $ingresosTotalesMes == 0 ? 0 : round((($sumaIngresosMes + $movimientoIngresoMes) - $sumaEgresosMes) / $ingresosTotalesMes, 2) * 100;
        $roiMes = $sumaEgresosMes == 0 ? 0 : round((($sumaIngresosMes + $movimientoIngresoMes) - $sumaEgresosMes) / $sumaEgresosMes, 2) * 100;

        $existePeriodo = ResumenMensual::where('periodo', $hoy->format('m/Y'))->get()->first();

        if (!$existePeriodo) {
            ResumenMensual::create([
                'periodo' => $hoy->format('m/Y'),
                'cantidad' => $cantidadVentasMes,
                'ingresos' => intval($ingresosTotalesMes),
                'egresos' => intval($sumaEgresosMes),
                'utilidad_neta' => intval(($sumaIngresosMes + $movimientoIngresoMes) - $sumaEgresosMes),
                'margen_ganancia' => round($margenGananciaMes, 2),
                'roi' => round($roiMes, 2)
            ]);
            
        } else {
            $existePeriodo->update([
                'cantidad' => $cantidadVentasMes,
                'ingresos' => intval($ingresosTotalesMes),
                'egresos' => intval($sumaEgresosMes),
                'utilidad_neta' => intval(($sumaIngresosMes + $movimientoIngresoMes) - $sumaEgresosMes),
                'margen_ganancia' => round($margenGananciaMes, 2),
                'roi' => round($roiMes, 2)
            ]);
        }

        $resumenMesActual = ResumenMensual::where('periodo', $hoy->format('m/Y'))->get()->first();

        $ventasMesCompletas = [
            'cantidad_ventas' => $resumenMesActual->cantidad,
            'ingresos' => $resumenMesActual->ingresos,
            'egresos' => $resumenMesActual->egresos,
            'diferencia' => $resumenMesActual->utilidad_neta,
            'margen_ganancia' => $resumenMesActual->margen_ganancia,
            'roi' => $resumenMesActual->roi,
        ];

        $ventas = ResumenMensual::all();

        $cantidadVentas = $ventas->sum('cantidad');
        $sumaEgresos = $ventas->sum('egresos');
        $ingresosTotal = $ventas->sum('ingresos');
        $margenGananciaTotal = $ingresosTotal == 0 ? 0 : round(($ingresosTotal - $sumaEgresos) / $ingresosTotal, 2) * 100;
        $roiTotal = $sumaEgresos == 0 ? 0 : round(($ingresosTotal - $sumaEgresos) / $sumaEgresos, 2) * 100;

        $ventasTotales = [
            'cantidad_ventas' => intval($cantidadVentas),
            'ingresos' => intval($ingresosTotal),
            'egresos' => intval($sumaEgresos),
            'diferencia' => intval($ingresosTotal - $sumaEgresos),
            'margen_ganancia' => round($margenGananciaTotal, 2),
            'roi' => round($roiTotal, 2),
        ];

        $presupuestoMes = Presupuesto::where('anio', $anioActual)
            ->where('mes', $hoy->month)
            ->first();

        $ventasObjetivoMes = intval($presupuestoMes?->ventas_objetivo ?? 0);
        $ingresosObjetivoMes = intval($presupuestoMes?->ingresos_objetivo ?? 0);
        $utilidadObjetivoMes = intval($presupuestoMes?->utilidad_objetivo ?? 0);

        $presupuestoDiario = $presupuestoMes
            ? intval(round($presupuestoMes->ingresos_objetivo / $hoy->daysInMonth))
            : 0;

        $ventasObjetivoDiario = $presupuestoMes
            ? intval(round($presupuestoMes->ventas_objetivo / $hoy->daysInMonth))
            : 0;

        $utilidadObjetivoDiario = $presupuestoMes
            ? intval(round($presupuestoMes->utilidad_objetivo / $hoy->daysInMonth))
            : 0;

        $graficaCumplimientoDiario = Ventas::selectRaw('DATE_FORMAT(created_at, "%m/%d") AS fecha')
            ->selectRaw("
                CASE 
                    WHEN $presupuestoDiario > 0 
                    THEN ROUND((SUM(precio) / $presupuestoDiario) * 100, 2)
                    ELSE 0 
                END AS suma_dividida
            ")
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%m/%d")'))
            ->whereYear('created_at', $anioActual)
            ->whereRaw('DATE_FORMAT(created_at, "%m/%d") <= ?', [$hoy->format('m/d')])
            ->orderByRaw('fecha DESC')
            ->limit(8)
            ->get();
        
        $graficaCumplimientoDiario = $graficaCumplimientoDiario->toArray();
        $fechaObjetivoDia = $hoy->format('m/d');

        // Verifica si la fecha ya existe en el array
        $existeDia = collect($graficaCumplimientoDiario)->contains(function ($item) use ($fechaObjetivoDia) {
            return $item['fecha'] === $fechaObjetivoDia;
        });

        if (!$existeDia) {
            $graficaCumplimientoDiario[] = [
                'fecha' => $fechaObjetivoDia,
                'suma_dividida' => 0,
            ];
        }

        usort($graficaCumplimientoDiario, function($a, $b) {
            $fechaA = DateTime::createFromFormat('m/d', $a['fecha']);
            $fechaB = DateTime::createFromFormat('m/d', $b['fecha']);

            return $fechaA <=> $fechaB;
        });

        foreach ($graficaCumplimientoDiario as $clave => $item) {
            if ($item["fecha"] === $hoy->format('m/d')) {
                $graficaCumplimientoDiario[$clave]["fecha"] = 'Hoy';
            }
        }

        $graficaCumplimientoMensual = ResumenMensual::orderBy('id', 'DESC')
            ->limit(8)
            ->get()
            ->map(function ($resumen) {

                [$mes, $anio] = explode('/', $resumen->periodo);

                $presupuesto = Presupuesto::where('anio', $anio)
                    ->where('mes', (int) $mes)
                    ->first();

                $presupuestoMensual = $presupuesto?->ingresos_objetivo ?? 0;

                return [
                    'fecha' => $resumen->periodo,
                    'suma_dividida' => $presupuestoMensual > 0
                        ? round(($resumen->ingresos / $presupuestoMensual) * 100, 2)
                        : 0,
                ];
            });

        $graficaCumplimientoMensual = $graficaCumplimientoMensual->toArray();
        $fechaObjetivoMes = $hoy->format('m/Y');

        // Verifica si el mes ya existe en el array
        $existeMes = collect($graficaCumplimientoMensual)->contains(function ($item) use ($fechaObjetivoMes) {
            return $item['fecha'] === $fechaObjetivoMes;
        });

        if (!$existeMes) {
            $graficaCumplimientoMensual[] = [
                'fecha' => $fechaObjetivoMes,
                'suma_dividida' => 0,
            ];
        }

        usort($graficaCumplimientoMensual, function($a, $b) {
            $fechaA = DateTime::createFromFormat('m/Y', $a['fecha']);
            $fechaB = DateTime::createFromFormat('m/Y', $b['fecha']);

            return $fechaA <=> $fechaB;
        });

        return Inertia::render('Dashboard', [
            "cantidad_notificaciones" => $cantidadNotificaciones,
            "ventas_hoy" => $ventasHoyCompletas,
            "ventas_mes" => $ventasMesCompletas,
            "ventas_totales" => $ventasTotales,
            "grafica_cumplimiento_diario" => $graficaCumplimientoDiario,
            "grafica_cumplimiento_mensual" => $graficaCumplimientoMensual,
            "presupuesto_actual" => [
                "ventas_objetivo_mes" => intval($ventasObjetivoMes),
                "ingresos_objetivo_mes" => intval($ingresosObjetivoMes),
                "utilidad_objetivo_mes" => intval($utilidadObjetivoMes),

                "ventas_objetivo_diario" => round($ventasObjetivoDiario, 2),
                "ingresos_objetivo_diario" => round($presupuestoDiario, 2),
                "utilidad_objetivo_diario" => round($utilidadObjetivoDiario, 2),
            ],
            "mensaje" => $mensaje,
        ]);
    }
}
