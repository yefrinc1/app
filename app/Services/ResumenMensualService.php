<?php

namespace App\Services;

use App\Models\Movimientos;
use App\Models\PedidoReembolso;
use App\Models\ResumenMensual;
use App\Models\Ventas;
use Carbon\Carbon;

class ResumenMensualService
{
    public function recalcular(Carbon|string|null $fecha = null): void
    {
        $fecha = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha ?? now());
        $mes = $fecha->month;
        $anio = $fecha->year;

        $ventas = Ventas::query()
            ->whereMonth('created_at', $mes)
            ->whereYear('created_at', $anio)
            ->where('estado', 'activa');

        $cantidadVentas = (clone $ventas)->count();
        $ingresosVentas = (float) (clone $ventas)->sum('precio');
        $otrosIngresos = (float) Movimientos::query()
            ->whereMonth('created_at', $mes)
            ->whereYear('created_at', $anio)
            ->where('tipo', 'Ingreso')
            ->sum('valor');
        $reembolsos = (float) PedidoReembolso::query()
            ->where('estado', 'aprobado')
            ->whereMonth('aprobado_at', $mes)
            ->whereYear('aprobado_at', $anio)
            ->whereHas('pedido.detalles.ventas', fn ($query) => $query->where('estado', 'activa'))
            ->sum('valor');
        $egresos = (float) Movimientos::query()
            ->whereMonth('created_at', $mes)
            ->whereYear('created_at', $anio)
            ->where('tipo', 'Egreso')
            ->sum('valor');

        $ingresos = $ingresosVentas + $otrosIngresos - $reembolsos;
        $utilidad = $ingresos - $egresos;
        $margen = $ingresos > 0 ? round(($utilidad / $ingresos) * 100, 2) : 0;
        $roi = $egresos > 0 ? round(($utilidad / $egresos) * 100, 2) : 0;

        ResumenMensual::updateOrCreate(
            ['periodo' => $fecha->format('m/Y')],
            [
                'cantidad' => $cantidadVentas,
                'ingresos' => (int) round($ingresos),
                'egresos' => (int) round($egresos),
                'utilidad_neta' => (int) round($utilidad),
                'margen_ganancia' => $margen,
                'roi' => $roi,
            ]
        );
    }
}
