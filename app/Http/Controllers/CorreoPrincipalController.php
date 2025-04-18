<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CorreoPrincipal;
use App\Models\CorreoGlobales;
use Inertia\Inertia;

class CorreoPrincipalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Obtén el término de búsqueda
        $search = $request->get('search', ''); // Si no hay búsqueda, se usa una cadena vacía por defecto
    
        // Filtra los correos si se proporciona un término de búsqueda
        $correos = CorreoPrincipal::when($search, function ($query, $search) {
            return $query->where('correo', 'like', "%{$search}%");
        })
        ->latest() // Ordena por fecha
        ->paginate(10);
    
        return Inertia::render('CorreoPrincipal/Index', [
            'correos' => $correos,
            'search' => $search,  // Pasa el término de búsqueda a la vista
        ]);
    }
    

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validar los datos que llegan desde el formulario
        $validatedData = $request->validate([
            'correo' => ['required', 'email', 'unique:correo_principal,correo'],
            'contrasena' => ['required', 'string', 'min:6'],
        ]);

        $variantes = $this->generarCorreosVariantes($validatedData['correo']);
    
        // Crear el correo en la base de datos
        $correoPrincipal = CorreoPrincipal::create([
            'correo' => $validatedData['correo'],
            'contrasena' => $validatedData['contrasena'],
        ]);

        foreach ($variantes as $variante) {
            CorreoGlobales::create([
                'correo' => $variante,
                'id_correo_principal' => $correoPrincipal->id,
            ]);
        }
    
        // Redirigir con mensaje de éxito
        return redirect()->route('correo-principal.index');
    }
    

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
    public function update(Request $request, $id)
    {
        $correo = CorreoPrincipal::findOrFail($id);

        $request->validate([
            'correo' => 'required|email',
            'contrasena' => 'required|string|min:6',
        ]);
    
        $correo->update([
            'correo' => $request->correo,
            'contrasena' => $request->contrasena,
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $correoPrincipal = CorreoPrincipal::findOrFail($id);
        $correoPrincipal->delete();

        return redirect()->route('correo-principal.index');
    }

    public function generarCorreosVariantes($correo)
    {
        // Separar el nombre y el dominio
        [$nombre, $dominio] = explode('@', $correo);

        // Generar todas las combinaciones posibles con puntos
        $variantes = $this->generarCombinaciones($nombre);

        // Añadir el dominio a cada variante
        $correosGenerados = array_map(fn($variante) => $variante . '@' . $dominio, $variantes);

        return $correosGenerados;
    }

    private function generarCombinaciones($nombre)
    {
        $longitud = strlen($nombre);
        $resultados = [];
        $resultados[] = $nombre;
    
        for ($i = 1; $i < $longitud; $i++) { 
            $nuevoNombre = substr($nombre, 0, $i) . '.' . substr($nombre, $i);
            $resultados[] = $nuevoNombre;
        }
    
        return $resultados;
    }
}
