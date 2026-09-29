<?php

namespace App\Services;

use App\Models\Notificaciones;
use App\Models\PedidoEntrega;
use App\Models\PedidoHistorial;
use App\Models\VentaAnulacion;
use App\Models\Ventas;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaAnulacionService
{
    public function __construct(
        private PedidoTotalesService $totales,
        private ResumenMensualService $resumen,
    ) {
    }

    public function anular(PedidoEntrega $entrega, string $tipo, string $motivo, int $usuarioId): VentaAnulacion
    {
        return DB::transaction(function () use ($entrega, $tipo, $motivo, $usuarioId) {
            $entrega = PedidoEntrega::query()
                ->with(['detalle.pedido', 'venta', 'correoJuego', 'codigoVerificacion'])
                ->lockForUpdate()
                ->findOrFail($entrega->id);

            if ($entrega->estado === 'anulada' || $entrega->anulacion) {
                throw ValidationException::withMessages(['entrega' => 'Esta venta ya fue anulada.']);
            }

            $venta = Ventas::query()->lockForUpdate()->findOrFail($entrega->venta_id);
            $ventaId = (int) $venta->id;
            $fechaVenta = $venta->created_at;
            // El reembolso y el reemplazo se calculan sobre el valor comercial
            // pagado por el cliente. La deduccion de la liquidacion solo afecta
            // el ingreso neto de la venta y nunca reduce el dinero reembolsable.
            $precioBruto = (int) ($venta->precio_bruto_pedido ?? $venta->precio);
            $deduccionLiquidacion = (int) ($venta->deduccion_liquidacion ?? 0);
            $precioNeto = (int) $venta->precio;
            if ($venta->estado === 'anulada') {
                throw ValidationException::withMessages(['venta' => 'Esta venta ya fue anulada.']);
            }

            $correo = $entrega->correoJuego;
            $codigo = $entrega->codigoVerificacion;
            $liberar = $tipo === 'reutilizable';
            $licencia = $venta->tipo_cuenta === 'Primaria'
                ? ($venta->consola === 'PS4' ? 'primaria_ps4' : 'primaria_ps5')
                : 'secundaria';

            $datosInventario = [
                'correo_juego_id' => $correo->id,
                'codigo_verificacion_id' => $codigo?->id,
                'licencia' => $licencia,
                'valor_anterior' => (int) $correo->{$licencia},
                'venta_eliminada' => [
                    'id' => $ventaId,
                    'cliente' => $venta->cliente,
                    'tipo_cuenta' => $venta->tipo_cuenta,
                    'consola' => $venta->consola,
                    'precio' => $precioNeto,
                    'precio_neto' => $precioNeto,
                    'precio_bruto_pedido' => $precioBruto,
                    'deduccion_liquidacion' => $deduccionLiquidacion,
                    'moneda' => $venta->moneda,
                    'medio_pago' => $venta->medio_pago,
                    'created_at' => $venta->created_at?->toDateTimeString(),
                ],
            ];

            if ($liberar) {
                if ((int) $correo->{$licencia} <= 0) {
                    throw ValidationException::withMessages([
                        'inventario' => 'El contador de inventario ya está en cero y no puede reducirse.',
                    ]);
                }

                $correo->update([
                    $licencia => (int) $correo->{$licencia} - 1,
                    'disponible' => 1,
                ]);

                if ($codigo) {
                    $codigo->update(['disponible' => 1]);
                }
            }

            $entrega->update([
                'estado' => 'anulada',
                'anulada_at' => now(),
                'anulada_por' => $usuarioId,
                'motivo_anulacion' => $motivo,
                'inventario_liberado' => $liberar,
            ]);

            $anulacion = VentaAnulacion::create([
                'venta_id' => $ventaId,
                'venta_id_original' => $ventaId,
                'pedido_entrega_id' => $entrega->id,
                'tipo' => $tipo,
                'motivo' => $motivo,
                'inventario_liberado' => $liberar,
                'valor_anulado' => $precioBruto,
                'datos_inventario' => $datosInventario,
                'anulada_por' => $usuarioId,
            ]);

            $pedido = $entrega->detalle->pedido;
            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $usuarioId,
                'evento' => 'venta_anulada',
                'descripcion' => "Se anuló y eliminó la venta #{$ventaId} del pedido {$pedido->codigo}. Inventario ".($liberar ? 'liberado.' : 'no reutilizable.'),
                'datos_nuevos' => [
                    'anulacion_id' => $anulacion->id,
                    'venta_id_eliminada' => $ventaId,
                    'inventario_liberado' => $liberar,
                    'precio_bruto' => $precioBruto,
                    'deduccion_liquidacion' => $deduccionLiquidacion,
                    'precio_neto' => $precioNeto,
                ],
            ]);

            $mensajeNotificacion = "Pedido {$pedido->codigo}: se anuló y eliminó la venta #{$ventaId} de {$entrega->detalle->juego} ({$entrega->detalle->tipo_cuenta} {$entrega->detalle->consola}). Revisar Jumpseller y gestionar el reembolso si corresponde.";

            Notificaciones::create([
                'tipo' => 'sincronizar_jumpseller',
                'juego' => $entrega->detalle->juego,
                'mensaje' => mb_substr($mensajeNotificacion, 0, 255),
            ]);

            // Se elimina mediante Query Builder para cumplir la eliminación física
            // obligatoria. Las FK configuradas con nullOnDelete conservan tanto la
            // entrega como la anulación y dejan venta_id en null.
            $eliminadas = DB::table('ventas')->where('id', $ventaId)->delete();
            if ($eliminadas !== 1) {
                throw ValidationException::withMessages([
                    'venta' => "No fue posible eliminar físicamente la venta #{$ventaId}.",
                ]);
            }

            $this->totales->recalcular($pedido);
            $this->resumen->recalcular($fechaVenta);

            return $anulacion->refresh();
        }, 3);
    }
}
