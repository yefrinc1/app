<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientePedidoRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\Juego;
use App\Models\PedidoDetalle;
use App\Models\PortalCodigoSolicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
        $juegos = $this->juegosComprados($cliente);

        return Inertia::render('Clientes/Show', [
            'cliente' => $cliente,
            'pedidos' => $pedidos,
            'juegos' => $juegos,
            'resumen' => [
                'total_pedidos' => $cliente->pedidos_count,
                'total_ventas' => $cliente->ventas_count,
                'total_juegos' => $juegos->pluck('juego')->unique()->count(),
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

    private function juegosComprados(Cliente $cliente): Collection
    {
        $detalles = PedidoDetalle::query()
            ->whereHas('pedido', fn ($query) => $query->where('cliente_id', $cliente->id)->whereNotIn('estado', ['cancelado']))
            ->with([
                'pedido:id,codigo,cliente_id,estado,created_at',
                'entregas' => fn ($query) => $query->where('estado', '!=', 'anulada')->orderBy('id'),
                'entregas.correoJuego:id,juego,correo,contrasena',
                'entregas.codigoVerificacion:id,codigo',
            ])
            ->latest('id')
            ->get();

        $ventas = $cliente->ventas()
            ->with('correoJuego:id,juego,correo,contrasena')
            ->latest('id')
            ->get();

        $nombres = $detalles->pluck('juego')
            ->merge($ventas->pluck('correoJuego.juego'))
            ->filter()
            ->unique()
            ->values();

        $imagenes = Juego::query()
            ->whereIn('nombre', $nombres)
            ->get(['nombre', 'url_imagen'])
            ->mapWithKeys(fn (Juego $juego) => [Str::lower(trim($juego->nombre)) => $juego->url_imagen]);

        $solicitudes = PortalCodigoSolicitud::query()
            ->where('cliente_id', $cliente->id)
            ->latest('id')
            ->get(['id', 'pedido_entrega_id', 'venta_id', 'codigo', 'created_at']);
        $porEntrega = $solicitudes->whereNotNull('pedido_entrega_id')->groupBy('pedido_entrega_id');
        $porVenta = $solicitudes->whereNotNull('venta_id')->groupBy('venta_id');

        $pedidos = $detalles->map(function (PedidoDetalle $detalle) use ($imagenes, $porEntrega) {
            $nombre = $detalle->juego ?: 'Juego digital';

            return [
                'clave' => 'pedido-'.$detalle->id,
                'juego' => $nombre,
                'imagen' => $imagenes->get(Str::lower(trim($nombre))),
                'tipo_cuenta' => $detalle->tipo_cuenta,
                'consola' => $detalle->consola,
                'cantidad' => (int) $detalle->cantidad,
                'origen' => 'pedido',
                'origen_texto' => $detalle->pedido?->codigo ?: 'Pedido',
                'pedido_id' => $detalle->pedido_id,
                'venta_id' => null,
                'fecha' => $detalle->pedido?->created_at?->toIso8601String(),
                'estado' => $detalle->pedido?->estado,
                'cuentas' => $detalle->entregas->map(fn ($entrega) => [
                    'usuario' => $entrega->correoJuego?->correo,
                    'contrasena' => $entrega->correoJuego?->contrasena,
                    'codigo' => $entrega->codigoVerificacion?->codigo,
                    'estado' => $entrega->estado,
                    'solicitudes' => $this->mapaSolicitudes($porEntrega->get($entrega->id, collect())),
                ])->values(),
            ];
        });

        $historicas = $ventas
            ->filter(fn ($venta) => blank($venta->pedido_detalle_id))
            ->map(function ($venta) use ($imagenes, $porVenta) {
                $nombre = $venta->correoJuego?->juego ?: 'Juego digital';

                return [
                    'clave' => 'venta-'.$venta->id,
                    'juego' => $nombre,
                    'imagen' => $imagenes->get(Str::lower(trim($nombre))),
                    'tipo_cuenta' => $venta->tipo_cuenta,
                    'consola' => $venta->consola,
                    'cantidad' => 1,
                    'origen' => 'venta',
                    'origen_texto' => 'Venta histórica',
                    'pedido_id' => null,
                    'venta_id' => $venta->id,
                    'fecha' => $venta->created_at?->toIso8601String(),
                    'estado' => $venta->estado ?: 'activa',
                    'cuentas' => [[
                        'usuario' => $venta->correoJuego?->correo,
                        'contrasena' => $venta->correoJuego?->contrasena,
                        'codigo' => null,
                        'estado' => $venta->estado ?: 'activa',
                        'solicitudes' => $this->mapaSolicitudes($porVenta->get($venta->id, collect())),
                    ]],
                ];
            });

        return $pedidos->concat($historicas)->sortByDesc('fecha')->values();
    }

    private function mapaSolicitudes(Collection $solicitudes): Collection
    {
        return $solicitudes->map(fn (PortalCodigoSolicitud $solicitud) => [
            'codigo' => $solicitud->codigo,
            'fecha' => $solicitud->created_at?->toIso8601String(),
        ])->values();
    }

    private function normalizar(array $datos): array
    {
        $datos['codigo_pais'] = ! empty($datos['codigo_pais']) ? preg_replace('/\D/', '', $datos['codigo_pais']) : null;
        $datos['telefono'] = ! empty($datos['telefono']) ? preg_replace('/\D/', '', $datos['telefono']) : null;
        $datos['usuario'] = Cliente::normalizarUsuario($datos['usuario'] ?? null);
        $datos['email'] = ! empty($datos['email']) ? strtolower(trim($datos['email'])) : null;
        return $datos;
    }
}
