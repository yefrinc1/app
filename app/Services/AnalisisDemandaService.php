<?php

namespace App\Services;

use App\Models\CorreoJuego;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalisisDemandaService
{
    public const ESTADOS = ['agotado', 'critico', 'bajo', 'saludable', 'excedente', 'sin_demanda'];

    public function analizar(array $filtros): array
    {
        [$desde, $hasta] = $this->periodo($filtros);
        $dias = $desde->diffInDays($hasta) + 1;
        $diasObjetivo = max(1, (int) ($filtros['dias_objetivo'] ?? 30));
        $seguridad = max(0, min(100, (float) ($filtros['seguridad'] ?? 20))) / 100;
        $consola = $filtros['consola'] ?? null;
        $licencia = $filtros['licencia'] ?? null;

        $ventas = $this->ventasPorJuego($desde, $hasta, $consola, $licencia);
        $previoHasta = $desde->subDay();
        $previoDesde = $previoHasta->subDays($dias - 1);
        $ventasPrevias = $this->ventasPorJuego($previoDesde, $previoHasta, $consola, $licencia)
            ->keyBy('juego');
        $ventas7 = $this->ventasPorJuego($hasta->subDays(6), $hasta, $consola, $licencia)->keyBy('juego');
        $ventas30 = $this->ventasPorJuego($hasta->subDays(29), $hasta, $consola, $licencia)->keyBy('juego');
        $inventario = $this->inventarioActual($licencia)->keyBy('juego');
        $catalogo = DB::table('juegos')->get()->keyBy(fn ($juego) => mb_strtolower(trim($juego->nombre)));

        $filas = $ventas->map(function ($venta) use (
            $ventasPrevias, $ventas7, $ventas30, $inventario, $catalogo, $dias, $diasObjetivo, $seguridad, $consola
        ) {
            $stock = $inventario->get($venta->juego);
            $juegoCatalogo = $catalogo->get(mb_strtolower(trim($venta->juego)));
            $compatiblePs4 = ! $juegoCatalogo || str_contains(mb_strtoupper((string) $juegoCatalogo->consola), 'PS4');
            $disponiblePs4 = $compatiblePs4 && $consola !== 'PS5' ? (int) ($stock->cupos_ps4 ?? 0) : 0;
            $disponiblePs5 = $consola !== 'PS4' ? (int) ($stock->cupos_ps5 ?? 0) : 0;

            $ps4 = $this->proyeccion((int) $venta->ventas_ps4, $dias, $diasObjetivo, $seguridad, $disponiblePs4);
            $ps5 = $this->proyeccion((int) $venta->ventas_ps5, $dias, $diasObjetivo, $seguridad, $disponiblePs5);
            $recomendadasPs4 = (int) ceil($ps4['faltantes'] / 2);
            $recomendadasPs5 = (int) ceil($ps5['faltantes'] / 2);
            // La misma cuenta ofrece capacidad PS4 y PS5; no se suman las compras.
            $recomendadas = max($recomendadasPs4, $recomendadasPs5);
            $previas = (int) ($ventasPrevias->get($venta->juego)->ventas_totales ?? 0);
            $variacion = $previas === 0
                ? ((int) $venta->ventas_totales > 0 ? 100.0 : 0.0)
                : round((((int) $venta->ventas_totales - $previas) / $previas) * 100, 1);
            $estado = $this->estadoGeneral($ps4, $ps5);
            $costo = (int) round((float) ($stock->costo_promedio ?? 0));

            return [
                'juego' => $venta->juego,
                'imagen' => $juegoCatalogo->url_imagen ?? null,
                'ventas_totales' => (int) $venta->ventas_totales,
                'ventas_ps4' => (int) $venta->ventas_ps4,
                'ventas_ps5' => (int) $venta->ventas_ps5,
                'ventas_primaria' => (int) $venta->ventas_primaria,
                'ventas_secundaria' => (int) $venta->ventas_secundaria,
                'promedio_diario' => round((int) $venta->ventas_totales / $dias, 2),
                'cuentas' => (int) ($stock->cuentas ?? 0),
                'cupos_ps4' => $disponiblePs4,
                'cupos_ps5' => $disponiblePs5,
                'cupos_primarios_ps4' => $compatiblePs4 && $consola !== 'PS5' ? (int) ($stock->primarios_ps4 ?? 0) : 0,
                'cupos_primarios_ps5' => $consola !== 'PS4' ? (int) ($stock->primarios_ps5 ?? 0) : 0,
                'cupos_secundarios_ps4' => $compatiblePs4 && $consola !== 'PS5' ? (int) ($stock->secundarios_ps4 ?? 0) : 0,
                'cupos_secundarios_ps5' => $consola !== 'PS4' ? (int) ($stock->secundarios_ps5 ?? 0) : 0,
                'ps4' => $ps4,
                'ps5' => $ps5,
                'demanda_estimada' => $ps4['objetivo'] + $ps5['objetivo'],
                'cuentas_recomendadas_ps4' => $recomendadasPs4,
                'cuentas_recomendadas_ps5' => $recomendadasPs5,
                'cuentas_recomendadas' => $recomendadas,
                'cupos_post_compra_ps4' => $disponiblePs4 + ($recomendadas * 2),
                'cupos_post_compra_ps5' => $disponiblePs5 + ($recomendadas * 2),
                'estado' => $estado,
                'variacion' => $variacion,
                'tendencia' => $variacion > 5 ? 'creciente' : ($variacion < -5 ? 'decreciente' : 'estable'),
                'ventas_7' => (int) ($ventas7->get($venta->juego)->ventas_totales ?? 0),
                'ventas_30' => (int) ($ventas30->get($venta->juego)->ventas_totales ?? 0),
                'costo_unitario' => $costo,
                'inversion_estimada' => $costo * $recomendadas,
            ];
        });

        if (! empty($filtros['juego'])) {
            $busqueda = mb_strtolower($filtros['juego']);
            $filas = $filas->filter(fn ($fila) => str_contains(mb_strtolower($fila['juego']), $busqueda));
        }
        if (! empty($filtros['estado'])) {
            $filas = $filas->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['reposicion'])) {
            $filas = $filas->filter(fn ($fila) => (int) $fila['cuentas_recomendadas'] > 0);
        }

        $orden = in_array($filtros['orden'] ?? '', ['juego', 'ventas_totales', 'cupos_ps4', 'cupos_ps5', 'demanda_estimada', 'cuentas_recomendadas', 'estado'], true)
            ? $filtros['orden'] : 'ventas_totales';
        $direccion = ($filtros['direccion'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $filas = $filas->sortBy($orden, SORT_NATURAL | SORT_FLAG_CASE, $direccion === 'desc')->values();

        return [
            'filas' => $filas,
            'resumen' => $this->resumen($filas),
            'graficos' => $this->graficos($filas, $desde, $hasta, $consola, $licencia),
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'dias' => $dias],
        ];
    }

    public function detalle(string $juego, array $filtros): array
    {
        $analisis = $this->analizar(array_merge($filtros, ['juego' => $juego]));
        $fila = $analisis['filas']->firstWhere('juego', $juego);
        abort_unless($fila, 404);
        [$desde, $hasta] = $this->periodo($filtros);

        $historial = DB::table('ventas')
            ->join('correo_juegos', 'correo_juegos.id', '=', 'ventas.id_correo_juego')
            ->where('correo_juegos.juego', $juego)->where('ventas.estado', 'activa')
            ->whereBetween('ventas.created_at', [$desde->startOfDay(), $hasta->endOfDay()])
            ->select('ventas.id', 'ventas.created_at', 'ventas.consola', 'ventas.tipo_cuenta')
            ->orderByDesc('ventas.created_at')->limit(100)->get();
        $diarias = DB::table('ventas')
            ->join('correo_juegos', 'correo_juegos.id', '=', 'ventas.id_correo_juego')
            ->where('correo_juegos.juego', $juego)->where('ventas.estado', 'activa')
            ->whereBetween('ventas.created_at', [$desde->startOfDay(), $hasta->endOfDay()])
            ->selectRaw('DATE(ventas.created_at) fecha, COUNT(*) ventas')->groupByRaw('DATE(ventas.created_at)')
            ->orderBy('fecha')->get();
        $cuentas = CorreoJuego::query()->where('juego', $juego)
            ->select('id', 'primaria_ps4', 'primaria_ps5', 'secundaria', 'disponible', 'precio_cop')
            ->orderByDesc('disponible')->get()->map(fn ($cuenta) => [
                'id' => $cuenta->id,
                'estado' => $cuenta->disponible ? 'Comercializable' : 'No disponible',
                'ocupados_ps4' => $cuenta->primaria_ps4,
                'disponibles_ps4' => $cuenta->disponible ? max(0, 2 - $cuenta->primaria_ps4) : 0,
                'ocupados_ps5' => $cuenta->primaria_ps5,
                'disponibles_ps5' => $cuenta->disponible ? max(0, 2 - $cuenta->primaria_ps5) : 0,
                'secundaria_ocupada' => (bool) $cuenta->secundaria,
                'costo' => $cuenta->precio_cop,
            ]);

        return compact('fila', 'historial', 'diarias', 'cuentas');
    }

    public function periodo(array $filtros): array
    {
        $hoy = CarbonImmutable::today();
        if (($filtros['periodo'] ?? '30') === 'personalizado' && ! empty($filtros['desde']) && ! empty($filtros['hasta'])) {
            $desde = CarbonImmutable::parse($filtros['desde']);
            $hasta = CarbonImmutable::parse($filtros['hasta']);

            return $desde->lte($hasta) ? [$desde, $hasta] : [$hasta, $desde];
        }
        $dias = in_array((int) ($filtros['periodo'] ?? 30), [7, 15, 30, 60, 90], true) ? (int) $filtros['periodo'] : 30;

        return [$hoy->subDays($dias - 1), $hoy];
    }

    public function proyeccion(int $ventas, int $dias, int $objetivoDias, float $seguridad, int $disponibles): array
    {
        $promedio = $dias > 0 ? $ventas / $dias : 0;
        $demanda = $promedio * $objetivoDias;
        $objetivo = (int) ceil($demanda * (1 + $seguridad));
        $faltantes = max(0, $objetivo - $disponibles);
        $cobertura = $promedio > 0 ? round($disponibles / $promedio, 1) : null;
        $estado = $this->estado($ventas, $disponibles, $cobertura, $objetivo);

        return [
            'ventas' => $ventas, 'promedio' => round($promedio, 2), 'demanda' => round($demanda, 2),
            'objetivo' => $objetivo, 'disponibles' => $disponibles, 'faltantes' => $faltantes,
            'cobertura' => $cobertura, 'agotamiento' => $cobertura === null ? null : CarbonImmutable::today()->addDays((int) floor($cobertura))->toDateString(),
            'estado' => $estado,
        ];
    }

    private function ventasPorJuego(CarbonImmutable $desde, CarbonImmutable $hasta, ?string $consola, ?string $licencia): Collection
    {
        return DB::table('ventas')->join('correo_juegos', 'correo_juegos.id', '=', 'ventas.id_correo_juego')
            ->where('ventas.estado', 'activa')
            ->whereBetween('ventas.created_at', [$desde->startOfDay(), $hasta->endOfDay()])
            ->when($consola, fn ($q) => $q->where('ventas.consola', $consola))
            ->when($licencia, fn ($q) => $q->where('ventas.tipo_cuenta', $licencia))
            ->whereNotNull('correo_juegos.juego')
            ->selectRaw("correo_juegos.juego, COUNT(ventas.id) ventas_totales,
                SUM(CASE WHEN ventas.consola = 'PS4' THEN 1 ELSE 0 END) ventas_ps4,
                SUM(CASE WHEN ventas.consola = 'PS5' THEN 1 ELSE 0 END) ventas_ps5,
                SUM(CASE WHEN ventas.tipo_cuenta = 'Primaria' THEN 1 ELSE 0 END) ventas_primaria,
                SUM(CASE WHEN ventas.tipo_cuenta = 'Secundaria' THEN 1 ELSE 0 END) ventas_secundaria")
            ->groupBy('correo_juegos.juego')->get();
    }

    private function inventarioActual(?string $licencia): Collection
    {
        return DB::table('correo_juegos')->where('disponible', 1)->where('es_cuenta_madre', 0)
            ->whereNotNull('juego')->selectRaw("juego, COUNT(*) cuentas,
                SUM(CASE WHEN ? IS NULL OR ? = 'Primaria' THEN GREATEST(0, 2 - primaria_ps4) ELSE 0 END)
                  + SUM(CASE WHEN (? IS NULL OR ? = 'Secundaria') AND primaria_ps4 = 2 AND secundaria = 0 THEN 1 ELSE 0 END) cupos_ps4,
                SUM(CASE WHEN ? IS NULL OR ? = 'Primaria' THEN GREATEST(0, 2 - primaria_ps5) ELSE 0 END)
                  + SUM(CASE WHEN (? IS NULL OR ? = 'Secundaria') AND primaria_ps5 = 2 AND secundaria = 0 THEN 1 ELSE 0 END) cupos_ps5,
                SUM(GREATEST(0, 2 - primaria_ps4)) primarios_ps4,
                SUM(GREATEST(0, 2 - primaria_ps5)) primarios_ps5,
                SUM(CASE WHEN primaria_ps4 = 2 AND secundaria = 0 THEN 1 ELSE 0 END) secundarios_ps4,
                SUM(CASE WHEN primaria_ps5 = 2 AND secundaria = 0 THEN 1 ELSE 0 END) secundarios_ps5,
                AVG(NULLIF(precio_cop, 0)) costo_promedio", [$licencia, $licencia, $licencia, $licencia, $licencia, $licencia, $licencia, $licencia])
            ->groupBy('juego')->get();
    }

    private function estado(int $ventas, int $disponibles, ?float $cobertura, int $objetivo): string
    {
        if ($ventas === 0) {
            return 'sin_demanda';
        }
        if ($disponibles === 0) {
            return 'agotado';
        }
        if ($cobertura <= 3) {
            return 'critico';
        }
        if ($objetivo > 0 && $disponibles > ($objetivo * 2)) {
            return 'excedente';
        }
        if ($disponibles < $objetivo) {
            return 'bajo';
        }

        return 'saludable';
    }

    private function estadoGeneral(array $ps4, array $ps5): string
    {
        $prioridad = array_flip(['agotado', 'critico', 'bajo', 'saludable', 'excedente', 'sin_demanda']);

        return collect([$ps4, $ps5])->where('ventas', '>', 0)->pluck('estado')
            ->sortBy(fn ($estado) => $prioridad[$estado])->first() ?? 'sin_demanda';
    }

    private function resumen(Collection $filas): array
    {
        return [
            'juegos' => $filas->count(), 'ventas' => $filas->sum('ventas_totales'),
            'cupos_ps4' => $filas->sum('cupos_ps4'), 'cupos_ps5' => $filas->sum('cupos_ps5'),
            'agotados' => $filas->where('estado', 'agotado')->count(),
            'criticos' => $filas->where('estado', 'critico')->count(),
            'reposicion' => $filas->where('cuentas_recomendadas', '>', 0)->count(),
            'cuentas_recomendadas' => $filas->sum('cuentas_recomendadas'),
            'inversion_estimada' => $filas->sum('inversion_estimada'),
        ];
    }

    private function graficos(Collection $filas, CarbonImmutable $desde, CarbonImmutable $hasta, ?string $consola, ?string $licencia): array
    {
        $evolucion = $filas->isEmpty() ? collect() : DB::table('ventas')
            ->join('correo_juegos', 'correo_juegos.id', '=', 'ventas.id_correo_juego')
            ->where('ventas.estado', 'activa')
            ->whereIn('correo_juegos.juego', $filas->pluck('juego'))
            ->whereBetween('ventas.created_at', [$desde->startOfDay(), $hasta->endOfDay()])
            ->when($consola, fn ($q) => $q->where('ventas.consola', $consola))
            ->when($licencia, fn ($q) => $q->where('ventas.tipo_cuenta', $licencia))
            ->selectRaw('DATE(ventas.created_at) fecha, COUNT(*) ventas')->groupByRaw('DATE(ventas.created_at)')->orderBy('fecha')->get();

        return [
            'top' => $filas->sortByDesc('ventas_totales')->take(10)->values()->map(fn ($fila) => [
                'juego' => $fila['juego'], 'ventas_totales' => $fila['ventas_totales'],
            ]),
            'consolas' => ['ps4' => $filas->sum('ventas_ps4'), 'ps5' => $filas->sum('ventas_ps5')],
            'demanda_inventario' => $filas->sortByDesc('demanda_estimada')->take(10)->values()->map(fn ($f) => [
                'juego' => $f['juego'], 'demanda' => $f['demanda_estimada'], 'inventario' => $f['cupos_ps4'] + $f['cupos_ps5'],
            ]),
            'evolucion' => $evolucion,
        ];
    }
}
