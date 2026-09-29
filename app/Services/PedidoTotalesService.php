<?php

namespace App\Services;

use App\Models\Pedido;

class PedidoTotalesService
{
    public function recalcular(Pedido $pedido): Pedido
    {
        $pedido->load(['detalles.entregas', 'detalles.resolucionesOrigen', 'pagos', 'reembolsos']);

        $valorReemplazado = (float) $pedido->detalles->sum(
            fn ($detalle) => (float) $detalle->resolucionesOrigen
                ->where('tipo', 'reemplazo')
                ->sum('valor_origen')
        );
        $subtotal = max(0, (float) $pedido->detalles->sum('subtotal') - $valorReemplazado);
        $total = max(0, $subtotal - (float) $pedido->descuento);
        $pagadoBruto = (float) $pedido->pagos->where('estado', 'aprobado')->sum('valor_bruto');
        $reembolsado = (float) $pedido->reembolsos->where('estado', 'aprobado')->sum('valor');
        $pagado = max(0, $pagadoBruto - $reembolsado);

        $estadoPago = match (true) {
            $pagado <= 0 => 'pendiente',
            $pagado < $total => 'parcial',
            default => 'pagado',
        };

        $estadoFinanciero = match (true) {
            $pagadoBruto <= 0 => 'pendiente',
            $reembolsado >= $pagadoBruto => 'reembolsado',
            $reembolsado > 0 => 'reembolso_parcial',
            $pagadoBruto < $total => 'pago_parcial',
            default => 'pagado',
        };

        $cantidadTotal = (int) $pedido->detalles->sum('cantidad');
        $cantidadPorAtender = 0;
        $asignadas = 0;
        $entregadas = 0;

        foreach ($pedido->detalles as $detalle) {
            $cantidadReembolsada = (int) $pedido->reembolsos
                ->where('estado', 'aprobado')
                ->where('pedido_detalle_id', $detalle->id)
                ->sum('cantidad');
            $cantidadReembolsada = min((int) $detalle->cantidad, $cantidadReembolsada);
            $cantidadReemplazada = (int) $detalle->resolucionesOrigen
                ->where('tipo', 'reemplazo')
                ->sum('cantidad');
            $cantidadReemplazada = min((int) $detalle->cantidad, $cantidadReemplazada);
            $cantidadAtenderDetalle = max(0, (int) $detalle->cantidad - $cantidadReembolsada - $cantidadReemplazada);
            $entregasActivas = $detalle->entregas->where('estado', '!=', 'anulada');
            $anuladasDetalle = $detalle->entregas->where('estado', 'anulada')->count();
            $asignadasDetalle = $entregasActivas->count();
            $entregadasDetalle = $entregasActivas->where('estado', 'entregada')->count();
            $cantidadPorAtender += $cantidadAtenderDetalle;
            $asignadas += $asignadasDetalle;
            $entregadas += $entregadasDetalle;

            $estadoDetalle = match (true) {
                $cantidadAtenderDetalle === 0 && $cantidadReemplazada > 0 && $cantidadReembolsada > 0 => 'resuelto',
                $cantidadAtenderDetalle === 0 && $cantidadReemplazada > 0 => 'reemplazado',
                $cantidadAtenderDetalle === 0 => 'reembolsado',
                $entregadasDetalle >= $cantidadAtenderDetalle => 'completado',
                $asignadasDetalle >= $cantidadAtenderDetalle => 'generado',
                $asignadasDetalle > 0 && $anuladasDetalle > 0 => 'parcial_anulado',
                $asignadasDetalle > 0 => 'parcial',
                $anuladasDetalle > 0 => 'anulado',
                default => $detalle->inventario_intentado_at
                    ? 'pendiente_inventario'
                    : 'pendiente_revision',
            };

            $detalle->forceFill([
                'cantidad_generada' => $asignadasDetalle,
                'estado' => $estadoDetalle,
            ])->save();
        }

        $estadoEntrega = match (true) {
            $cantidadTotal > 0 && $cantidadPorAtender === 0 => 'completado',
            $cantidadPorAtender > 0 && $entregadas >= $cantidadPorAtender => 'completado',
            $entregadas > 0 => 'parcial',
            default => 'pendiente',
        };

        $canceladoManualmente = $pedido->estado === 'cancelado'
            && $pedido->estado_financiero !== 'reembolsado';

        if ($estadoFinanciero === 'reembolsado') {
            $estado = 'reembolsado';
        } elseif ($canceladoManualmente) {
            $estado = 'cancelado';
        } elseif ($estadoFinanciero === 'reembolso_parcial' && $estadoEntrega === 'completado') {
            $estado = 'completado';
        } else {
            $estado = ($estadoPago === 'pagado' && $estadoEntrega === 'completado')
                ? 'completado'
                : ($estadoPago === 'pendiente' && $asignadas === 0 ? 'pendiente' : 'en_proceso');
        }

        $pedido->forceFill([
            'subtotal' => $subtotal,
            'total' => $total,
            'estado_pago' => $estadoPago,
            'estado_financiero' => $estadoFinanciero,
            'estado_entrega' => $estadoEntrega,
            'estado' => $estado,
            'completado_por' => $estado === 'completado' ? $pedido->completado_por : null,
            'completado_at' => $estado === 'completado'
                ? ($pedido->completado_at ?? now())
                : null,
            'reembolsado_at' => $estadoFinanciero === 'reembolsado'
                ? ($pedido->reembolsado_at ?? now())
                : null,
        ])->save();

        return $pedido->refresh();
    }
}
