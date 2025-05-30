<?php

namespace App\Http\Controllers;

use App\Models\CodigoVerificacion;
use App\Models\CorreoGlobales;
use App\Models\CorreoJuego;
use App\Models\CorreoMadre;
use App\Models\Notificaciones;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CorreoJuegoController extends Controller
{
    public function index(Request $request)
    {
        // Obtén el término de búsqueda
        $search = $request->get('search', ''); // Si no hay búsqueda, se usa una cadena vacía por defecto
        $mensaje_correo_creado = $request->get('mensaje_correo_creado', '');
        $icon_mensaje = $request->get('icon_mensaje', '');
    
        // Filtra los correos si se proporciona un término de búsqueda
        $correos = CorreoJuego::when($search, function ($query, $search) {
            return $query->where('correo', 'like', "%{$search}%");
        })
        ->orderBy('disponible', 'desc') // Ordena por 'disponible'
        ->latest() // Ordena por fecha
        ->paginate(10);
    
        return Inertia::render('CorreoJuego/Index', [
            'correos' => $correos,
            'search' => $search,  // Pasa el término de búsqueda a la vista
            'mensaje_correo_creado' => $mensaje_correo_creado,
            'icon_mensaje' => $icon_mensaje,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $correosMadres = CorreoMadre::where('disponible', 1)->take(30)->latest()->get();
        $correosGlobales = CorreoGlobales::where('disponible', 1)->take(30)->latest()->get();
        return Inertia::render('CorreoJuego/Create', ['correosMadres' => $correosMadres, 'correosGlobales' => $correosGlobales]);
    }

    public function crearJuegoManual()
    {
        return Inertia::render('CorreoJuego/CreateManual');
    }

    public function storeManual(Request $request)
    {
        $validatedData = $request->validate([
            'correo' => 'required|unique:correo_globales,correo',
            'contrasena' => 'required',
            'juego' => 'required',
            'fecha_nacimiento' => 'required',
            'primaria_ps4' => 'required',
            'primaria_ps5' => 'required',
            'secundaria' => 'required',
        ]);

        $correoGlobal = CorreoGlobales::create([
            'correo' => $validatedData['correo'],
        ]);

        $correoJuegoDisponible = $validatedData['primaria_ps4'] + $validatedData['primaria_ps5'] + $validatedData['secundaria'] >= 5;

        $correoJuego = CorreoJuego::create([
            'correo' => $correoGlobal->correo,
            'contrasena' => $validatedData['contrasena'],
            'id_correo_globales' => $correoGlobal->id,
            'fecha_nacimiento' => $validatedData['fecha_nacimiento'],
            'juego' => $validatedData['juego'],
            'primaria_ps4' => $validatedData['primaria_ps4'],
            'primaria_ps5' => $validatedData['primaria_ps5'],
            'secundaria' => $validatedData['secundaria'],
            'disponible' => $correoJuegoDisponible ? 0 : 1,
        ]);

        $correoGlobal->update(['disponible' => 0]);

        if (trim($request->codigo) != '') {
            $codigos = CodigoVerificacion::separarCodigos($request->codigo);

            $codigoRespaldo = 1;
            foreach ($codigos as $clave => $codigo) {
                if ($clave > 1) {
                    $codigoRespaldo = 0;
                }
                
                CodigoVerificacion::create([
                    'codigo' => $codigo,
                    'id_correo_juego' => $correoJuego->id,
                    'respaldo' => $codigoRespaldo,
                ]);
            }
        }

        return redirect()->back();
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'contrasena' => 'required|string|min:6',
            'id_correo_globales' => 'required|exists:correo_globales,id',
            'id_correo_madre' => 'required|exists:correo_madre,id',
            'fecha_nacimiento' => 'required',
        ]);

        $correoGlobal = CorreoGlobales::findOrFail($validatedData['id_correo_globales']);
        $correoMadre = CorreoMadre::findOrFail($validatedData['id_correo_madre']);
        $crearCorreoJuego = true;

        if ($request->precio_usd != '') {
            if ($request->precio_usd > $correoMadre->saldo_usd) {
                $crearCorreoJuego = false;
            }
        }

        if ($crearCorreoJuego) {
            $precioCOP = null;
            if ($request->precio_usd) {
                $saldoRestanteUSD = $correoMadre->saldo_usd - $request->precio_usd;

                $precioDolar = $correoMadre->saldo_usd != 0 ? ($correoMadre->saldo_cop / $correoMadre->saldo_usd) : 0;
                $precioCOP = $request->precio_usd * $precioDolar;
                $saldoRestanteCOP = $correoMadre->saldo_cop - $precioCOP;
                $correoMadre->update(['saldo_usd' => $saldoRestanteUSD, 'saldo_cop' => $saldoRestanteCOP]);
            }

            $correoJuego = CorreoJuego::create([
                'correo' => $correoGlobal->correo,
                'contrasena' => $validatedData['contrasena'],
                'id_correo_globales' => $validatedData['id_correo_globales'],
                'id_correo_madre' => $validatedData['id_correo_madre'],
                'fecha_nacimiento' => $validatedData['fecha_nacimiento'],
                'juego' => $request->juego,
                'precio_usd' => $request->precio_usd,
                'precio_cop' => $precioCOP,
            ]);

            $notificacionExiste = Notificaciones::where('juego', $request->juego)
            ->where('tipo', 'crear_juego')
            ->first();

            if ($notificacionExiste) {
                $notificacionExiste->delete();
            }
            
            $juegosMadre = CorreoJuego::where('id_correo_madre', $validatedData['id_correo_madre'])->count();
            if ($juegosMadre >= 6) {
                $correoMadre->update(['disponible' => 0]);
            }
            $correoGlobal->update(['disponible' => 0]);
    
            if (trim($request->codigo) != '') {
                $codigos = CodigoVerificacion::separarCodigos($request->codigo);
    
                $codigoRespaldo = 1;
                foreach ($codigos as $clave => $codigo) {
                    if ($clave > 1) {
                        $codigoRespaldo = 0;
                    }
                    
                    CodigoVerificacion::create([
                        'codigo' => $codigo,
                        'id_correo_juego' => $correoJuego->id,
                        'respaldo' => $codigoRespaldo,
                    ]);
                }
            }

            $mensaje = 'Se creo correctamente el correo del juego';
            $icon_mensaje = 'success';
        } else {
            $mensaje = 'No fue posible crear el juego ya que la cuenta madre no tiene saldo suficiente';
            $icon_mensaje = 'error';
        }

        return redirect()->route('correo-juegos.index', ['mensaje_correo_creado' => $mensaje, 'icon_mensaje' => $icon_mensaje]);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'contrasena' => 'required|string|min:6',
            'fecha_nacimiento' => 'required',
            'juego' => 'required',
            'primaria_ps4' => 'required',
            'primaria_ps5' => 'required',
            'secundaria' => 'required',
        ]);

        $correoJuego = CorreoJuego::findOrFail($id);
        $correoMadre = CorreoMadre::find($correoJuego->id_correo_madre);

        if ($correoMadre) {
            $saldoNuevoMadreUSD = $correoJuego->precio_usd + $correoMadre->saldo_usd;
            $saldoNuevoMadreCOP = $correoJuego->precio_cop + $correoMadre->saldo_cop;
    
            if ($saldoNuevoMadreUSD >= $request->precio_usd) {
                $saldoRestanteUSD = $saldoNuevoMadreUSD - $request->precio_usd;
                $precioDolar = $saldoNuevoMadreUSD != 0 ? ($saldoNuevoMadreCOP / $saldoNuevoMadreUSD) : 0;
                $precioCOP = $request->precio_usd * $precioDolar;
                $saldoRestanteCOP = $saldoNuevoMadreCOP - $precioCOP;
                $correoMadre->update(['saldo_usd' => $saldoRestanteUSD, 'saldo_cop' => $saldoRestanteCOP]);
    
                $correoJuego->update([
                    'contrasena' => $request->contrasena,
                    'fecha_nacimiento' => $request->fecha_nacimiento,
                    'juego' => $request->juego,
                    'precio_usd' => $request->precio_usd,
                    'precio_cop' => $precioCOP,
                    'disponible' => $request->disponible ? 1 : 0,
                    'primaria_ps4' => $request->primaria_ps4,
                    'primaria_ps5' => $request->primaria_ps5,
                    'secundaria' => $request->secundaria,
                ]);
    
                $mensaje = "Se actualizo correctamente el juego";
                $icon_mensaje = 'success';
            } else {
                $mensaje = "No fue posible actualizar el juego, la cuenta madre solo tiene $saldoNuevoMadreUSD USD de saldo";
                $icon_mensaje = 'error';
            }
        } else {
            $correoJuego->update([
                'contrasena' => $request->contrasena,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'juego' => $request->juego,
                'disponible' => $request->disponible ? 1 : 0,
                'primaria_ps4' => $request->primaria_ps4,
                'primaria_ps5' => $request->primaria_ps5,
                'secundaria' => $request->secundaria,
            ]);

            $mensaje = "Se actualizo correctamente el juego";
            $icon_mensaje = 'success';
        }

        if (trim($request->codigo) != '') {
            $codigos = CodigoVerificacion::separarCodigos($request->codigo);

            $codigoRespaldo = 1;
            foreach ($codigos as $clave => $codigo) {
                if ($clave > 1) {
                    $codigoRespaldo = 0;
                }
                
                CodigoVerificacion::create([
                    'codigo' => $codigo,
                    'id_correo_juego' => $correoJuego->id,
                    'respaldo' => $codigoRespaldo,
                ]);
            }
        }

        return redirect()->route('correo-juegos.index', ['mensaje_correo_creado' => $mensaje, 'icon_mensaje' => $icon_mensaje]);
    }

    public function destroy(string $id)
    {
        $correoJuego = CorreoJuego::findOrFail($id);

        if ($correoJuego->id_correo_madre) {
            $correoMadre = CorreoMadre::findOrFail($correoJuego->id_correo_madre);
            $saldoRestanteUSD = $correoJuego->precio_usd + $correoMadre->saldo_usd;
            $saldoRestanteCOP = $correoJuego->precio_cop + $correoMadre->saldo_cop;
            $correoMadre->update([
                'saldo_usd' => $saldoRestanteUSD, 
                'saldo_cop' => $saldoRestanteCOP, 
                'disponible' => 1
            ]);
        }

        $correoGlobal = CorreoGlobales::findOrFail($correoJuego->id_correo_globales);
        $correoJuego->delete();
        $correoGlobal->update(['disponible' => 1]);
        CodigoVerificacion::where('id_correo_juego', $correoJuego->id)->delete();

        return redirect()->back();
    }

    public function consultarInventario(Request $request)
    {
        $search = $request->get('search', '');

        $correos = CorreoJuego::when($search, function ($query, $search) {
            return $query->where('juego', 'like', "%{$search}%");
        })        
        ->where('disponible', 1)
        ->latest()
        ->paginate(10);

        return Inertia::render('CorreoJuego/Inventario', [
            'correos' => $correos,
            'search' => $search,
        ]);
    }
}
