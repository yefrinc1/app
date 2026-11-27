<?php

namespace App\Http\Controllers;

use App\Models\ResumenMensual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EstadisticaController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:reportes.juegos', ['only' => ['estadisticaJuegos']]);
        $this->middleware('can:reportes.mensual', ['only' => ['resumenMensual']]);
    }
    public function estadisticaJuegos(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $consulta = DB::table(DB::raw("(SELECT juego, SUM(precio_cop) AS total_comprado
                                                FROM correo_juegos
                                                WHERE precio_cop IS NOT NULL
                                                GROUP BY juego) AS c"))
            ->leftJoin(DB::raw("(SELECT correo_juegos.juego, 
                                                SUM(ventas.precio) AS total_vendido,
                                                MIN(ventas.created_at) AS fecha_minima,
                                                MAX(ventas.created_at) AS fecha_maxima
                                        FROM ventas
                                        INNER JOIN correo_juegos ON correo_juegos.id = ventas.id_correo_juego
                                        WHERE correo_juegos.precio_cop IS NOT NULL
                                        GROUP BY correo_juegos.juego) AS v"), 'c.juego', '=', 'v.juego')
            ->select(
                'c.juego',
                'c.total_comprado',
                'v.total_vendido',
                DB::raw('v.total_vendido - c.total_comprado AS diferencia'),
                DB::raw('CASE WHEN c.total_comprado > 0 THEN ROUND(((v.total_vendido - c.total_comprado) / c.total_comprado) * 100, 1) ELSE 0 END AS roi'),
                'v.fecha_minima',
                'v.fecha_maxima'
            )
            ->whereNotNull('v.total_vendido')
            ->when($search !== '', fn ($query) => $query->where('c.juego', 'like', "%{$search}%"));

        $datos = (clone $consulta)->get();
        $totalComprado = (float) $datos->sum('total_comprado');
        $totalVendido = (float) $datos->sum('total_vendido');
        $diferencia = $totalVendido - $totalComprado;

        $juegos = (clone $consulta)
            ->orderByDesc('diferencia')
            ->paginate(10)
            ->appends($request->query());

        return Inertia::render('Estadistica/RentabilidadJuegos', [
            'juegos' => $juegos,
            'search' => $search,
            'resumen' => [
                'juegos' => $datos->count(),
                'comprado' => (int) round($totalComprado),
                'vendido' => (int) round($totalVendido),
                'diferencia' => (int) round($diferencia),
                'roi' => $totalComprado > 0 ? round(($diferencia / $totalComprado) * 100, 1) : 0,
                'rentables' => $datos->where('diferencia', '>', 0)->count(),
                'por_recuperar' => $datos->where('diferencia', '<', 0)->count(),
            ],
            'graficos' => [
                'comparativo' => $datos->sortByDesc('total_vendido')->take(10)->values()->map(fn ($juego) => [
                    'juego' => $juego->juego,
                    'comprado' => (int) round($juego->total_comprado),
                    'vendido' => (int) round($juego->total_vendido),
                ]),
                'rentabilidad' => $datos->sortByDesc('diferencia')->take(10)->values()->map(fn ($juego) => [
                    'juego' => $juego->juego,
                    'diferencia' => (int) round($juego->diferencia),
                ]),
                'distribucion' => [
                    'rentables' => $datos->where('diferencia', '>', 0)->count(),
                    'equilibrio' => $datos->where('diferencia', 0)->count(),
                    'por_recuperar' => $datos->where('diferencia', '<', 0)->count(),
                ],
            ],
        ]);
    }

    public function resumenMensual()
    {
        $todos = ResumenMensual::query()->orderBy('id')->get();
        $resumenMensual = ResumenMensual::orderByDesc('id')->paginate(10);
        $ultimos = $todos->take(-12)->values();
        $actual = $todos->last();
        $anterior = $todos->count() > 1 ? $todos->get($todos->count() - 2) : null;
        $ingresos = (float) $todos->sum('ingresos');
        $egresos = (float) $todos->sum('egresos');
        $utilidad = (float) $todos->sum('utilidad_neta');

        return Inertia::render('Estadistica/ResumenMensual', [
            'resumen_mensual' => $resumenMensual,
            'resumen' => [
                'periodos' => $todos->count(),
                'ventas' => (int) $todos->sum('cantidad'),
                'ingresos' => (int) round($ingresos),
                'egresos' => (int) round($egresos),
                'utilidad' => (int) round($utilidad),
                'margen' => $ingresos > 0 ? round(($utilidad / $ingresos) * 100, 1) : 0,
                'roi' => $egresos > 0 ? round(($utilidad / $egresos) * 100, 1) : 0,
            ],
            'comparacion' => [
                'periodo_actual' => $actual?->periodo,
                'periodo_anterior' => $anterior?->periodo,
                'ventas' => $this->variacion($actual?->cantidad, $anterior?->cantidad),
                'ingresos' => $this->variacion($actual?->ingresos, $anterior?->ingresos),
                'utilidad' => $this->variacion($actual?->utilidad_neta, $anterior?->utilidad_neta),
            ],
            'graficos' => $ultimos->map(fn (ResumenMensual $item) => [
                'periodo' => $item->periodo,
                'ventas' => (int) $item->cantidad,
                'ingresos' => (int) $item->ingresos,
                'egresos' => (int) $item->egresos,
                'utilidad' => (int) $item->utilidad_neta,
                'margen' => (float) $item->margen_ganancia,
                'roi' => (float) $item->roi,
            ]),
        ]);
    }

    private function variacion(int|float|null $actual, int|float|null $anterior): ?float
    {
        if ($actual === null || $anterior === null) {
            return null;
        }

        if ((float) $anterior === 0.0) {
            return (float) $actual === 0.0 ? 0 : 100;
        }

        return round((((float) $actual - (float) $anterior) / abs((float) $anterior)) * 100, 1);
    }
}
