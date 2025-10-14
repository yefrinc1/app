<?php

namespace App\Http\Controllers;

use App\Models\CorreoJuego;
use App\Models\ResumenMensual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EstadisticaController extends Controller
{
    public function estadisticaJuegos(Request $request)
    {
        $search = $request->get('search', '');

        $juegos = DB::table(DB::raw("(SELECT juego, SUM(precio_cop) AS total_comprado
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
                'v.fecha_minima',
                'v.fecha_maxima'
            )
            ->whereNotNull('v.total_vendido')
            ->where('c.juego', 'like', "%{$search}%")
            ->orderByDesc('diferencia')
            ->paginate(10);

        return Inertia::render('Estadistica/RentabilidadJuegos', [
            'juegos' => $juegos,
            'search' => $search,
        ]);
    }

    public function resumenMensual(){
        $resumenMensual = ResumenMensual::orderByDesc('id')->paginate(10);
        return Inertia::render('Estadistica/ResumenMensual', ['resumen_mensual' => $resumenMensual]);
    }
}
