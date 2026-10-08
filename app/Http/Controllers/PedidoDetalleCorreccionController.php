<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoHistorial;
use App\Services\PedidoTotalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PedidoDetalleCorreccionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:pedidos.entregar']);
    }

    private function revisar(Pedido $pedido, PedidoDetalle $detalle): void
    {
        if (in_array($pedido->estado, ['cancelado', 'reembolsado', 'completado'], true)
            || $pedido->estado_financiero === 'reembolsado') {
            throw ValidationException::withMessages(['juego' => 'No puedes modificar un pedido cerrado.']);
        }
        if ($detalle->cantidad_generada > 0 || $detalle->entregas()->exists() || $detalle->ventas()->exists()) {
            throw ValidationException::withMessages(['juego' => 'Este juego ya tuvo ventas o entregas generadas. Utiliza Resolver anulación cuando corresponda.']);
        }
        if ($detalle->resolucionesOrigen()->exists() || $detalle->resolucionReemplazo()->exists()) {
            throw ValidationException::withMessages(['juego' => 'Este juego pertenece a una resolución de anulación y no admite corrección directa.']);
        }
        if ($pedido->reembolsos()->whereIn('estado', ['pendiente', 'aprobado'])->exists()) {
            throw ValidationException::withMessages(['juego' => 'El pedido tiene reembolsos pendientes o aprobados. Debes resolver ese flujo antes de corregir juegos.']);
        }
    }

    private function precioBloqueado(Pedido $pedido): bool
    {
        return $pedido->detalles()->where(function ($q) {
            $q->where('cantidad_generada', '>', 0)->orWhereHas('entregas')->orWhereHas('ventas');
        })->exists();
    }

    private function firma(Pedido $pedido, PedidoDetalle $detalle): string
    {
        return hash('sha256', json_encode([$pedido->getAttributes(), $detalle->getAttributes()], JSON_THROW_ON_ERROR));
    }

    public function edit(Pedido $pedido, int $detalle)
    {
        $detalle = $pedido->detalles()->findOrFail($detalle);
        $this->revisar($pedido, $detalle);
        return response()->json([
            'detalle' => $detalle,
            'firma' => $this->firma($pedido, $detalle),
            'precio_bloqueado' => $this->precioBloqueado($pedido),
            'total' => $pedido->total,
            'moneda' => $pedido->moneda,
        ]);
    }

    public function update(Request $request, Pedido $pedido, int $detalle, PedidoTotalesService $totales)
    {
        $datos = $request->validate([
            'juego' => ['required', 'string', 'max:255'],
            'tipo_cuenta' => ['required', Rule::in(['Primaria', 'Secundaria'])],
            'consola' => ['required', Rule::in(['PS4', 'PS5'])],
            'precio_unitario' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
            'firma' => ['required', 'string', 'size:64'],
        ]);
        $mensaje = DB::transaction(function () use ($pedido, $detalle, $datos, $request, $totales) {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $detalle = $pedido->detalles()->lockForUpdate()->findOrFail($detalle);
            $this->revisar($pedido, $detalle);

            if (!hash_equals($this->firma($pedido, $detalle), $datos['firma'])) {
                throw ValidationException::withMessages(['juego' => 'El pedido cambió. Cierra el formulario y vuelve a abrirlo para revisar los datos.']);
            }
            $precio = round((float) $datos['precio_unitario'], 2);
            if ($pedido->moneda === 'COP' && floor($precio) !== $precio) {
                throw ValidationException::withMessages(['precio_unitario' => 'El precio en COP debe ser un número entero sin puntos ni comas.']);
            }
            if ($this->precioBloqueado($pedido) && $precio !== (float) $detalle->precio_unitario) {
                throw ValidationException::withMessages(['precio_unitario' => 'Ya hay ventas generadas en este pedido. Conserva el precio original para corregir este juego.']);
            }
            $subtotal = round($precio * $detalle->cantidad - (float) $detalle->descuento, 2);
            if ($subtotal < 0) {
                throw ValidationException::withMessages(['precio_unitario' => 'El nuevo valor no puede ser menor que el descuento registrado para este juego.']);
            }

            $antes = $detalle->only(['juego', 'tipo_cuenta', 'consola', 'cantidad', 'precio_unitario', 'descuento', 'subtotal', 'estado']);
            $totalAnterior = $pedido->total;
            $detalle->update([
                'juego' => trim($datos['juego']),
                'tipo_cuenta' => $datos['tipo_cuenta'],
                'consola' => $datos['consola'],
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
                'inventario_intentado_at' => null,
                'estado' => 'pendiente_revision',
            ]);
            $pedido = $totales->recalcular($pedido);
            $despues = $detalle->fresh()->only(array_keys($antes));
            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $request->user()->id,
                'evento' => 'juego_corregido_antes_inventario',
                'descripcion' => 'Detalle #'.$detalle->id.': '.$antes['juego'].' ('.$antes['tipo_cuenta'].' / '.$antes['consola']
                    .') cambiado por '.$despues['juego'].' ('.$despues['tipo_cuenta'].' / '.$despues['consola']
                    .'). Motivo: '.$datos['motivo'].'. Total anterior: '.$totalAnterior.'; nuevo total: '.$pedido->total.'.',
                'datos_anteriores' => array_merge($antes, ['total_pedido' => $totalAnterior]),
                'datos_nuevos' => array_merge($despues, ['total_pedido' => $pedido->total]),
            ]);
            $pagado = (float) $pedido->pagos()->where('estado', 'aprobado')->sum('valor_bruto');
            $diferencia = round((float) $pedido->total - $pagado, 2);
            $mensaje = 'Juego corregido. El pago registrado se conserva y el nuevo juego queda pendiente de revisión.';
            if ($pagado > 0 && $diferencia > 0) {
                $mensaje .= ' Falta aprobar un pago por '.number_format($diferencia, 2, ',', '.').' '.$pedido->moneda.'.';
            } elseif ($diferencia < 0) {
                $mensaje .= ' Existe un excedente de '.number_format(-$diferencia, 2, ',', '.').' '.$pedido->moneda.'; registra su reembolso si corresponde.';
            }
            return $mensaje;
        }, 3);

        return back()->with('success', $mensaje);
    }
}
