<?php

namespace App\Http\Controllers;

use App\Models\CorreoGlobales;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CorreoGlobalesController extends Controller
{
    public function index(Request $request)
    {
        // Obtén el término de búsqueda
        $search = $request->get('search', ''); // Si no hay búsqueda, se usa una cadena vacía por defecto
    
        // Filtra los correos si se proporciona un término de búsqueda
        $correosGlobales = CorreoGlobales::when($search, function ($query, $search) {
            return $query->where('correo', 'like', "%{$search}%");
        })
        ->orderBy('disponible', 'desc') // Ordena por 'disponible'
        ->latest() // Ordena por fecha
        ->paginate(10);
    
        return Inertia::render('CorreoGlobales/Index', [
            'correos' => $correosGlobales,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'correo' => 'required|unique:correo_globales,correo',
        ]);
        CorreoGlobales::create([
            'correo' => $validatedData['correo'],
        ]);

        return redirect()->back();
    }

    public function destroy(string $id)
    {
        $correoGlobal = CorreoGlobales::findOrFail($id);
        $correoGlobal->delete();

        return redirect()->back();
    }
}