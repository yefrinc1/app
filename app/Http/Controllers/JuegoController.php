<?php

namespace App\Http\Controllers;

use App\Models\Juego;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JuegoController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');

        $juegos = Juego::when($search, function ($query, $search) {
            return $query->where('nombre', 'like', "%{$search}%");
        })
            ->latest()
            ->paginate(10)
            ->appends($request->query());

        return Inertia::render('Juegos/Index', [
            'juegos' => $juegos,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:juegos,nombre'],
            'url_imagen' => ['nullable', 'string', 'max:500'],
            'consola' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ]);

        Juego::create([
            'nombre' => $validatedData['nombre'],
            'url_imagen' => $validatedData['url_imagen'] ?? null,
            'consola' => $validatedData['consola'] ?? null,
            'descripcion' => $validatedData['descripcion'] ?? null,
            'activo' => $validatedData['activo'] ?? true,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, string $id)
    {
        $juego = Juego::findOrFail($id);

        $validatedData = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:juegos,nombre,' . $id],
            'url_imagen' => ['nullable', 'string', 'max:500'],
            'consola' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ]);

        $juego->update([
            'nombre' => $validatedData['nombre'],
            'url_imagen' => $validatedData['url_imagen'] ?? null,
            'consola' => $validatedData['consola'] ?? null,
            'descripcion' => $validatedData['descripcion'] ?? null,
            'activo' => $validatedData['activo'] ?? true,
        ]);

        return redirect()->back();
    }

    public function destroy(string $id)
    {
        $juego = Juego::findOrFail($id);
        $juego->delete();

        return redirect()->back();
    }

    public function buscar(Request $request)
    {
        $query = trim($request->input('nombre', ''));

        if ($query === '') {
            return response()->json([
                'juegos' => [],
                'exact_match' => false,
            ]);
        }

        $juegos = Juego::where('nombre', 'like', "%{$query}%")
            ->where('activo', true)
            ->select('id', 'nombre', 'url_imagen')
            ->orderBy('nombre')
            ->limit(7)
            ->get();

        $exactMatch = Juego::whereRaw('LOWER(nombre) = ?', [strtolower($query)])
            ->where('activo', true)
            ->exists();

        return response()->json([
            'juegos' => $juegos,
            'exact_match' => $exactMatch,
        ]);
    }
}
