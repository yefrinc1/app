<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePedidoRequest;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoEntrega;
use App\Models\PedidoHistorial;
use App\Models\PedidoPago;
use App\Services\PedidoTotalesService;
use App\Services\ComprobantePagoService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class PedidoController extends Controller
{
    public function __construct(
        private PedidoTotalesService $totales,
        private ComprobantePagoService $comprobantes
    )
    {
        $this->middleware('can:pedidos.ver')->only(['index', 'show']);
        $this->middleware('can:pedidos.crear')->only(['create', 'store']);
        $this->middleware('can:pedidos.cancelar')->only('cancelar');
    }

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:borrador,pendiente,en_proceso,completado,cancelado,reembolsado'],
            'estado_pago' => ['nullable', 'in:pendiente,parcial,pagado'],
            'estado_financiero' => ['nullable', 'in:pendiente,pago_parcial,pagado,reembolso_parcial,reembolsado'],
            'estado_entrega' => ['nullable', 'in:pendiente,parcial,completado'],
            'fecha' => ['nullable', 'date'],
        ]);

        $pedidos = Pedido::query()
            ->with([
                'cliente:id,nombre,codigo_pais,telefono,usuario,email',
                'pagos:id,pedido_id,valor_bruto,estado',
                'reembolsos:id,pedido_id,valor,estado',
                'detalles:id,pedido_id,cantidad,cantidad_generada',
            ])
            ->when($filtros['buscar'] ?? null, function ($query, $buscar) {
                $query->where(function ($q) use ($buscar) {
                    $q->where('codigo', 'like', "%{$buscar}%")
                        ->orWhereHas('cliente', fn ($cliente) => $cliente->buscar($buscar));
                });
            })
            ->when($filtros['estado'] ?? null, fn ($q, $valor) => $q->where('estado', $valor))
            ->when($filtros['estado_pago'] ?? null, fn ($q, $valor) => $q->where('estado_pago', $valor))
            ->when($filtros['estado_financiero'] ?? null, fn ($q, $valor) => $q->where('estado_financiero', $valor))
            ->when($filtros['estado_entrega'] ?? null, fn ($q, $valor) => $q->where('estado_entrega', $valor))
            ->when($filtros['fecha'] ?? null, fn ($q, $valor) => $q->whereDate('created_at', $valor))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Pedidos/Index', [
            'pedidos' => $pedidos,
            'filtros' => $filtros,
        ]);
    }

    public function create()
    {
        return Inertia::render('Pedidos/Create', [
            'canales' => ['manual', 'instagram', 'whatsapp', 'jumpseller', 'otro'],
            'metodosPago' => ['Bancolombia', 'Nequi', 'Mercado Pago', 'Jumpseller', 'Efectivo', 'Otro'],
        ]);
    }

    public function store(StorePedidoRequest $request)
    {
        $pathsGuardados = [];

        try {
            $pedido = DB::transaction(function () use ($request, &$pathsGuardados) {
                $datos = $request->validated();
                $cliente = $this->resolverCliente($datos);

            $pedido = Pedido::create([
                'cliente_id' => $cliente->id,
                'canal_venta' => $datos['canal_venta'],
                'moneda' => $datos['moneda'],
                'descuento' => $datos['descuento'] ?? 0,
                'estado' => 'pendiente',
                'observaciones' => $datos['observaciones'] ?? null,
                'creado_por' => $request->user()->id,
            ]);

            $pedido->update([
                'codigo' => 'PED-'.$pedido->created_at->format('Ymd').'-'.str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT),
            ]);

            foreach ($datos['detalles'] as $detalle) {
                $descuento = (float) ($detalle['descuento'] ?? 0);
                $subtotal = max(0, ((float) $detalle['precio_unitario'] * (int) $detalle['cantidad']) - $descuento);

                $pedido->detalles()->create([
                    ...$detalle,
                    'descuento' => $descuento,
                    'subtotal' => $subtotal,
                    'estado' => 'pendiente',
                ]);
            }

            foreach ($datos['pagos'] ?? [] as $indice => $pago) {
                $archivo = $request->file("pagos.{$indice}.comprobante");
                [$referenciaNormalizada, $comprobanteHash] = $this->comprobantes->validarUnico(
                    $pago['referencia'] ?? null,
                    $archivo,
                    "pagos.{$indice}.referencia",
                    "pagos.{$indice}.comprobante"
                );
                $path = $this->guardarComprobante($request, "pagos.{$indice}.comprobante", $pedido->id);
                if ($path) {
                    $pathsGuardados[] = $path;
                }

                PedidoPago::create([
                    'pedido_id' => $pedido->id,
                    'metodo_pago' => $pago['metodo_pago'],
                    'valor_bruto' => $pago['valor_bruto'],
                    'moneda' => $datos['moneda'],
                    'fecha_pago' => $pago['fecha_pago'] ?? null,
                    'referencia' => $pago['referencia'] ?? null,
                    'referencia_normalizada' => $referenciaNormalizada,
                    'comprobante_path' => $path,
                    'comprobante_hash' => $comprobanteHash,
                    'estado' => 'pendiente',
                    'observaciones' => $pago['observaciones'] ?? null,
                    'registrado_por' => $request->user()->id,
                ]);
            }

            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $request->user()->id,
                'evento' => 'pedido_creado',
                'descripcion' => "Se creó el pedido {$pedido->codigo}.",
                'datos_nuevos' => ['cliente_id' => $cliente->id, 'detalles' => count($datos['detalles'])],
            ]);

                return $this->totales->recalcular($pedido);
            });
        } catch (QueryException $exception) {
            foreach ($pathsGuardados as $path) {
                Storage::disk('local')->delete($path);
            }

            $this->lanzarErrorDuplicado($exception);
            throw $exception;
        } catch (Throwable $exception) {
            foreach ($pathsGuardados as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return redirect()->route('pedidos.show', $pedido)
            ->with('success', 'Pedido creado correctamente. Los pagos quedaron pendientes de aprobación.');
    }

    public function show(Pedido $pedido)
    {
        $usuario = request()->user();
        $relaciones = [
            'cliente', 'creador:id,name',
            'detalles.entregas.resolucion.reemplazoDetalle',
            'detalles.entregas.reembolsoResolucion',
            'detalles.entregas.anulacion',
            'pagos.liquidaciones',
            'reembolsos.pago', 'reembolsos.detalle',
            'pagos.registrador:id,name',
            'pagos.aprobador:id,name',
            'historial.usuario:id,name',
        ];

        if ($usuario->can('pedidos.entregar')) {
            $relaciones[] = 'detalles.entregas.correoJuego';
            $relaciones[] = 'detalles.entregas.codigoVerificacion';
        }

        $pedido->load($relaciones);

        // Estos permisos se entregan directamente a la pagina. De esta forma
        // el modulo no depende de que HandleInertiaRequests comparta
        // auth.permissions globalmente y los botones coinciden con las
        // autorizaciones que Laravel aplica en controladores y FormRequest.
        $permisosModulo = [
            'pedidos.cancelar',
            'pagos.subir',
            'pagos.revisar',
            'pedidos.entregar',
            'pedidos.anular',
            'reembolsos.crear',
            'reembolsos.revisar',
        ];

        $permisos = collect($permisosModulo)
            ->filter(fn (string $permiso) => $usuario->can($permiso))
            ->values()
            ->all();

        return Inertia::render('Pedidos/Show', [
            'pedido' => $pedido,
            'permissions' => $permisos,
        ]);
    }

    public function cancelar(Request $request, Pedido $pedido)
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);

        if ($pedido->estado_financiero === 'pagado') {
            throw ValidationException::withMessages([
                'pedido' => 'Un pedido pagado no se puede cancelar. Debes entregarlo o registrar un reembolso.',
            ]);
        }

        abort_if($pedido->estado === 'completado', 422, 'No se puede cancelar un pedido completado.');

        $tieneEntregas = PedidoEntrega::query()
            ->whereHas('detalle', fn ($query) => $query->where('pedido_id', $pedido->id))
            ->where('estado', '!=', 'anulada')
            ->exists();

        if ($tieneEntregas) {
            throw ValidationException::withMessages([
                'pedido' => 'Este pedido ya tiene ventas generadas. Debes crear un proceso de anulación antes de cancelarlo.',
            ]);
        }

        DB::transaction(function () use ($pedido, $request, $datos) {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $pedido->update([
                'estado' => 'cancelado',
                'cancelado_por' => $request->user()->id,
                'cancelado_at' => now(),
                'motivo_cancelacion' => $datos['motivo'],
            ]);

            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $request->user()->id,
                'evento' => 'pedido_cancelado',
                'descripcion' => 'Pedido cancelado: '.$datos['motivo'],
            ]);
        });

        return back()->with('success', 'Pedido cancelado correctamente.');
    }

    private function resolverCliente(array $datos): Cliente
    {
        if (! empty($datos['cliente_id'])) {
            return Cliente::findOrFail($datos['cliente_id']);
        }

        $nuevo = $datos['cliente_nuevo'];
        $nuevo['telefono'] = isset($nuevo['telefono']) ? preg_replace('/\D/', '', $nuevo['telefono']) : null;
        $nuevo['usuario'] = isset($nuevo['usuario']) ? ltrim(strtolower($nuevo['usuario']), '@') : null;
        $nuevo['email'] = isset($nuevo['email']) ? strtolower($nuevo['email']) : null;

        $identificadores = collect(['telefono', 'usuario', 'email'])
            ->filter(fn ($campo) => ! empty($nuevo[$campo]));

        $existente = $identificadores->isEmpty()
            ? null
            : Cliente::query()->where(function ($query) use ($nuevo, $identificadores) {
                foreach ($identificadores as $campo) {
                    $query->orWhere($campo, $nuevo[$campo]);
                }
            })->first();

        return $existente ?: Cliente::create($nuevo);
    }

    private function guardarComprobante(Request $request, string $campo, int $pedidoId): ?string
    {
        $archivo = $request->file($campo);

        if (! $archivo) {
            return null;
        }

        $nombre = Str::uuid().'.'.$archivo->getClientOriginalExtension();

        return $archivo->storeAs("comprobantes/pedidos/{$pedidoId}", $nombre, 'local');
    }

    private function lanzarErrorDuplicado(QueryException $exception): void
    {
        $mensaje = $exception->getMessage();

        if (str_contains($mensaje, 'pedido_pagos_referencia_normalizada_unique')) {
            throw ValidationException::withMessages([
                'pagos' => 'Una referencia de pago acaba de ser registrada en otro comprobante.',
            ]);
        }

        if (str_contains($mensaje, 'pedido_pagos_comprobante_hash_unique')) {
            throw ValidationException::withMessages([
                'pagos' => 'Uno de estos comprobantes acaba de ser subido anteriormente.',
            ]);
        }
    }
}
