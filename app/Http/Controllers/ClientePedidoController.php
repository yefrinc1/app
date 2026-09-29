<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientePedidoRequest;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClientePedidoController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:pedidos.crear');
    }

    public function buscar(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $clientes = Cliente::query()
            ->buscar($request->string('q')->toString())
            ->latest('id')
            ->limit(10)
            ->get(['id', 'nombre', 'codigo_pais', 'telefono', 'usuario', 'email']);

        return response()->json($clientes);
    }

    public function store(StoreClientePedidoRequest $request)
    {
        $datos = $request->validated();

        if (! empty($datos['telefono'])) {
            $datos['telefono'] = preg_replace('/\D/', '', $datos['telefono']);
        }

        if (! empty($datos['usuario'])) {
            $datos['usuario'] = ltrim(strtolower($datos['usuario']), '@');
        }

        $identificadores = collect(['telefono', 'email', 'usuario'])
            ->filter(fn ($campo) => ! empty($datos[$campo]));

        $existente = $identificadores->isEmpty()
            ? null
            : Cliente::query()->where(function ($query) use ($datos, $identificadores) {
                foreach ($identificadores as $campo) {
                    $valor = $campo === 'email' ? strtolower($datos[$campo]) : $datos[$campo];
                    $query->orWhere($campo, $valor);
                }
            })->first();

        if ($existente) {
            return response()->json([
                'message' => 'Ya existe un cliente con estos datos.',
                'cliente' => $existente,
            ], 422);
        }

        $datos['email'] = isset($datos['email']) ? strtolower($datos['email']) : null;
        $cliente = Cliente::create($datos);

        return response()->json($cliente, 201);
    }
}
