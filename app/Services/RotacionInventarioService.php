<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RotacionInventarioService
{
    public const ESTADOS = ['alta', 'media', 'lenta', 'detenida', 'nuevo'];

    public function analizar(array $filtros): array
    {
        $dias = in_array((int) ($filtros['periodo'] ?? 90), [30, 60, 90, 180, 365], true)
            ? (int) $filtros['periodo']
            : 90;
        $desde = CarbonImmutable::today()->subDays($dias - 1)->startOfDay();
        $hasta = CarbonImmutable::today()->endOfDay();

        $ventas = DB::table('ventas')
            ->join('correo_juegos', 'correo_juegos.id', '=', 'ventas.id_correo_juego')
            ->where('ventas.estado', 'activa')
            ->whereBetween('ventas.created_at', [$desde, $hasta])
            ->whereNotNull('correo_juegos.juego')
            ->selectRaw("correo_juegos.juego, COUNT(ventas.id) ventas,
                SUM(CASE WHEN ventas.consola = 'PS4' THEN 1 ELSE 0 END) ventas_ps4,
                SUM(CASE WHEN ventas.consola = 'PS5' THEN 1 ELSE 0 END) ventas_ps5")
            ->groupBy('correo_juegos.juego')->get()->keyBy('juego');

        $ultimasVentas = DB::table('ventas')
            ->join('correo_juegos', 'correo_juegos.id', '=', 'ventas.id_correo_juego')
            ->where('ventas.estado', 'activa')
            ->whereNotNull('correo_juegos.juego')
            ->selectRaw('correo_juegos.juego, MAX(ventas.created_at) ultima_venta')
            ->groupBy('correo_juegos.juego')->get()->keyBy('juego');

        $catalogo = DB::table('juegos')->get()->keyBy(fn ($juego) => mb_strtolower(trim($juego->nombre)));

        $filas = DB::table('correo_juegos')
            ->where('disponible', 1)
            ->where('es_cuenta_madre', 0)
            ->whereNotNull('juego')
            ->selectRaw("juego, COUNT(*) cuentas,
                SUM(GREATEST(0, 2 - primaria_ps4))
                  + SUM(CASE WHEN primaria_ps4 = 2 AND secundaria = 0 THEN 1 ELSE 0 END) cupos_ps4,
                SUM(GREATEST(0, 2 - primaria_ps5))
                  + SUM(CASE WHEN primaria_ps5 = 2 AND secundaria = 0 THEN 1 ELSE 0 END) cupos_ps5,
                SUM(COALESCE(precio_cop, 0)) valor_inventario,
                AVG(COALESCE(precio_cop, 0)) costo_promedio,
                ROUND(AVG(DATEDIFF(CURDATE(), created_at)), 0) antiguedad_promedio,
                MIN(created_at) cuenta_mas_antigua")
            ->groupBy('juego')->get()
            ->map(function ($stock) use ($ventas, $ultimasVentas, $catalogo, $dias) {
                $venta = $ventas->get($stock->juego);
                $ultimaVenta = $ultimasVentas->get($stock->juego)->ultima_venta ?? null;
                $cantidadVentas = (int) ($venta->ventas ?? 0);
                $cupos = (int) $stock->cupos_ps4 + (int) $stock->cupos_ps5;
                $velocidad = $cantidadVentas / $dias;
                $cobertura = $velocidad > 0 ? round($cupos / $velocidad, 1) : null;
                $antiguedad = (int) $stock->antiguedad_promedio;
                $estado = $this->estado($cantidadVentas, $cobertura, $antiguedad);
                $catalogoJuego = $catalogo->get(mb_strtolower(trim($stock->juego)));

                return [
                    'juego' => $stock->juego,
                    'imagen' => $catalogoJuego->url_imagen ?? null,
                    'cuentas' => (int) $stock->cuentas,
                    'cupos' => $cupos,
                    'cupos_ps4' => (int) $stock->cupos_ps4,
                    'cupos_ps5' => (int) $stock->cupos_ps5,
                    'ventas' => $cantidadVentas,
                    'ventas_ps4' => (int) ($venta->ventas_ps4 ?? 0),
                    'ventas_ps5' => (int) ($venta->ventas_ps5 ?? 0),
                    'velocidad_diaria' => round($velocidad, 2),
                    'indice_rotacion' => $cupos > 0 ? round($cantidadVentas / $cupos, 2) : ($cantidadVentas > 0 ? $cantidadVentas : 0),
                    'cobertura' => $cobertura,
                    'antiguedad_promedio' => $antiguedad,
                    'cuenta_mas_antigua' => $stock->cuenta_mas_antigua,
                    'ultima_venta' => $ultimaVenta,
                    'dias_sin_venta' => $ultimaVenta ? CarbonImmutable::parse($ultimaVenta)->startOfDay()->diffInDays(CarbonImmutable::today()) : null,
                    'valor_inventario' => (int) round($stock->valor_inventario),
                    'costo_promedio' => (int) round($stock->costo_promedio),
                    'estado' => $estado,
                ];
            });

        if (! empty($filtros['juego'])) {
            $busqueda = mb_strtolower($filtros['juego']);
            $filas = $filas->filter(fn ($fila) => str_contains(mb_strtolower($fila['juego']), $busqueda));
        }
        if (! empty($filtros['estado'])) {
            $filas = $filas->where('estado', $filtros['estado']);
        }

        $orden = in_array($filtros['orden'] ?? '', ['juego', 'ventas', 'cuentas', 'cupos', 'cobertura', 'antiguedad_promedio', 'valor_inventario', 'estado'], true)
            ? $filtros['orden']
            : 'valor_inventario';
        $direccion = ($filtros['direccion'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $filas = $filas->sortBy($orden, SORT_NATURAL | SORT_FLAG_CASE, $direccion === 'desc')->values();

        return [
            'filas' => $filas,
            'resumen' => $this->resumen($filas),
            'graficos' => $this->graficos($filas),
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'dias' => $dias],
        ];
    }

    public function estado(int $ventas, ?float $cobertura, int $antiguedad): string
    {
        if ($ventas === 0) {
            return $antiguedad <= 30 ? 'nuevo' : 'detenida';
        }
        if ($cobertura !== null && $cobertura <= 30) {
            return 'alta';
        }
        if ($cobertura !== null && $cobertura <= 60) {
            return 'media';
        }
        if ($cobertura !== null && $cobertura <= 120) {
            return 'lenta';
        }

        return 'detenida';
    }

    private function resumen(Collection $filas): array
    {
        $riesgo = $filas->whereIn('estado', ['lenta', 'detenida']);

        return [
            'juegos' => $filas->count(),
            'cuentas' => $filas->sum('cuentas'),
            'cupos' => $filas->sum('cupos'),
            'ventas' => $filas->sum('ventas'),
            'valor_inventario' => $filas->sum('valor_inventario'),
            'capital_riesgo' => $riesgo->sum('valor_inventario'),
            'detenidos' => $filas->where('estado', 'detenida')->count(),
            'rotacion_alta' => $filas->where('estado', 'alta')->count(),
        ];
    }

    private function graficos(Collection $filas): array
    {
        return [
            'capital_estado' => collect(self::ESTADOS)->map(fn ($estado) => [
                'estado' => $estado,
                'valor' => $filas->where('estado', $estado)->sum('valor_inventario'),
            ])->values(),
            'capital_detenido' => $filas->whereIn('estado', ['detenida', 'lenta'])
                ->sortByDesc('valor_inventario')->take(10)->values()->map(fn ($fila) => [
                    'juego' => $fila['juego'], 'valor' => $fila['valor_inventario'], 'estado' => $fila['estado'],
                ]),
            'ventas_inventario' => $filas->sortByDesc('ventas')->take(10)->values()->map(fn ($fila) => [
                'juego' => $fila['juego'], 'ventas' => $fila['ventas'], 'cupos' => $fila['cupos'],
            ]),
        ];
    }
}
