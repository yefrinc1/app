<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoAnulacionResolucion;
use App\Models\PedidoDetalle;
use App\Models\PedidoEntrega;
use App\Models\PedidoHistorial;
use App\Services\PedidoTotalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PedidoAnulacionResolucionController extends Controller
{
    public function __construct(private PedidoTotalesService $totales)
    {
        $this->middleware('can:pedidos.anular');
    }

    public function reemplazar(Request $request, PedidoEntrega $entrega)
    {
        $datos = $request->validate([
            'juego' => ['required', 'string', 'max:255'],
            'tipo_cuenta' => ['required', Rule::in(['Primaria', 'Secundaria'])],
            'consola' => ['required', Rule::in(['PS4', 'PS5'])],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'juego.required' => 'Selecciona el juego de reemplazo.',
            'tipo_cuenta.required' => 'Selecciona el tipo de cuenta.',
            'consola.required' => 'Selecciona la consola.',
            'precio_unitario.required' => 'Ingresa el precio del juego de reemplazo.',
        ]);

        $pedido = DB::transaction(function () use ($request, $entrega, $datos) {
            $entrega = PedidoEntrega::query()
                ->with(['detalle.pedido', 'resolucion'])
                ->lockForUpdate()
                ->findOrFail($entrega->id);

            if ($entrega->estado !== 'anulada') {
                throw ValidationException::withMessages([
                    'entrega' => 'Solo puedes resolver una venta que esté anulada.',
                ]);
            }

            if ($entrega->resolucion) {
                throw ValidationException::withMessages([
                    'entrega' => 'Esta anulación ya fue resuelta con un reemplazo.',
                ]);
            }

            if ($entrega->reembolsoResolucion()->exists()) {
                throw ValidationException::withMessages([
                    'entrega' => 'Esta anulación ya tiene un reembolso pendiente o aprobado.',
                ]);
            }

            $reembolsoGeneralComprometido = $entrega->detalle->pedido->reembolsos()
                ->where('pedido_detalle_id', $entrega->pedido_detalle_id)
                ->whereNull('pedido_entrega_anulada_id')
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->exists();

            if ($reembolsoGeneralComprometido) {
                throw ValidationException::withMessages([
                    'entrega' => 'Este juego ya tiene un reembolso general pendiente o aprobado. Relaciónalo correctamente antes de crear un reemplazo.',
                ]);
            }

            $pedido = Pedido::query()->lockForUpdate()->findOrFail($entrega->detalle->pedido_id);
            if (in_array($pedido->estado, ['cancelado', 'reembolsado'], true)) {
                throw ValidationException::withMessages([
                    'pedido' => 'No puedes reemplazar juegos en un pedido cerrado.',
                ]);
            }

            $detalleOrigen = PedidoDetalle::query()->lockForUpdate()->findOrFail($entrega->pedido_detalle_id);
            $valorOrigen = $detalleOrigen->cantidad > 0
                ? round((float) $detalleOrigen->subtotal / (int) $detalleOrigen->cantidad, 2)
                : 0;

            $detalleReemplazo = $pedido->detalles()->create([
                'juego' => $datos['juego'],
                'tipo_cuenta' => $datos['tipo_cuenta'],
                'consola' => $datos['consola'],
                'cantidad' => 1,
                'precio_unitario' => $datos['precio_unitario'],
                'descuento' => 0,
                'subtotal' => $datos['precio_unitario'],
                'cantidad_generada' => 0,
                'inventario_intentado_at' => null,
                'estado' => 'pendiente_revision',
                'observaciones' => ($datos['observaciones'] ?? null)
                    ?: "Reemplazo de la entrega anulada #{$entrega->id}.",
            ]);

            $resolucion = PedidoAnulacionResolucion::create([
                'pedido_entrega_id' => $entrega->id,
                'pedido_detalle_origen_id' => $detalleOrigen->id,
                'pedido_detalle_reemplazo_id' => $detalleReemplazo->id,
                'tipo' => 'reemplazo',
                'cantidad' => 1,
                'valor_origen' => $valorOrigen,
                'observaciones' => $datos['observaciones'] ?? null,
                'resuelto_por' => $request->user()->id,
                'resuelto_at' => now(),
            ]);

            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $request->user()->id,
                'evento' => 'anulacion_resuelta_reemplazo',
                'descripcion' => "La entrega anulada #{$entrega->id} de {$detalleOrigen->juego} se reemplazó por {$detalleReemplazo->juego} ({$detalleReemplazo->tipo_cuenta} {$detalleReemplazo->consola}).",
                'datos_nuevos' => [
                    'resolucion_id' => $resolucion->id,
                    'detalle_origen_id' => $detalleOrigen->id,
                    'detalle_reemplazo_id' => $detalleReemplazo->id,
                    'valor_origen' => $valorOrigen,
                    'valor_reemplazo' => (float) $detalleReemplazo->subtotal,
                ],
            ]);

            return $this->totales->recalcular($pedido);
        }, 3);

        $excedente = max(0, (float) $pedido->total_pagado_bruto - (float) $pedido->total);
        $mensaje = match (true) {
            $pedido->estado_financiero === 'pago_parcial' => 'Reemplazo creado. El nuevo valor es mayor y falta aprobar el pago restante antes de generar la venta.',
            $excedente > 0 => "Reemplazo creado. El nuevo valor es menor y quedaron {$excedente} {$pedido->moneda} disponibles para reembolso.",
            default => 'Reemplazo creado correctamente. Ya puedes buscar inventario para el nuevo juego.',
        };

        return back()->with('success', $mensaje);
    }
}
