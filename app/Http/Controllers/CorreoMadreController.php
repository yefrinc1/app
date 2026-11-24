<?php

namespace App\Http\Controllers;

use App\Models\CodigoVerificacion;
use App\Models\CorreoGlobales;
use App\Models\CorreoJuego;
use App\Models\CorreoMadre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CorreoMadreController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:correos.madre', ['only' => [
            'index', 'create', 'store', 'show', 'update', 'destroy', 'quitarHijo', 'usarComoHijo',
        ]]);
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Obtén el término de búsqueda
        $search = $request->get('search', ''); // Si no hay búsqueda, se usa una cadena vacía por defecto
        $mensaje_correo_creado = $request->get('mensaje_correo_creado', '');
    
        // Filtra los correos si se proporciona un término de búsqueda
        $correos = CorreoMadre::withCount([
            'correosJuegos',
            'correosJuegos as hijos_reales_count' => fn ($query) => $query->hijosReales(),
            'correosJuegos as madre_jugable_count' => fn ($query) => $query->where('es_cuenta_madre', 1),
        ])
        ->when($search, function ($query, $search) {
            return $query->where('correo', 'like', "%{$search}%");
        })
        ->orderBy('disponible', 'desc') // Ordena por 'disponible'
        ->latest() // Ordena por fecha
        ->paginate(10)
        ->appends($request->query());

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
        $idUltimaMadre = CorreoMadre::latest()->first()?->id ?? 0;
        return Inertia::render('CorreoMadre/Create', ['correosGlobales' => $correosGlobales, 'idUltimaMadre' => $idUltimaMadre]);
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
                if ($clave > 0 && $codigoRespaldo == 1) {
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
    public function show(Request $request, string $id)
    {
        $correoMadre = CorreoMadre::with(['correosJuegos', 'historialHijos.usuario:id,name'])->findOrFail($id);

        return Inertia::render('CorreoMadre/Show', [
            'correos_madres' => $correoMadre,
            'mensaje_correo_creado' => $request->get('mensaje_correo_creado', ''),
        ]);
    }

    public function usarComoHijo(Request $request, string $id)
    {
        $madre = DB::transaction(function () use ($id) {
            $madre = CorreoMadre::query()->lockForUpdate()->findOrFail($id);

            if ($madre->cuentaComoHijo()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'madre' => 'Esta madre ya está registrada como hijo.',
                ]);
            }

            CorreoJuego::create([
                'correo' => $madre->correo,
                'contrasena' => $madre->contrasena,
                'id_correo_globales' => $madre->id_correo_globales,
                'id_correo_madre' => $madre->id,
                'fecha_nacimiento' => $madre->fecha_nacimiento,
                'es_cuenta_madre' => true,
                'disponible' => 1,
            ]);

            return $madre;
        });

        return redirect()->route('correo-madre.show', [
            'correo_madre' => $madre->id,
            'mensaje_correo_creado' => 'La madre quedó como hijo. Asígnale el juego en Correos juegos para poder venderla y enviarla por el portal.',
        ]);
    }

    public function quitarHijo(Request $request, string $id)
    {
        $correoJuego = CorreoJuego::findOrFail($id);
        $idCorreoMadre = $correoJuego->id_correo_madre;

        if (! $idCorreoMadre) {
            throw ValidationException::withMessages([
                'hijo' => 'Este correo ya no está ligado a una madre.',
            ]);
        }

        $correoMadre = CorreoMadre::findOrFail($idCorreoMadre);
        $correoMadre->registrarHijoHistorial($correoJuego, 'quitado', $request->user()?->id);

        if ($correoJuego->es_cuenta_madre) {
            CodigoVerificacion::where('id_correo_juego', $correoJuego->id)->delete();
            $correoJuego->delete();

            return redirect()->back();
        }

        $correoJuego->update(['id_correo_madre' => null]);
        $correoMadre->sincronizarDisponibilidad();

        return redirect()->back();
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

        CorreoJuego::query()
            ->where('id_correo_madre', $correoMadre->id)
            ->where('es_cuenta_madre', 1)
            ->update([
                'correo' => $correoMadre->correo,
                'contrasena' => $request->contrasena,
                'fecha_nacimiento' => $request->fecha_nacimiento,
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
