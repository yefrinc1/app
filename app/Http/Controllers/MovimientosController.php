<?php

namespace App\Http\Controllers;

use App\Models\Movimientos;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MovimientosController extends Controller
{
    public function index()
    {
        $movimientos = Movimientos::latest()->paginate(10);
        return Inertia::render('Movimientos/Index', ['movimientos' => $movimientos]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'tipo' => 'required',
            'descripcion' => 'required',
            'valor' => 'required',
        ]);

        Movimientos::create([
            'tipo' => $validatedData['tipo'],
            'descripcion' => $validatedData['descripcion'],
            'valor' => $validatedData['valor'],
            'observaciones' => $request->observaciones,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, string $id)
    {
        $movimiento = Movimientos::findOrFail($id);

        $validatedData = $request->validate([
            'tipo' => 'required',
            'descripcion' => 'required',
            'valor' => 'required',
        ]);
    
        $movimiento->update([
            'tipo' => $validatedData['tipo'],
            'descripcion' => $validatedData['descripcion'],
            'valor' => $validatedData['valor'],
            'observaciones' => $request->observaciones,
        ]);

        return redirect()->back();
    }

    public function destroy(string $id)
    {
        $movimiento = Movimientos::findOrFail($id);
        $movimiento->delete();

        return redirect()->back();
    }
}
