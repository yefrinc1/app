<?php

namespace App\Http\Controllers;

use App\Models\CodigoVerificacion;
use App\Models\CorreoJuego;
use App\Models\CorreoMadre;
use App\Models\Notificaciones;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CodigoVerificacionController extends Controller
{
    public function index()
    {
        return Inertia::render('CodigoVerificacion/Index');
    }

    public function create(Request $request)
    {
        $correo = $request->input('correo');
        return Inertia::render('CodigoVerificacion/Create', ['correo' => $correo]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'correo' => 'required|exists:correo_globales,correo',
            'codigo' => 'required',
        ]);

        $correoMadre = CorreoMadre::where('correo', $validatedData['correo'])->first();

        if ($correoMadre != null) {
            CodigoVerificacion::where('id_correo_madre', $correoMadre->id)->delete();

            $codigos = CodigoVerificacion::separarCodigos($validatedData['codigo']);
    
            $codigoRespaldo = 1;
            foreach ($codigos as $clave => $codigo) {
                if ($clave > 0 && $codigoRespaldo == 1) {
                    $codigoRespaldo = 0;
                }
                CodigoVerificacion::create([
                    'codigo' => $codigo,
                    'id_correo_madre' => $correoMadre->id,
                    'respaldo' => $codigoRespaldo,
                ]);
            }
        } else {
            $correoJuego = CorreoJuego::where('correo', $validatedData['correo'])->first();
            CodigoVerificacion::where('id_correo_juego', $correoJuego->id)->delete();

            $codigos = CodigoVerificacion::separarCodigos($validatedData['codigo']);
    
            $codigoRespaldo = 1;
            foreach ($codigos as $clave => $codigo) {
                if ($clave > 0 && $codigoRespaldo == 1) {
                    $codigoRespaldo = 0;
                }
                CodigoVerificacion::create([
                    'codigo' => $codigo,
                    'id_correo_juego' => $correoJuego->id,
                    'respaldo' => $codigoRespaldo,
                ]);
            }

            $notificacionExiste = Notificaciones::where('id_correo_juego', $correoJuego->id)
            ->where('tipo', 'crear_codigos')
            ->where('mensaje', 'Se necesita crear codigos')
            ->first();

            if ($notificacionExiste) {
                $notificacionExiste->delete();
            }
        }

        return redirect()->back();
    }

    public function destroy(string $id)
    {
        $codigoVerificacion = CodigoVerificacion::findOrFail($id);
        $codigoVerificacion->delete();

        return redirect()->back();
    }

    public function buscar(Request $request)
    {   
        $query = $request->input('q');
        $correos = CorreoMadre::where('correo', 'like', "%{$query}%")->limit(5)->get();

        if ($correos->isEmpty()) {
            $correos = CorreoJuego::where('correo', 'like', "%{$query}%")->limit(5)->get();
        }
        
        return response()->json($correos);
    }

    public function consultarCodigo(Request $request)
    {
        $correo = $request->input('correo');
        $correoMadre = CorreoMadre::where('correo', $correo)->first();
        $codigoFinal = "N/A";

        if ($correoMadre != null) {
            $codigoVerificacion = CodigoVerificacion::where('disponible', 1)
            ->where('respaldo', 1)
            ->where('id_correo_madre', $correoMadre->id)
            ->first();

            if ($codigoVerificacion != null) {
                $codigoFinal = $codigoVerificacion->codigo;
            }
        } else {
            $correoJuego = CorreoJuego::where('correo', $correo)->first();
            if ($correoJuego != null) {
                $codigoVerificacion = CodigoVerificacion::where('disponible', 1)
                ->where('respaldo', 1)
                ->where('id_correo_juego', $correoJuego->id)
                ->first();
    
                if ($codigoVerificacion != null) {
                    $codigoFinal = $codigoVerificacion->codigo;
                }
            }
        }

        return response()->json($codigoFinal);
    }

    public function consultarTodosCodigos(Request $request)
    {
        $correo = $request->input('correo');
        $correoMadre = CorreoMadre::where('correo', $correo)->first();
        $codigos = "";

        if ($correoMadre != null) {
            $codigoVerificacion = CodigoVerificacion::where('disponible', 1)
            ->where('id_correo_madre', $correoMadre->id)
            ->get();

            if (!$codigoVerificacion->isEmpty()) {
                $codigos = $codigoVerificacion;
            }
        } else {
            $correoJuego = CorreoJuego::where('correo', $correo)->first();
            if ($correoJuego != null) {
                $codigoVerificacion = CodigoVerificacion::where('disponible', 1)
                ->where('id_correo_juego', $correoJuego->id)
                ->get();
    
                if (!$codigoVerificacion->isEmpty()) {
                    $codigos = $codigoVerificacion;
                }
            }
        }

        return response()->json($codigos);
    }

    public function generarCodigo()
    {
        return Inertia::render('CodigoVerificacion/GenerarCodigo');
    }

    public function generarCodigoDisponible(Request $request)
    {
        $correo = $request->input('correo');
        $ultimoCodigo = false;
        $respuestaCodigo = [];
        $correoMadre = CorreoMadre::where('correo', $correo)->first();

        if ($correoMadre != null) {
            $codigoVerificacion = CodigoVerificacion::where('disponible', 1)
            ->where('respaldo', 0)
            ->where('id_correo_madre', $correoMadre->id)
            ->first();

            if ($codigoVerificacion != null) {
                $codigoVerificacion->update(['disponible' => 0]);

                $codigoVerificacion2 = CodigoVerificacion::where('disponible', 1)
                ->where('respaldo', 0)
                ->where('id_correo_madre', $correoMadre->id)
                ->first();

                $ultimoCodigo = $codigoVerificacion2 == null;
                $respuestaCodigo = ['codigo' => $codigoVerificacion->codigo, 'ultimo_codigo' => $ultimoCodigo];
            } else {
                $codigoRespaldo = CodigoVerificacion::where('disponible', 1)
                ->where('respaldo', 1)
                ->where('id_correo_madre', $correoMadre->id)
                ->first();

                if ($codigoRespaldo != null) {
                    $respuestaCodigo = ['mensaje' => 'Los codigos para este correo ya se acabaron'];
                }
            }
        } else {
            $correoJuego = CorreoJuego::where('correo', $correo)->first();
            if ($correoJuego != null) {
                $codigoVerificacion = CodigoVerificacion::where('disponible', 1)
                ->where('respaldo', 0)
                ->where('id_correo_juego', $correoJuego->id)
                ->first();
    
                if ($codigoVerificacion != null) {
                    $codigoVerificacion->update(['disponible' => 0]);
    
                    $codigoVerificacion2 = CodigoVerificacion::where('disponible', 1)
                    ->where('respaldo', 0)
                    ->where('id_correo_juego', $correoJuego->id)
                    ->first();
    
                    if ($codigoVerificacion2 == null) {
                        $ultimoCodigo = true;

                        $notificacionExiste = Notificaciones::where('id_correo_juego', $correoJuego->id)
                        ->where('tipo', 'crear_codigos')
                        ->where('mensaje', 'Se necesita crear codigos')
                        ->first();

                        if ($notificacionExiste == null) {
                            Notificaciones::create([
                                'id_correo_juego' => $correoJuego->id,
                                'tipo' => 'crear_codigos',
                                'mensaje' => 'Se necesita crear codigos',
                            ]);
                        }
                    }

                    $respuestaCodigo = ['codigo' => $codigoVerificacion->codigo, 'ultimo_codigo' => $ultimoCodigo];
                } else {
                    $codigoRespaldo = CodigoVerificacion::where('disponible', 1)
                    ->where('respaldo', 1)
                    ->where('id_correo_juego', $correoJuego->id)
                    ->first();
    
                    if ($codigoRespaldo != null) {
                        $respuestaCodigo = ['mensaje' => 'Los codigos para este correo ya se acabaron'];

                        $notificacionExiste = Notificaciones::where('id_correo_juego', $correoJuego->id)
                        ->where('tipo', 'crear_codigos')
                        ->where('mensaje', 'Se necesita crear codigos')
                        ->first();

                        if ($notificacionExiste == null) {
                            Notificaciones::create([
                                'id_correo_juego' => $correoJuego->id,
                                'tipo' => 'crear_codigos',
                                'mensaje' => 'Se necesita crear codigos',
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json($respuestaCodigo);
    }
}