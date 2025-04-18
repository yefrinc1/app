<?php

namespace App\Http\Controllers;

use App\Models\CodigoVerificacion;
use App\Models\CorreoGlobales;
use App\Models\CorreoJuego;
use App\Models\CorreoMadre;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CorreoMadreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Obtén el término de búsqueda
        $search = $request->get('search', ''); // Si no hay búsqueda, se usa una cadena vacía por defecto
        $mensaje_correo_creado = $request->get('mensaje_correo_creado', '');
    
        // Filtra los correos si se proporciona un término de búsqueda
        $correos = CorreoMadre::withCount('correosJuegos')
        ->when($search, function ($query, $search) {
            return $query->where('correo', 'like', "%{$search}%");
        })
        ->orderBy('disponible', 'desc') // Ordena por 'disponible'
        ->latest() // Ordena por fecha
        ->paginate(10);

        return Inertia::render('CorreoMadre/Index', [
            'correos' => $correos,
            'search' => $search,  // Pasa el término de búsqueda a la vista
            'mensaje_correo_creado' => $mensaje_correo_creado,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $correosGlobales = CorreoGlobales::where('disponible', 1)->take(30)->latest()->get();
        return Inertia::render('CorreoMadre/Create', ['correosGlobales' => $correosGlobales]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'contrasena' => 'required|string|min:6',
            'id_correo_globales' => 'required|exists:correo_globales,id|unique:correo_madre,id_correo_globales',
            'saldo_usd' => 'required',
            'saldo_cop' => 'required',
            'fecha_nacimiento' => 'required',
        ]);

        $correoGlobal = CorreoGlobales::findOrFail($validatedData['id_correo_globales']);

        $correoMadre = CorreoMadre::create([
            'correo' => $correoGlobal->correo,
            'contrasena' => $validatedData['contrasena'],
            'id_correo_globales' => $validatedData['id_correo_globales'],
            'saldo_usd' => $validatedData['saldo_usd'],
            'saldo_cop' => $validatedData['saldo_cop'],
            'fecha_nacimiento' => $validatedData['fecha_nacimiento'],
        ]);
        $correoGlobal->update(['disponible' => 0]);

        if ($request->codigo != '') {
            $codigos = CodigoVerificacion::separarCodigos($request->codigo);

            $codigoRespaldo = 1;
            foreach ($codigos as $clave => $codigo) {
                if ($clave > 1) {
                    $codigoRespaldo = 0;
                }
                
                CodigoVerificacion::create([
                    'codigo' => $codigo,
                    'id_correo_madre' => $correoMadre->id,
                    'respaldo' => $codigoRespaldo,
                ]);
            }
    
        }

        return redirect()->route('correo-madre.index', ['mensaje_correo_creado' => 'Se creo correctamente el correo madre']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $correoMadre = CorreoMadre::with('correosJuegos')->findOrFail($id);
        return Inertia::render('CorreoMadre/Show', ['correos_madres' => $correoMadre]);
    }

    public function quitarHijo(string $id)
    {
        $correoJuego = CorreoJuego::findOrFail($id);
        $idCorreoMadre = $correoJuego->id_correo_madre;
        $correoJuego->update(['id_correo_madre' => null]);

        $correoMadre = CorreoMadre::findOrFail($idCorreoMadre);
        $correoMadre->update(['disponible' => 1]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $correoMadre = CorreoMadre::findOrFail($id);

        $request->validate([
            'contrasena' => 'required|string|min:6',
            'fecha_nacimiento' => 'required',
        ]);
    
        $correoMadre->update([
            'contrasena' => $request->contrasena,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'saldo_usd' => $request->saldo_usd,
            'saldo_cop' => $request->saldo_cop,
            'disponible' => $request->disponible ? 1 : 0,
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $correoMadre = CorreoMadre::findOrFail($id);
        $correoGlobal = CorreoGlobales::findOrFail($correoMadre->id_correo_globales);
        $correoMadre->delete();
        $correoGlobal->update(['disponible' => 1]);
        CodigoVerificacion::where('id_correo_madre', $correoMadre->id)->delete();

        return redirect()->back();
    }
}
