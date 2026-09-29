<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevisarPedidoReembolsoRequest;
use App\Http\Requests\StorePedidoReembolsoRequest;
use App\Models\Pedido;
use App\Models\PedidoHistorial;
use App\Models\PedidoEntrega;
use App\Models\PedidoReembolso;
use App\Services\PedidoTotalesService;
use App\Services\ResumenMensualService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PedidoReembolsoController extends Controller
{
    public function __construct(
        private PedidoTotalesService $totales,
        private ResumenMensualService $resumen,
    ) {
        $this->middleware('can:reembolsos.crear')->only('store');
        $this->middleware('can:pedidos.ver')->only('comprobante');
        $this->middleware('can:reembolsos.revisar')->only(['aprobar', 'rechazar', 'relacionarDetalle']);
    }

    public function store(StorePedidoReembolsoRequest $request, Pedido $pedido)
    {
        $datos = $request->validated();

        DB::transaction(function () use ($request, $pedido, $datos) {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->estado_financiero === 'reembolsado') {
                throw ValidationException::withMessages([
                    'pedido' => 'Este pedido ya fue reembolsado totalmente y se encuentra cerrado.',
                ]);
            }

            if (! empty($datos['pedido_entrega_anulada_id'])) {
                $entregaAnulada = PedidoEntrega::query()
                    ->with('resolucion')
                    ->whereHas('detalle', fn ($query) => $query->where('pedido_id', $pedido->id))
                    ->lockForUpdate()
                    ->find($datos['pedido_entrega_anulada_id']);

                if (! $entregaAnulada || $entregaAnulada->estado !== 'anulada') {
                    throw ValidationException::withMessages([
                        'pedido_entrega_anulada_id' => 'La entrega seleccionada no está anulada o no pertenece a este pedido.',
                    ]);
                }

                if ($entregaAnulada->resolucion) {
                    throw ValidationException::withMessages([
                        'pedido_entrega_anulada_id' => 'Esta anulación ya fue resuelta mediante un reemplazo.',
                    ]);
                }

                $yaRelacionado = $pedido->reembolsos()
                    ->where('pedido_entrega_anulada_id', $entregaAnulada->id)
                    ->whereIn('estado', ['pendiente', 'aprobado'])
                    ->lockForUpdate()
                    ->exists();

                if ($yaRelacionado) {
                    throw ValidationException::withMessages([
                        'pedido_entrega_anulada_id' => 'Esta anulación ya tiene un reembolso pendiente o aprobado.',
                    ]);
                }

                $datos['pedido_detalle_id'] = $entregaAnulada->pedido_detalle_id;
                $datos['cantidad'] = 1;
            }

            if ($datos['moneda'] !== $pedido->moneda) {
                throw ValidationException::withMessages([
                    'moneda' => 'La moneda del reembolso debe coincidir con la moneda del pedido.',
                ]);
            }

            $pagosAprobados = $pedido->pagos()
                ->where('estado', 'aprobado')
                ->lockForUpdate()
                ->get();

            if (! empty($datos['pedido_pago_id']) && ! $pagosAprobados->contains('id', (int) $datos['pedido_pago_id'])) {
                throw ValidationException::withMessages([
                    'pedido_pago_id' => 'Selecciona un pago aprobado que pertenezca a este pedido.',
                ]);
            }

            if (! empty($datos['pedido_detalle_id'])) {
                $detalle = $pedido->detalles()
                    ->with('entregas')
                    ->lockForUpdate()
                    ->find($datos['pedido_detalle_id']);

                if (! $detalle) {
                    throw ValidationException::withMessages([
                        'pedido_detalle_id' => 'El juego seleccionado no pertenece a este pedido.',
                    ]);
                }

                $cantidadComprometida = (int) $pedido->reembolsos()
                    ->where('pedido_detalle_id', $detalle->id)
                    ->whereIn('estado', ['pendiente', 'aprobado'])
                    ->lockForUpdate()
                    ->get()
                    ->sum('cantidad');
                $cantidadReemplazada = (int) $detalle->resolucionesOrigen()
                    ->where('tipo', 'reemplazo')
                    ->sum('cantidad');
                $entregasActivas = $detalle->entregas->where('estado', '!=', 'anulada')->count();
                // Las entregas anuladas dejan de contar como ventas activas. Por eso la
                // unidad puede relacionarse con un reembolso, aunque el inventario se
                // haya marcado como no reutilizable durante la anulación.
                $cantidadDisponible = max(0, (int) $detalle->cantidad - $entregasActivas - $cantidadReemplazada - $cantidadComprometida);

                if ((int) $datos['cantidad'] > $cantidadDisponible) {
                    throw ValidationException::withMessages([
                        'cantidad' => $cantidadDisponible > 0
                            ? "Solo puedes relacionar {$cantidadDisponible} unidad(es) de este juego con el reembolso."
                            : 'Este juego ya tiene venta generada, fue reembolsado o no tiene unidades pendientes. Anula primero cualquier venta generada.',
                    ]);
                }
            } else {
                $datos['pedido_detalle_id'] = null;
                $datos['cantidad'] = null;
            }

            $reembolsosComprometidos = $pedido->reembolsos()
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->lockForUpdate()
                ->get();

            $pagadoBruto = (float) $pagosAprobados->sum('valor_bruto');
            $comprometido = (float) $reembolsosComprometidos->sum('valor');
            $disponible = max(0, $pagadoBruto - $comprometido);

            if ($pagadoBruto <= 0) {
                throw ValidationException::withMessages([
                    'valor' => 'No hay pagos aprobados en este pedido. Primero debes aprobar un pago.',
                ]);
            }

            if ((float) $datos['valor'] > $disponible) {
                throw ValidationException::withMessages([
                    'valor' => "Solo hay {$disponible} {$pedido->moneda} disponibles para reembolsar.",
                ]);
            }

            $path = null;
            if ($request->hasFile('comprobante')) {
                $archivo = $request->file('comprobante');
                $path = $archivo->storeAs(
                    "comprobantes/pedidos/{$pedido->id}/reembolsos",
                    Str::uuid().'.'.$archivo->getClientOriginalExtension(),
                    'local'
                );
            }

            unset($datos['comprobante']);

            $reembolso = $pedido->reembolsos()->create([
                ...$datos,
                'pedido_pago_id' => ($datos['pedido_pago_id'] ?? null) ?: null,
                'comprobante_path' => $path,
                'estado' => 'pendiente',
                'registrado_por' => $request->user()->id,
            ]);

            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $request->user()->id,
                'evento' => 'reembolso_registrado',
                'descripcion' => $reembolso->pedido_entrega_anulada_id
                    ? "La entrega anulada #{$reembolso->pedido_entrega_anulada_id} se resolvió con una solicitud de reembolso por {$reembolso->valor} {$reembolso->moneda}."
                    : "Se registró una solicitud de reembolso por {$reembolso->valor} {$reembolso->moneda}.",
                'datos_nuevos' => [
                    'reembolso_id' => $reembolso->id,
                    'pedido_entrega_anulada_id' => $reembolso->pedido_entrega_anulada_id,
                ],
            ]);
        }, 3);

        return back()->with('success', 'Reembolso registrado y pendiente de aprobación.');
    }

    public function aprobar(RevisarPedidoReembolsoRequest $request, PedidoReembolso $reembolso)
    {
        $cierreAutomatico = false;
        $pedidoCompletado = false;

        DB::transaction(function () use ($request, $reembolso, &$cierreAutomatico, &$pedidoCompletado) {
            $reembolso = PedidoReembolso::query()
                ->with('pedido.pagos')
                ->lockForUpdate()
                ->findOrFail($reembolso->id);

            if ($reembolso->estado !== 'pendiente') {
                throw ValidationException::withMessages(['reembolso' => 'Este reembolso ya fue revisado.']);
            }

            $pagadoBruto = (float) $reembolso->pedido->pagos->where('estado', 'aprobado')->sum('valor_bruto');
            $yaAprobado = (float) $reembolso->pedido->reembolsos()
                ->where('estado', 'aprobado')
                ->where('id', '!=', $reembolso->id)
                ->sum('valor');

            if ($yaAprobado + (float) $reembolso->valor > $pagadoBruto) {
                throw ValidationException::withMessages(['reembolso' => 'El valor supera los pagos aprobados.']);
            }

            $estadoFinancieroAnterior = $reembolso->pedido->estado_financiero;
            $estadoPedidoAnterior = $reembolso->pedido->estado;

            $reembolso->update([
                'estado' => 'aprobado',
                'aprobado_por' => $request->user()->id,
                'aprobado_at' => now(),
            ]);

            PedidoHistorial::create([
                'pedido_id' => $reembolso->pedido_id,
                'usuario_id' => $request->user()->id,
                'evento' => 'reembolso_aprobado',
                'descripcion' => "Se aprobó el reembolso #{$reembolso->id} por {$reembolso->valor} {$reembolso->moneda}.",
            ]);

            $pedido = $this->totales->recalcular($reembolso->pedido);

            if ($pedido->estado === 'completado' && $estadoPedidoAnterior !== 'completado') {
                $pedidoCompletado = true;
                $pedido->forceFill([
                    'completado_por' => $request->user()->id,
                    'completado_at' => $pedido->completado_at ?: now(),
                ])->save();
                PedidoHistorial::create([
                    'pedido_id' => $pedido->id,
                    'usuario_id' => $request->user()->id,
                    'evento' => 'pedido_completado_con_reembolso',
                    'descripcion' => 'El pedido quedó completado: las unidades restantes fueron entregadas y las demás quedaron reembolsadas.',
                ]);
            }

            if ($pedido->estado_financiero === 'reembolsado') {
                $cierreAutomatico = $estadoFinancieroAnterior !== 'reembolsado';
                $pedido->forceFill([
                    'reembolsado_por' => $pedido->reembolsado_por ?: $request->user()->id,
                    'reembolsado_at' => $pedido->reembolsado_at ?: now(),
                ])->save();

                if ($cierreAutomatico) {
                    PedidoHistorial::create([
                        'pedido_id' => $pedido->id,
                        'usuario_id' => $request->user()->id,
                        'evento' => 'pedido_cerrado_reembolso',
                        'descripcion' => 'El reembolso cubrió todos los pagos aprobados y el pedido quedó reembolsado.',
                        'datos_anteriores' => ['estado_financiero' => $estadoFinancieroAnterior],
                        'datos_nuevos' => ['estado' => 'reembolsado', 'estado_financiero' => 'reembolsado'],
                    ]);
                }
            }

            $this->resumen->recalcular(now());
        });

        $mensaje = match (true) {
            $cierreAutomatico => 'Reembolso total aprobado. El pedido quedó en estado reembolsado.',
            $pedidoCompletado => 'Reembolso aprobado. Las demás unidades ya estaban entregadas y el pedido quedó completado.',
            default => 'Reembolso aprobado correctamente. El pedido conserva su seguimiento por tratarse de un reembolso parcial.',
        };

        return back()->with('success', $mensaje);
    }

    public function rechazar(RevisarPedidoReembolsoRequest $request, PedidoReembolso $reembolso)
    {
        $request->validate(['motivo_rechazo' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $reembolso) {
            $reembolso = PedidoReembolso::query()->lockForUpdate()->findOrFail($reembolso->id);
            if ($reembolso->estado !== 'pendiente') {
                throw ValidationException::withMessages(['reembolso' => 'Este reembolso ya fue revisado.']);
            }

            $reembolso->update([
                'estado' => 'rechazado',
                'rechazado_por' => $request->user()->id,
                'rechazado_at' => now(),
                'motivo_rechazo' => $request->string('motivo_rechazo')->toString(),
            ]);

            PedidoHistorial::create([
                'pedido_id' => $reembolso->pedido_id,
                'usuario_id' => $request->user()->id,
                'evento' => 'reembolso_rechazado',
                'descripcion' => 'Reembolso rechazado: '.$request->string('motivo_rechazo')->toString(),
            ]);
        });

        return back()->with('success', 'Reembolso rechazado.');
    }

    public function relacionarDetalle(Request $request, PedidoReembolso $reembolso)
    {
        $datos = $request->validate([
            'pedido_detalle_id' => ['required', 'integer', 'exists:pedido_detalles,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $reembolso, $datos) {
            $reembolso = PedidoReembolso::query()->lockForUpdate()->findOrFail($reembolso->id);

            if ($reembolso->estado === 'rechazado') {
                throw ValidationException::withMessages([
                    'reembolso' => 'No puedes relacionar un juego con un reembolso rechazado.',
                ]);
            }

            if ($reembolso->pedido_detalle_id) {
                throw ValidationException::withMessages([
                    'reembolso' => 'Este reembolso ya tiene un juego relacionado.',
                ]);
            }

            $pedido = Pedido::query()->lockForUpdate()->findOrFail($reembolso->pedido_id);
            $detalle = $pedido->detalles()->with('entregas')->lockForUpdate()->find($datos['pedido_detalle_id']);

            if (! $detalle) {
                throw ValidationException::withMessages([
                    'pedido_detalle_id' => 'El juego seleccionado no pertenece a este pedido.',
                ]);
            }

            $otrasComprometidas = (int) $pedido->reembolsos()
                ->where('id', '!=', $reembolso->id)
                ->where('pedido_detalle_id', $detalle->id)
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->lockForUpdate()
                ->get()
                ->sum('cantidad');
            $cantidadReemplazada = (int) $detalle->resolucionesOrigen()
                ->where('tipo', 'reemplazo')
                ->sum('cantidad');
            $entregasActivas = $detalle->entregas->where('estado', '!=', 'anulada')->count();
            // Una venta anulada vuelve a estar disponible para relacionarla con el
            // reembolso; esto no modifica si la cuenta era reutilizable o no.
            $disponible = max(0, (int) $detalle->cantidad - $entregasActivas - $cantidadReemplazada - $otrasComprometidas);

            if ((int) $datos['cantidad'] > $disponible) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La cantidad supera las unidades pendientes sin venta de este juego.',
                ]);
            }

            $reembolso->update($datos);

            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $request->user()->id,
                'evento' => 'reembolso_relacionado_detalle',
                'descripcion' => "El reembolso #{$reembolso->id} se relacionó con {$datos['cantidad']} unidad(es) de {$detalle->juego}.",
                'datos_nuevos' => $datos,
            ]);

            if ($reembolso->estado === 'aprobado') {
                $estadoAnterior = $pedido->estado;
                $pedido = $this->totales->recalcular($pedido);

                if ($pedido->estado === 'completado' && $estadoAnterior !== 'completado') {
                    $pedido->forceFill([
                        'completado_por' => $request->user()->id,
                        'completado_at' => $pedido->completado_at ?: now(),
                    ])->save();
                    PedidoHistorial::create([
                        'pedido_id' => $pedido->id,
                        'usuario_id' => $request->user()->id,
                        'evento' => 'pedido_completado_con_reembolso',
                        'descripcion' => 'El pedido quedó completado al relacionar el juego pendiente con un reembolso aprobado.',
                    ]);
                }
            }
        }, 3);

        return back()->with('success', 'El juego quedó relacionado con el reembolso.');
    }

    public function comprobante(PedidoReembolso $reembolso)
    {
        abort_unless(
            $reembolso->comprobante_path && Storage::disk('local')->exists($reembolso->comprobante_path),
            404
        );

        return Storage::disk('local')->response($reembolso->comprobante_path);
    }
}
