<?php

namespace App\Http\Controllers;

use App\Models\CierreCaja;
use App\Models\Movimientos;
use App\Models\Ventas;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class CierreCajaController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:finanzas.cerrar.caja', ['only' => ['create', 'store', 'destroy']]);
        $this->middleware('can:finanzas.cerrar.caja.ver', ['only' => ['index']]);
    }

    public function index(Request $request)
    {
        $filtros = array_merge([
            'periodo' => '90',
            'resultado' => '',
            'buscar' => '',
        ], $request->validate([
            'periodo' => ['nullable', 'in:7,30,90,180,365,todo,personalizado'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'resultado' => ['nullable', 'in:positivo,negativo,ajuste'],
            'buscar' => ['nullable', 'string', 'max:150'],
        ]));
        [$desde, $hasta] = $this->periodo($filtros);
        $filtros['buscar'] = trim((string) $filtros['buscar']);

        $base = CierreCaja::query()
            ->when($desde, fn ($query) => $query->whereDate('fecha', '>=', $desde->toDateString()))
            ->when($hasta, fn ($query) => $query->whereDate('fecha', '<=', $hasta->toDateString()))
            ->when($filtros['buscar'] !== '', fn ($query) => $query->where('observaciones', 'like', "%{$filtros['buscar']}%"))
            ->when($filtros['resultado'] === 'positivo', fn ($query) => $query->whereRaw('(saldo_final - saldo_inicial) >= 0'))
            ->when($filtros['resultado'] === 'negativo', fn ($query) => $query->whereRaw('(saldo_final - saldo_inicial) < 0'))
            ->when($filtros['resultado'] === 'ajuste', fn ($query) => $query->whereRaw('(saldo_final - (saldo_inicial + ingresos - egresos)) <> 0'));

        $datos = (clone $base)->orderBy('fecha')->get();
        $resumen = $this->resumen($datos);
        $cierres = (clone $base)->orderByDesc('fecha')->paginate(15)->appends($request->query());
        $dias = $desde && $hasta ? $desde->diffInDays($hasta) + 1 : null;
        $agruparPorMes = $dias === null || $dias > 180;
        $evolucion = $datos
            ->groupBy(fn ($cierre) => $agruparPorMes ? CarbonImmutable::parse($cierre->fecha)->format('Y-m') : CarbonImmutable::parse($cierre->fecha)->toDateString())
            ->map(fn ($grupo, $periodo) => [
                'periodo' => $periodo,
                'ingresos' => (int) $grupo->sum('ingresos'),
                'egresos' => (int) $grupo->sum('egresos'),
                'flujo' => (int) $grupo->sum(fn ($cierre) => $cierre->ingresos - $cierre->egresos),
                'saldo_final' => (int) $grupo->sortBy('fecha')->last()->saldo_final,
            ])->values();

        $comparacion = ['disponible' => false, 'ingresos' => null, 'egresos' => null, 'flujo' => null];
        if ($desde && $hasta && ($filtros['periodo'] ?? '') !== 'personalizado') {
            $anteriorHasta = $desde->subDay();
            $anteriorDesde = $anteriorHasta->subDays($dias - 1);
            $anterior = CierreCaja::query()
                ->whereBetween('fecha', [$anteriorDesde->toDateString(), $anteriorHasta->toDateString()])
                ->when($filtros['buscar'] !== '', fn ($query) => $query->where('observaciones', 'like', "%{$filtros['buscar']}%"))
                ->when($filtros['resultado'] === 'positivo', fn ($query) => $query->whereRaw('(saldo_final - saldo_inicial) >= 0'))
                ->when($filtros['resultado'] === 'negativo', fn ($query) => $query->whereRaw('(saldo_final - saldo_inicial) < 0'))
                ->when($filtros['resultado'] === 'ajuste', fn ($query) => $query->whereRaw('(saldo_final - (saldo_inicial + ingresos - egresos)) <> 0'))
                ->get();
            $resumenAnterior = $this->resumen($anterior);
            $comparacion = [
                'disponible' => true,
                'ingresos' => $this->variacion($resumen['ingresos'], $resumenAnterior['ingresos']),
                'egresos' => $this->variacion($resumen['egresos'], $resumenAnterior['egresos']),
                'flujo' => $this->variacion($resumen['flujo_neto'], $resumenAnterior['flujo_neto']),
            ];
        }

        return Inertia::render('CierreCaja/Index', [
            'cierre_cajas' => $cierres,
            'filtros' => $filtros,
            'resumen' => $resumen,
            'comparacion' => $comparacion,
            'graficos' => [
                'evolucion' => $evolucion,
                'distribucion' => [
                    'ingresos' => $resumen['ingresos'],
                    'egresos' => $resumen['egresos'],
                    'ajustes' => abs($resumen['ajustes']),
                ],
            ],
            'periodo' => [
                'desde' => $desde?->toDateString(),
                'hasta' => $hasta?->toDateString(),
                'dias' => $dias,
            ],
            'mensaje' => $request->get('mensaje', ''),
        ]);
    }

    private function periodo(array $filtros): array
    {
        if (($filtros['periodo'] ?? '90') === 'todo') {
            return [null, null];
        }
        if (($filtros['periodo'] ?? '') === 'personalizado' && ! empty($filtros['desde']) && ! empty($filtros['hasta'])) {
            $desde = CarbonImmutable::parse($filtros['desde']);
            $hasta = CarbonImmutable::parse($filtros['hasta']);

            return $desde->lte($hasta) ? [$desde, $hasta] : [$hasta, $desde];
        }

        $dias = in_array((int) ($filtros['periodo'] ?? 90), [7, 30, 90, 180, 365], true) ? (int) $filtros['periodo'] : 90;
        $hasta = CarbonImmutable::today();

        return [$hasta->subDays($dias - 1), $hasta];
    }

    private function resumen($cierres): array
    {
        $ingresos = (int) $cierres->sum('ingresos');
        $egresos = (int) $cierres->sum('egresos');
        $ajustes = (int) $cierres->sum(fn ($cierre) => $cierre->saldo_final - ($cierre->saldo_inicial + $cierre->ingresos - $cierre->egresos));
        $ultimo = $cierres->sortByDesc('fecha')->first();

        return [
            'cierres' => $cierres->count(),
            'ingresos' => $ingresos,
            'egresos' => $egresos,
            'flujo_neto' => $ingresos - $egresos,
            'ajustes' => $ajustes,
            'saldo_actual' => (int) ($ultimo?->saldo_final ?? 0),
            'promedio_ingresos' => $cierres->count() ? (int) round($ingresos / $cierres->count()) : 0,
            'cierres_positivos' => $cierres->filter(fn ($cierre) => $cierre->saldo_final - $cierre->saldo_inicial >= 0)->count(),
        ];
    }

    private function variacion(int $actual, int $anterior): float
    {
        if ($anterior === 0) {
            return $actual === 0 ? 0 : 100;
        }

        return round((($actual - $anterior) / abs($anterior)) * 100, 1);
    }

    public function create(Request $request)
    {
        $fecha = $request->input('fecha', Carbon::today());

        $ventas = Ventas::whereDate('created_at', $fecha)
            ->with('correoJuego')
            ->get()
            ->map(fn($venta) => [
                'cliente' => $venta->cliente,
                'tipo_cuenta' => $venta->tipo_cuenta,
                'consola' => $venta->consola,
                'precio' => $venta->precio,
                'medio_pago' => $venta->medio_pago,
                'correoJuego' => [
                    'correo' => $venta->correoJuego->correo ?? null,
                    'juego' => $venta->correoJuego->juego ?? null,
                ],
            ]);

        $movimientos = Movimientos::whereDate('created_at', $fecha)
            ->get()
            ->map(fn($movimiento) => [
                'cliente' => $movimiento->observaciones,
                'tipo_cuenta' => null,
                'consola' => null,
                'precio' => $movimiento->tipo === 'Ingreso' ? $movimiento->valor : -$movimiento->valor,
                'medio_pago' => null,
                'correoJuego' => [
                    'correo' => $movimiento->descripcion,
                    'juego' => $movimiento->tipo,
                ],
            ]);

        $ventasYMovimientos = $ventas->concat($movimientos);

        $ultimoCierre = CierreCaja::latest()->first();
        $saldoInicial = $ultimoCierre->saldo_final ?? 0;

        $ingresos = Ventas::whereDate('created_at', $fecha)
        ->sum('precio');

        $movimientoIngresoHoy = Movimientos::whereDate('created_at', $fecha)
        ->where('tipo', 'Ingreso')
        ->sum('valor');

        $egresos = Movimientos::where('tipo', 'Egreso')
        ->whereDate('created_at', $fecha)
        ->sum('valor');

        $retiroUtilidades = Movimientos::where('tipo', 'Retiro Utilidades')
        ->whereDate('created_at', $fecha)
        ->sum('valor');

        $saldoFinal = $saldoInicial + ($ingresos + $movimientoIngresoHoy - $egresos - $retiroUtilidades);

        return Inertia::render('CierreCaja/Create', [
            'ventas' => $ventasYMovimientos,
            'ingresos' => intval($ingresos + $movimientoIngresoHoy),
            'egresos' => intval($egresos),
            'retiro_utilidades' => intval($retiroUtilidades),
            'saldo_inicial' => intval($saldoInicial),
            'saldo_final' => intval($saldoFinal),
            'fecha' => $fecha,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'fecha' => 'required|unique:cierre_cajas,fecha',
            'saldo_inicial' => 'required',
            'ingresos' => 'required',
            'egresos' => 'required',
            'saldo_final' => 'required',
        ]);

        CierreCaja::create([
            'fecha' => $validatedData['fecha'],
            'saldo_inicial' => $validatedData['saldo_inicial'],
            'ingresos' => $validatedData['ingresos'],
            'egresos' => $validatedData['egresos'],
            'saldo_final' => $validatedData['saldo_final'],
            'observaciones' => $request->observaciones,
        ]);

        return redirect()->route("cierre-caja.index", ['mensaje' => 'Se cerro la caja correctamente']);
    }

    public function cierreCajaAutomatico()
    {
        $fecha = Carbon::yesterday()->toDateString();

        if (CierreCaja::whereDate('fecha', $fecha)->exists()) {
            return response()->json(['message' => 'Ya existe cierre para ' . $fecha], 200);
        }

        $ultimoCierre = CierreCaja::latest()->first();
        $saldoInicial = $ultimoCierre->saldo_final ?? 0;

        $ingresos = Ventas::whereDate('created_at', $fecha)->sum('precio');
        $movimientoIngresoHoy = Movimientos::whereDate('created_at', $fecha)
            ->where('tipo', 'Ingreso')
            ->sum('valor');

        $egresos = Movimientos::whereDate('created_at', $fecha)
            ->where('tipo', 'Egreso')
            ->sum('valor');

        $retiroUtilidades = Movimientos::whereDate('created_at', $fecha)
            ->where('tipo', 'Retiro Utilidades')
            ->sum('valor');

        $saldoFinal = $saldoInicial + ($ingresos + $movimientoIngresoHoy - $egresos - $retiroUtilidades);

        CierreCaja::create([
            'fecha' => $fecha,
            'saldo_inicial' => $saldoInicial,
            'ingresos' => $ingresos + $movimientoIngresoHoy,
            'egresos' => $egresos,
            'saldo_final' => $saldoFinal,
        ]);

        return response()->json(['message' => 'Cierre creado', 'fecha' => $fecha], 201);
    }

    public function destroy(string $id)
    {
        $cierreCaja = CierreCaja::findOrFail($id);
        $cierreCaja->delete();

        return redirect()->back();
    }
}
