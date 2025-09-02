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

        $presupuestoDiario = config('app.presupuesto_diario');
        $graficaCumplimientoDiario = Ventas::selectRaw('DATE_FORMAT(created_at, "%m/%d") AS fecha')
            ->selectRaw("ROUND((SUM(precio) / $presupuestoDiario) * 100, 2) AS suma_dividida")
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%m/%d")'))
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

        $presupuestoMensual = config('app.presupuesto_mensual');
        $graficaCumplimientoMensual = ResumenMensual::select(
                DB::raw('periodo AS fecha'),
                DB::raw("ROUND((ingresos / $presupuestoMensual) * 100, 2) AS suma_dividida")
            )
            ->orderBy('id', 'DESC')
            ->limit(8)
            ->get();
        
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
            "mensaje" => $mensaje,
        ]);
    }
}
