<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientePedidoRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientePedidoController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:pedidos.crear')->only(['buscar', 'store']);
        $this->middleware('can:clientes.ver')->only(['index', 'show']);
        $this->middleware('can:clientes.editar')->only('update');
    }

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'datos' => ['nullable', 'in:completos,incompletos'],
        ]);

        $clientes = Cliente::query()
            ->withCount(['pedidos', 'ventas'])
            ->withMax('pedidos', 'created_at')
            ->when($filtros['buscar'] ?? null, fn ($query, $buscar) => $query->buscar($buscar))
            ->when(($filtros['datos'] ?? null) === 'incompletos', fn ($query) => $query->datosIncompletos())
            ->when(($filtros['datos'] ?? null) === 'completos', fn ($query) => $query->datosCompletos())
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clientes/Index', [
            'clientes' => $clientes,
            'filtros' => $filtros,
            'puedeEditar' => $request->user()->can('clientes.editar'),
        ]);
    }

    public function show(Request $request, Cliente $cliente)
    {
        $pedidos = $cliente->pedidos()
            ->with([
                'pagos:id,pedido_id,valor_bruto,estado',
                'reembolsos:id,pedido_id,valor,estado',
            ])
            ->select(['id', 'cliente_id', 'codigo', 'total', 'moneda', 'estado', 'estado_financiero', 'estado_entrega', 'created_at'])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $cliente->load('cuentaPortal:id,name,email')->loadCount(['pedidos', 'ventas']);

        return Inertia::render('Clientes/Show', [
            'cliente' => $cliente,
            'pedidos' => $pedidos,
            'resumen' => [
                'total_pedidos' => $cliente->pedidos_count,
                'total_ventas' => $cliente->ventas_count,
                'total_comprado' => (float) $cliente->pedidos()->whereNotIn('estado', ['cancelado', 'reembolsado'])->sum('total'),
            ],
            'puedeEditar' => $request->user()->can('clientes.editar'),
            'portal' => [
                'activo' => (bool) $cliente->user_id,
                'activado_at' => $cliente->portal_activated_at?->toIso8601String(),
                'email_acceso' => $cliente->cuentaPortal?->email,
                'ventas_historicas' => $cliente->ventas()->whereNull('pedido_detalle_id')->count(),
            ],
        ]);
    }

    public function buscar(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json(
            Cliente::query()->buscar($request->string('q')->toString())->latest('id')->limit(10)
                ->get(['id', 'nombre', 'codigo_pais', 'telefono', 'usuario', 'email', 'notas'])
        );
    }

    public function store(StoreClientePedidoRequest $request)
    {
        $datos = $this->normalizar($request->validated());
        $identificadores = collect(['telefono', 'email', 'usuario'])->filter(fn ($campo) => ! empty($datos[$campo]));
        $existente = $identificadores->isEmpty() ? null : Cliente::query()->where(function ($query) use ($datos, $identificadores) {
            foreach ($identificadores as $campo) {
                $query->orWhere($campo, $datos[$campo]);
            }
        })->first();

        if ($existente) {
            return response()->json(['message' => 'Ya existe un cliente con estos datos.', 'cliente' => $existente], 422);
        }

        return response()->json(Cliente::create($datos), 201);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente)
    {
        $cliente->update($request->validated());
        return back()->with('success', 'Los datos del cliente fueron actualizados.');
    }

    private function normalizar(array $datos): array
    {
        $datos['codigo_pais'] = ! empty($datos['codigo_pais']) ? preg_replace('/\D/', '', $datos['codigo_pais']) : null;
        $datos['telefono'] = ! empty($datos['telefono']) ? preg_replace('/\D/', '', $datos['telefono']) : null;
        $datos['usuario'] = ! empty($datos['usuario']) ? ltrim(strtolower(trim($datos['usuario'])), '@') : null;
        $datos['email'] = ! empty($datos['email']) ? strtolower(trim($datos['email'])) : null;
        return $datos;
    }
}
