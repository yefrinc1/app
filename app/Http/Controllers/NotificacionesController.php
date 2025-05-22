<?php

namespace App\Http\Controllers;

use App\Models\Notificaciones;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NotificacionesController extends Controller
{
    public function index()
    {
        $notificaciones = DB::table('notificaciones')
        ->leftJoin('correo_juegos', 'notificaciones.id_correo_juego', '=', 'correo_juegos.id')
        ->select('notificaciones.*', 'correo_juegos.correo')
        ->orderBy('notificaciones.created_at')
        ->get();

        return Inertia::render('Notificaciones/Index', ["notificaciones" => $notificaciones]);
    }

    public function destroy(string $id)
    {
        $notificacion = Notificaciones::findOrFail($id);
        $notificacion->delete();

        return redirect()->back();
    }

}
