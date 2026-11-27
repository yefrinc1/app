<?php

namespace App\Http\Controllers;

use App\Models\Movimientos;
use App\Models\Ventas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MovimientosController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:finanzas.movimientos', ['only' => ['index', 'store', 'update', 'destroy']]);
    }

    public function index(Request $request)
    {
        $filtros = array_merge(['periodo' => '30', 'tipo' => '', 'buscar' => '', 'incluir_ventas' => false], $request->validate([
            'periodo' => ['nullable', 'in:7,30,90,180,365,todo,personalizado'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'tipo' => ['nullable', 'in:Ingreso,Egreso,Retiro Utilidades'],
            'buscar' => ['nullable', 'string', 'max:150'],
            'incluir_ventas' => ['nullable', 'boolean'],
        ]));
        [$desde, $hasta] = $this->periodo($filtros);
        $filtros['buscar'] = trim((string) $filtros['buscar']);
        $filtros['incluir_ventas'] = (bool) $filtros['incluir_ventas'];

        $base = Movimientos::query()
            ->when($desde, fn ($query) => $query->where('created_at', '>=', $desde->startOfDay()))
            ->when($hasta, fn ($query) => $query->where('created_at', '<=', $hasta->endOfDay()))
            ->when($filtros['tipo'] !== '', fn ($query) => $query->where('tipo', $filtros['tipo']))
            ->when($filtros['buscar'] !== '', fn ($query) => $query->where(function ($subquery) use ($filtros) {
                $subquery->where('descripcion', 'like', "%{$filtros['buscar']}%")
                    ->orWhere('observaciones', 'like', "%{$filtros['buscar']}%");
            }));

        $ventasBase = Ventas::query()
            ->where('estado', 'activa')
            ->when($desde, fn ($query) => $query->where('created_at', '>=', $desde->startOfDay()))
            ->when($hasta, fn ($query) => $query->where('created_at', '<=', $hasta->endOfDay()));
        $ventasIncluidas = $filtros['incluir_ventas'] ? (int) (clone $ventasBase)->sum('precio') : 0;
        $cantidadVentas = $filtros['incluir_ventas'] ? (clone $ventasBase)->count() : 0;

        $datos = (clone $base)->get();
        $resumen = $this->resumen($datos, $ventasIncluidas, $cantidadVentas);
        $movimientos = (clone $base)->latest()->paginate(15)->appends($request->query());
        $dias = $desde && $hasta ? $desde->diffInDays($hasta) + 1 : null;
        $agruparPorMes = $dias === null || $dias > 90;
        $expresionPeriodo = $agruparPorMes ? "DATE_FORMAT(created_at, '%Y-%m')" : 'DATE(created_at)';

        $evolucionMovimientos = (clone $base)->reorder()
            ->selectRaw("{$expresionPeriodo} periodo,
                SUM(CASE WHEN tipo = 'Ingreso' THEN valor ELSE 0 END) ingresos,
                SUM(CASE WHEN tipo = 'Egreso' THEN valor ELSE 0 END) egresos,
                SUM(CASE WHEN tipo = 'Retiro Utilidades' THEN valor ELSE 0 END) retiros")
            ->groupByRaw($expresionPeriodo)->orderBy('periodo')->get();
        $evolucionVentas = $filtros['incluir_ventas']
            ? (clone $ventasBase)->reorder()
                ->selectRaw("{$expresionPeriodo} periodo, SUM(precio) total")
                ->groupByRaw($expresionPeriodo)->orderBy('periodo')->get()->keyBy('periodo')
            : collect();
        $evolucion = $evolucionMovimientos->keyBy('periodo')
            ->keys()->merge($evolucionVentas->keys())->unique()->sort()->values()
            ->map(function ($periodo) use ($evolucionMovimientos, $evolucionVentas) {
                $movimiento = $evolucionMovimientos->firstWhere('periodo', $periodo);
                $ventas = (int) ($evolucionVentas->get($periodo)?->total ?? 0);

                return [
                    'periodo' => $periodo,
                    'ingresos' => (int) ($movimiento?->ingresos ?? 0) + $ventas,
                    'ingresos_manuales' => (int) ($movimiento?->ingresos ?? 0),
                    'ventas' => $ventas,
                    'egresos' => (int) ($movimiento?->egresos ?? 0),
                    'retiros' => (int) ($movimiento?->retiros ?? 0),
                ];
            });

        $conceptos = (clone $base)->reorder()->whereIn('tipo', ['Egreso', 'Retiro Utilidades'])
            ->select('descripcion')->selectRaw('SUM(valor) total')
            ->groupBy('descripcion')->orderByDesc('total')->limit(10)->get();

        $comparacion = ['disponible' => false, 'ingresos' => null, 'egresos' => null, 'balance' => null];
        if ($desde && $hasta && ($filtros['periodo'] ?? '') !== 'personalizado') {
            $anteriorHasta = $desde->subDay();
            $anteriorDesde = $anteriorHasta->subDays($dias - 1);
            $anterior = Movimientos::query()
                ->whereBetween('created_at', [$anteriorDesde->startOfDay(), $anteriorHasta->endOfDay()])
                ->when($filtros['tipo'] !== '', fn ($query) => $query->where('tipo', $filtros['tipo']))
                ->when($filtros['buscar'] !== '', fn ($query) => $query->where(function ($subquery) use ($filtros) {
                    $subquery->where('descripcion', 'like', "%{$filtros['buscar']}%")
                        ->orWhere('observaciones', 'like', "%{$filtros['buscar']}%");
                }))->get();
            $ventasAnteriores = $filtros['incluir_ventas']
                ? (int) Ventas::query()->where('estado', 'activa')
                    ->whereBetween('created_at', [$anteriorDesde->startOfDay(), $anteriorHasta->endOfDay()])
                    ->sum('precio')
                : 0;
            $cantidadVentasAnteriores = $filtros['incluir_ventas']
                ? Ventas::query()->where('estado', 'activa')
                    ->whereBetween('created_at', [$anteriorDesde->startOfDay(), $anteriorHasta->endOfDay()])
                    ->count()
                : 0;
            $resumenAnterior = $this->resumen($anterior, $ventasAnteriores, $cantidadVentasAnteriores);
            $comparacion = [
                'disponible' => true,
                'ingresos' => $this->variacion($resumen['ingresos'], $resumenAnterior['ingresos']),
                'egresos' => $this->variacion($resumen['egresos'], $resumenAnterior['egresos']),
                'balance' => $this->variacion($resumen['balance_operativo'], $resumenAnterior['balance_operativo']),
            ];
        }

        return Inertia::render('Movimientos/Index', [
            'movimientos' => $movimientos,
            'filtros' => $filtros,
            'resumen' => $resumen,
            'comparacion' => $comparacion,
            'graficos' => [
                'evolucion' => $evolucion,
                'tipos' => [
                    'ingresos' => $resumen['ingresos'],
                    'egresos' => $resumen['egresos'],
                    'retiros' => $resumen['retiros'],
                ],
                'conceptos' => $conceptos,
            ],
            'periodo' => [
                'desde' => $desde?->toDateString(),
                'hasta' => $hasta?->toDateString(),
                'dias' => $dias,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'tipo' => ['required', 'in:Ingreso,Egreso,Retiro Utilidades'],
            'descripcion' => ['required', 'string', 'max:150'],
            'valor' => ['required', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        Movimientos::create([
            'tipo' => $validatedData['tipo'],
            'descripcion' => $validatedData['descripcion'],
            'valor' => $validatedData['valor'],
            'observaciones' => $validatedData['observaciones'] ?? null,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, string $id)
    {
        $movimiento = Movimientos::findOrFail($id);

        $validatedData = $request->validate([
            'tipo' => ['required', 'in:Ingreso,Egreso,Retiro Utilidades'],
            'descripcion' => ['required', 'string', 'max:150'],
            'valor' => ['required', 'integer', 'min:1'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);
    
        $movimiento->update([
            'tipo' => $validatedData['tipo'],
            'descripcion' => $validatedData['descripcion'],
            'valor' => $validatedData['valor'],
            'observaciones' => $validatedData['observaciones'] ?? null,
        ]);

        return redirect()->back();
    }

    public function destroy(string $id)
    {
        $movimiento = Movimientos::findOrFail($id);
        $movimiento->delete();

        return redirect()->back();
    }

    private function periodo(array $filtros): array
    {
        if (($filtros['periodo'] ?? '30') === 'todo') {
            return [null, null];
        }
        if (($filtros['periodo'] ?? '') === 'personalizado' && ! empty($filtros['desde']) && ! empty($filtros['hasta'])) {
            $desde = CarbonImmutable::parse($filtros['desde']);
            $hasta = CarbonImmutable::parse($filtros['hasta']);

            return $desde->lte($hasta) ? [$desde, $hasta] : [$hasta, $desde];
        }

        $dias = in_array((int) ($filtros['periodo'] ?? 30), [7, 30, 90, 180, 365], true) ? (int) $filtros['periodo'] : 30;
        $hasta = CarbonImmutable::today();

        return [$hasta->subDays($dias - 1), $hasta];
    }

    private function resumen($movimientos, int $ventas = 0, int $cantidadVentas = 0): array
    {
        $ingresosManuales = (int) $movimientos->where('tipo', 'Ingreso')->sum('valor');
        $ingresos = $ingresosManuales + $ventas;
        $egresos = (int) $movimientos->where('tipo', 'Egreso')->sum('valor');
        $retiros = (int) $movimientos->where('tipo', 'Retiro Utilidades')->sum('valor');

        return [
            'movimientos' => $movimientos->count(),
            'ingresos' => $ingresos,
            'ingresos_manuales' => $ingresosManuales,
            'ventas' => $ventas,
            'cantidad_ventas' => $cantidadVentas,
            'egresos' => $egresos,
            'retiros' => $retiros,
            'balance_operativo' => $ingresos - $egresos,
            'saldo_neto' => $ingresos - $egresos - $retiros,
        ];
    }

    private function variacion(int $actual, int $anterior): float
    {
        if ($anterior === 0) {
            return $actual === 0 ? 0 : 100;
        }

        return round((($actual - $anterior) / abs($anterior)) * 100, 1);
    }
}
