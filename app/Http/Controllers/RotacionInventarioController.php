<?php

namespace App\Http\Controllers;

use App\Services\RotacionInventarioService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class RotacionInventarioController extends Controller
{
    public function __construct(private RotacionInventarioService $rotacion)
    {
        $this->middleware('can:inventario.rotacion.ver');
    }

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $resultado = $this->rotacion->analizar($filtros);
        $pagina = max(1, (int) $request->input('page', 1));
        $porPagina = 15;
        $paginador = new LengthAwarePaginator(
            $resultado['filas']->forPage($pagina, $porPagina)->values(),
            $resultado['filas']->count(),
            $porPagina,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Inventario/RotacionInventario', [
            'resultados' => $paginador,
            'resumen' => $resultado['resumen'],
            'graficos' => $resultado['graficos'],
            'periodo' => $resultado['periodo'],
            'filtros' => $filtros,
        ]);
    }

    public function exportar(Request $request)
    {
        $resultado = $this->rotacion->analizar($this->filtros($request));

        return response()->streamDownload(function () use ($resultado) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Videojuego', 'Estado', 'Cuentas', 'Cupos PS4', 'Cupos PS5', 'Ventas', 'Cobertura dias', 'Antiguedad promedio', 'Ultima venta', 'Valor inventario COP'], ';');
            foreach ($resultado['filas'] as $fila) {
                fputcsv($salida, [
                    $fila['juego'], $fila['estado'], $fila['cuentas'], $fila['cupos_ps4'], $fila['cupos_ps5'],
                    $fila['ventas'], $fila['cobertura'], $fila['antiguedad_promedio'], $fila['ultima_venta'], $fila['valor_inventario'],
                ], ';');
            }
            fclose($salida);
        }, 'rotacion-inventario-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtros(Request $request): array
    {
        $validos = $request->validate([
            'periodo' => ['nullable', 'in:30,60,90,180,365'],
            'juego' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', 'in:alta,media,lenta,detenida,nuevo'],
            'orden' => ['nullable', 'string'],
            'direccion' => ['nullable', 'in:asc,desc'],
        ]);

        return array_merge(['periodo' => '90', 'orden' => 'valor_inventario', 'direccion' => 'desc'], $validos);
    }
}
