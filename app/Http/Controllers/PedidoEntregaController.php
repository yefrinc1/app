<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoEntrega;
use App\Models\PedidoHistorial;
use App\Services\InventarioAsignacionService;
use App\Services\PedidoTotalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoEntregaController extends Controller
{
    public function __construct(
        private InventarioAsignacionService $inventario,
        private PedidoTotalesService $totales,
    ) {
        $this->middleware('can:pedidos.entregar');
    }

    public function generar(Request $request, Pedido $pedido)
    {
        $resultado = $this->inventario->generarDisponibles($pedido, $request->user()->id);
        $generadas = count($resultado['generadas']);
        $pendientes = count($resultado['pendientes']);

        $faltantes = collect($resultado['pendientes'])
            ->map(fn (array $item) => "{$item['juego']} ({$item['tipo_cuenta']} {$item['consola']})")
            ->unique()
            ->implode(', ');

        if ($generadas === 0 && $pendientes > 0) {
            $mensajeFaltante = "No se generaron ventas. Falta inventario o códigos para: {$faltantes}. La notificación correspondiente quedó registrada.";

            return back()->with(['warning' => $mensajeFaltante, 'success' => $mensajeFaltante]);
        }

        $mensaje = "Se generaron {$generadas} venta(s).";
        $deduccionAplicada = (int) collect($resultado['generadas'])->sum('deduccion_liquidacion');
        if ($deduccionAplicada > 0) {
            $mensaje .= ' Se aplicaron $'.number_format($deduccionAplicada, 0, ',', '.').' de deducciones del registro neto.';
        }
        if ($pendientes > 0) {
            $mensajeFaltante = "{$mensaje} No fue posible generar {$pendientes} producto(s). Falta inventario o códigos para: {$faltantes}. La notificación correspondiente quedó registrada.";

            return back()->with(['warning' => $mensajeFaltante, 'success' => $mensajeFaltante]);
        }

        return back()->with('success', $mensaje);
    }

    public function confirmar(Request $request, PedidoEntrega $entrega)
    {
        DB::transaction(function () use ($request, $entrega) {
            $entrega = PedidoEntrega::query()
                ->with('detalle.pedido')
                ->lockForUpdate()
                ->findOrFail($entrega->id);

            if ($entrega->detalle->pedido->estado_financiero === 'reembolsado') {
                throw ValidationException::withMessages([
                    'pedido' => 'El pedido fue reembolsado totalmente y está cerrado.',
                ]);
            }

            if ($entrega->estado === 'anulada') {
                throw ValidationException::withMessages([
                    'entrega' => 'No se puede entregar una venta anulada.',
                ]);
            }

            if ($entrega->estado === 'entregada') {
                throw ValidationException::withMessages([
                    'entrega' => 'Esta cuenta ya fue marcada como entregada.',
                ]);
            }

            $entrega->update([
                'estado' => 'entregada',
                'entregado_at' => now(),
            ]);

            $pedido = $entrega->detalle->pedido;
            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $request->user()->id,
                'evento' => 'cuenta_entregada',
                'descripcion' => "Se confirmó la entrega #{$entrega->id} de {$entrega->detalle->juego}.",
                'datos_nuevos' => ['entrega_id' => $entrega->id, 'venta_id' => $entrega->venta_id],
            ]);

            $pedido = $this->totales->recalcular($pedido);
            if ($pedido->estado === 'completado' && ! $pedido->completado_por) {
                $pedido->update(['completado_por' => $request->user()->id]);
            }
        });

        return back()->with('success', 'Entrega confirmada correctamente.');
    }

    public function completar(Request $request, Pedido $pedido)
    {
        DB::transaction(function () use ($request, $pedido) {
            $pedido = Pedido::query()
                ->with(['detalles.entregas', 'detalles.resolucionesOrigen'])
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            if (in_array($pedido->estado, ['cancelado', 'reembolsado'], true) || $pedido->estado_financiero === 'reembolsado') {
                throw ValidationException::withMessages([
                    'pedido' => 'No puedes completar un pedido cancelado o reembolsado totalmente.',
                ]);
            }

            if (! in_array($pedido->estado_financiero, ['pagado', 'reembolso_parcial'], true)) {
                throw ValidationException::withMessages([
                    'pago' => 'No puedes completar el pedido sin pago aprobado o con un estado financiero pendiente.',
                ]);
            }

            $reembolsadasPorDetalle = $pedido->reembolsos()
                ->where('estado', 'aprobado')
                ->whereNotNull('pedido_detalle_id')
                ->get()
                ->groupBy('pedido_detalle_id')
                ->map(fn ($reembolsos) => (int) $reembolsos->sum('cantidad'));

            $solicitadas = (int) $pedido->detalles->sum(
                fn ($detalle) => max(
                    0,
                    (int) $detalle->cantidad
                        - (int) $reembolsadasPorDetalle->get($detalle->id, 0)
                        - (int) $detalle->resolucionesOrigen->where('tipo', 'reemplazo')->sum('cantidad')
                )
            );
            $asignadas = (int) $pedido->detalles->sum(
                fn ($detalle) => $detalle->entregas->where('estado', '!=', 'anulada')->count()
            );

            if ($asignadas < $solicitadas) {
                throw ValidationException::withMessages([
                    'inventario' => 'Todavía hay productos pendientes de inventario.',
                ]);
            }

            $entregas = PedidoEntrega::query()
                ->whereHas('detalle', fn ($query) => $query->where('pedido_id', $pedido->id))
                ->where('estado', 'asignada')
                ->lockForUpdate()
                ->get();

            foreach ($entregas as $entrega) {
                $entrega->update(['estado' => 'entregada', 'entregado_at' => now()]);
            }

            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $request->user()->id,
                'evento' => 'pedido_entregado',
                'descripcion' => 'Se confirmaron todas las cuentas como entregadas.',
                'datos_nuevos' => ['entregas_confirmadas' => $entregas->pluck('id')->all()],
            ]);

            $pedido = $this->totales->recalcular($pedido);
            $pedido->update([
                'completado_por' => $request->user()->id,
                'completado_at' => now(),
            ]);
        });

        return back()->with('success', 'Pedido completado correctamente.');
    }
}
