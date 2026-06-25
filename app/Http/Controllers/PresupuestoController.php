<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PresupuestoController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->get('search', ''));

        $presupuestos = Presupuesto::when($search, function ($query, $search) {
                $query->where('anio', 'like', "%{$search}%");
            })
            ->orderByDesc('anio')
            ->orderBy('mes')
            ->paginate(12)
            ->appends($request->query());

        return Inertia::render('Presupuesto/Index', [
            'presupuestos' => $presupuestos,
            'search' => $search,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'anio' => ['required', 'integer', 'min:2024', 'max:2100'],
            'mes' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('presupuestos')->where(function ($query) use ($request) {
                    return $query->where('anio', $request->anio);
                }),
            ],
            'ventas_objetivo' => ['required', 'integer', 'min:0'],
            'ingresos_objetivo' => ['required', 'numeric', 'min:0'],
            'utilidad_objetivo' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ]);

        Presupuesto::create($validatedData);

        return redirect()->route('presupuestos.index');
    }

    public function update(Request $request, Presupuesto $presupuesto)
    {
        $validatedData = $request->validate([
            'anio' => ['required', 'integer', 'min:2024', 'max:2100'],
            'mes' => [
                'required',
                'integer',
                'min:1',
                'max:12',
                Rule::unique('presupuestos')
                    ->ignore($presupuesto->id)
                    ->where(function ($query) use ($request) {
                        return $query->where('anio', $request->anio);
                    }),
            ],
            'ventas_objetivo' => ['required', 'integer', 'min:0'],
            'ingresos_objetivo' => ['required', 'numeric', 'min:0'],
            'utilidad_objetivo' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $presupuesto->update($validatedData);

        return redirect()->route('presupuestos.index');
    }

    public function destroy(Presupuesto $presupuesto)
    {
        $presupuesto->delete();

        return redirect()->back();
    }
}