<?php

namespace App\Http\Controllers;

use App\Services\AnalisisDemandaService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class AnalisisDemandaController extends Controller
{
    public function __construct(private AnalisisDemandaService $analisis)
    {
        $this->middleware('can:inventario.analisis.ver');
    }

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $resultado = $this->analisis->analizar($filtros);
        $pagina = max(1, (int) $request->input('page', 1));
        $porPagina = 15;
        $paginador = new LengthAwarePaginator(
            $resultado['filas']->forPage($pagina, $porPagina)->values(),
            $resultado['filas']->count(), $porPagina, $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $prioridad = array_flip(['agotado', 'critico', 'bajo', 'saludable', 'excedente', 'sin_demanda']);
        $planCompras = $resultado['filas']->where('cuentas_recomendadas', '>', 0)
            ->sortBy(fn ($fila) => ($prioridad[$fila['estado']] * 100000)
                + min($fila['ps4']['cobertura'] ?? 99999, $fila['ps5']['cobertura'] ?? 99999))
            ->values();

        return Inertia::render('Inventario/AnalisisDemanda', [
            'resultados' => $paginador,
            'planCompras' => $planCompras,
            'resumen' => $resultado['resumen'],
            'graficos' => $resultado['graficos'],
            'periodo' => $resultado['periodo'],
            'filtros' => $filtros,
        ]);
    }

    public function show(Request $request, string $juego)
    {
        return response()->json($this->analisis->detalle($juego, $this->filtros($request)));
    }

    public function exportar(Request $request)
    {
        $resultado = $this->analisis->analizar(array_merge($this->filtros($request), ['reposicion' => true]));

        return response()->streamDownload(function () use ($resultado) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Videojuego', 'Prioridad', 'Ventas', 'Cupos PS4', 'Cupos PS5', 'Demanda', 'Cuentas sugeridas', 'Costo unitario COP', 'Inversion COP'], ';');
            foreach ($resultado['filas'] as $fila) {
                fputcsv($salida, [$fila['juego'], $fila['estado'], $fila['ventas_totales'], $fila['cupos_ps4'], $fila['cupos_ps5'], $fila['demanda_estimada'], $fila['cuentas_recomendadas'], $fila['costo_unitario'], $fila['inversion_estimada']], ';');
            }
            fclose($salida);
        }, 'plan-compras-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtros(Request $request): array
    {
        $validos = $request->validate([
            'periodo' => ['nullable', 'in:7,15,30,60,90,personalizado'],
            'desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date'],
            'consola' => ['nullable', 'in:PS4,PS5'], 'licencia' => ['nullable', 'in:Primaria,Secundaria'],
            'juego' => ['nullable', 'string', 'max:150'], 'estado' => ['nullable', 'in:agotado,critico,bajo,saludable,excedente,sin_demanda'],
            'reposicion' => ['nullable', 'boolean'], 'dias_objetivo' => ['nullable', 'integer', 'min:1', 'max:365'],
            'seguridad' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'orden' => ['nullable', 'string'], 'direccion' => ['nullable', 'in:asc,desc'],
        ]);

        return array_merge(['periodo' => '30', 'dias_objetivo' => 30, 'seguridad' => 20, 'direccion' => 'desc'], $validos);
    }
}
