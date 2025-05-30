<?php

namespace App\Http\Controllers;

use App\Models\Movimientos;
use App\Models\Notificaciones;
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
        $movimientoIngresoMes = Movimientos::whereMonth('created_at', operator: $mesActual)
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

        // Resultado final
        $ventasMesCompletas = [
            'cantidad_ventas' => $cantidadVentasMes,
            'ingresos' => intval($ingresosTotalesMes),
            'egresos' => intval($sumaEgresosMes),
            'diferencia' => intval(($sumaIngresosMes + $movimientoIngresoMes) - $sumaEgresosMes),
            'margen_ganancia' => round($margenGananciaMes, 2),
            'roi' => round($roiMes, 2),
        ];

        // Total de ventas (sin importar la fecha)
        $ventas = Ventas::all();
        $cantidadVentas = $ventas->count();
        $sumaIngresos = $ventas->sum('precio');

        // Suma de ingresos desde movimientos
        $movimientoIngreso = Movimientos::where('tipo', 'Ingreso')->sum('valor');

        // Suma de egresos
        $sumaEgresos = Movimientos::where('tipo', 'Egreso')->sum('valor');

        $ingresosTotal = $sumaIngresos + $movimientoIngreso;
        $margenGananciaTotal = $ingresosTotal == 0 ? 0 : round((($sumaIngresos + $movimientoIngreso) - $sumaEgresos) / $ingresosTotal, 2) * 100;
        $roiTotal = $sumaEgresos == 0 ? 0 : round((($sumaIngresos + $movimientoIngreso) - $sumaEgresos) / $sumaEgresos, 2) * 100;

        // Consolidar todo como enteros
        $ventasTotales = [
            'cantidad_ventas' => intval($cantidadVentas),
            'ingresos' => intval($ingresosTotal),
            'egresos' => intval($sumaEgresos),
            'diferencia' => intval(($sumaIngresos + $movimientoIngreso) - $sumaEgresos),
            'margen_ganancia' => round($margenGananciaTotal, 2),
            'roi' => round($roiTotal, 2),
        ];

        $graficaCumplimientoDiario = Ventas::selectRaw('DATE_FORMAT(created_at, "%m/%d") AS fecha')
            ->selectRaw('ROUND((SUM(precio) / 700000) * 100, 2) AS suma_dividida')
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%m/%d")'))
            ->whereRaw('DATE_FORMAT(created_at, "%m/%d") <= ?', [$hoy->format('m/d')])
            ->orderByRaw('fecha DESC')
            ->limit(8)
            ->get();

        $graficaCumplimientoDiario = $graficaCumplimientoDiario->toArray();

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

        $graficaCumplimientoMensual = Ventas::selectRaw('DATE_FORMAT(created_at, "%m/%Y") AS fecha')
            ->selectRaw('ROUND((SUM(precio) / 21000000) * 100, 2) AS suma_dividida')
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%m/%Y")')) // Agrupar por mes y año
            ->whereRaw('DATE_FORMAT(created_at, "%m/%Y") <= ?', [$hoy->format('m/Y')]) // Filtro por mes y año
            ->orderByRaw('fecha DESC')
            ->limit(8)
            ->get();

        $graficaCumplimientoMensual = $graficaCumplimientoMensual->toArray();

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
