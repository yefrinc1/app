<?php

namespace App\Services;

use App\Models\CodigoVerificacion;
use App\Models\CorreoJuego;
use App\Models\Notificaciones;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\PedidoEntrega;
use App\Models\PedidoHistorial;
use App\Models\Ventas;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioAsignacionService
{
    public function __construct(
        private PedidoTotalesService $totales,
        private ResumenMensualService $resumen,
    ) {
    }

    public function generarDisponibles(Pedido $pedido, int $usuarioId): array
    {
        return DB::transaction(function () use ($pedido, $usuarioId) {
            $pedido = Pedido::query()
                ->with(['cliente', 'pagos.liquidaciones', 'detalles.entregas'])
                ->lockForUpdate()
                ->findOrFail($pedido->id);

            if (in_array($pedido->estado, ['cancelado', 'reembolsado'], true) || $pedido->estado_financiero === 'reembolsado') {
                throw ValidationException::withMessages([
                    'pedido' => 'No se pueden generar ventas para un pedido cancelado o reembolsado totalmente.',
                ]);
            }

            if (! in_array($pedido->estado_financiero, ['pagado', 'reembolso_parcial'], true)) {
                throw ValidationException::withMessages([
                    'pago' => 'El pedido debe estar pagado o tener un reembolso parcial aprobado.',
                ]);
            }

            $pedido->forceFill(['inventario_intentado_at' => now()])->save();

            $generadas = [];
            $pendientes = [];

            foreach ($pedido->detalles as $detalleOriginal) {
                $detalle = PedidoDetalle::query()
                    ->with(['entregas', 'resolucionesOrigen'])
                    ->lockForUpdate()
                    ->findOrFail($detalleOriginal->id);

                $asignadas = $detalle->entregas->where('estado', '!=', 'anulada')->count();
                $anuladas = $detalle->entregas->where('estado', 'anulada')->count();
                $reemplazadas = (int) $detalle->resolucionesOrigen
                    ->where('tipo', 'reemplazo')
                    ->sum('cantidad');
                $cantidadEnReembolso = (int) $pedido->reembolsos()
                    ->where('pedido_detalle_id', $detalle->id)
                    ->whereIn('estado', ['pendiente', 'aprobado'])
                    ->sum('cantidad');
                // Una unidad anulada queda a la espera de reembolso o decisión
                // administrativa; nunca debe volver a generarse como inventario faltante.
                $anuladasSinResolver = max(0, $anuladas - $reemplazadas);
                $faltantes = max(0, $detalle->cantidad - $asignadas - $anuladasSinResolver - $reemplazadas - $cantidadEnReembolso);

                if ($faltantes > 0) {
                    $detalle->forceFill(['inventario_intentado_at' => now()])->save();
                }

                for ($unidad = 0; $unidad < $faltantes; $unidad++) {
                    $seleccion = $this->buscarCuentaYCodigo($detalle);

                    if (! $seleccion) {
                        $pendientes[] = [
                            'detalle_id' => $detalle->id,
                            'juego' => $detalle->juego,
                            'tipo_cuenta' => $detalle->tipo_cuenta,
                            'consola' => $detalle->consola,
                            'motivo' => 'Sin inventario o sin códigos disponibles',
                        ];
                        $this->notificarFaltante($detalle);
                        break;
                    }

                    [$correoJuego, $codigo, $licencia] = $seleccion;

                    $precioVenta = $this->calcularPrecioVenta($pedido, $detalle);
                    $metodos = $pedido->pagos
                        ->where('estado', 'aprobado')
                        ->pluck('metodo_pago')
                        ->unique()
                        ->implode(', ');

                    $venta = Ventas::create([
                        'cliente_id' => $pedido->cliente_id,
                        'pedido_detalle_id' => $detalle->id,
                        'id_correo_juego' => $correoJuego->id,
                        'cliente' => $this->nombreCliente($pedido),
                        'tipo_cuenta' => $detalle->tipo_cuenta,
                        'consola' => $detalle->consola,
                        'precio' => $precioVenta['neto'],
                        'precio_bruto_pedido' => $precioVenta['bruto'],
                        'deduccion_liquidacion' => $precioVenta['deduccion'],
                        'medio_pago' => mb_substr($metodos ?: 'Pedido', 0, 255),
                        'moneda' => $pedido->moneda,
                        'id_usuario' => $usuarioId,
                    ]);

                    if ($codigo) {
                        $codigo->update(['disponible' => 0]);
                    }

                    $correoJuego->increment($licencia);
                    $correoJuego->refresh();

                    if (($correoJuego->primaria_ps4 + $correoJuego->primaria_ps5 + $correoJuego->secundaria) >= 5) {
                        $correoJuego->update(['disponible' => 0]);
                    }

                    $entrega = PedidoEntrega::create([
                        'pedido_detalle_id' => $detalle->id,
                        'venta_id' => $venta->id,
                        'correo_juego_id' => $correoJuego->id,
                        'codigo_verificacion_id' => $codigo?->id,
                        'estado' => 'asignada',
                        'asignado_por' => $usuarioId,
                        'asignado_at' => now(),
                    ]);

                    $detalle->increment('cantidad_generada');
                    $detalle->refresh();
                    $detalle->update([
                        'estado' => $detalle->cantidad_generada >= $detalle->cantidad
                            ? 'generado'
                            : 'parcial',
                    ]);

                    $generadas[] = [
                        'entrega_id' => $entrega->id,
                        'venta_id' => $venta->id,
                        'detalle_id' => $detalle->id,
                        'juego' => $detalle->juego,
                        'tipo_cuenta' => $detalle->tipo_cuenta,
                        'consola' => $detalle->consola,
                        'correo' => $correoJuego->correo,
                        'precio_bruto' => $precioVenta['bruto'],
                        'deduccion_liquidacion' => $precioVenta['deduccion'],
                        'precio_neto' => $precioVenta['neto'],
                    ];
                }
            }

            $pedido = $this->totales->recalcular($pedido);

            PedidoHistorial::create([
                'pedido_id' => $pedido->id,
                'usuario_id' => $usuarioId,
                'evento' => 'inventario_generado',
                'descripcion' => count($generadas).' venta(s) generada(s); '.count($pendientes).' detalle(s) pendiente(s).',
                'datos_nuevos' => [
                    'ventas_generadas' => collect($generadas)->pluck('venta_id')->all(),
                    'pendientes' => $pendientes,
                ],
            ]);

            if (count($generadas) > 0) {
                $this->resumen->recalcular(now());
            }

            return [
                'generadas' => $generadas,
                'pendientes' => $pendientes,
                'estado_pedido' => $pedido->estado,
            ];
        }, 3);
    }

    private function buscarCuentaYCodigo(PedidoDetalle $detalle): ?array
    {
        $licencia = $detalle->tipo_cuenta === 'Primaria'
            ? ($detalle->consola === 'PS4' ? 'primaria_ps4' : 'primaria_ps5')
            : 'secundaria';

        $query = CorreoJuego::query()
            ->where('juego', $detalle->juego)
            ->where('disponible', 1);

        if ($detalle->tipo_cuenta === 'Primaria') {
            $query->where($licencia, '<', 2)
                ->orderByRaw("CASE WHEN {$licencia} = 1 AND secundaria = 0 THEN 0 ELSE 1 END")
                ->orderBy('secundaria');
        } else {
            $primariaConsola = $detalle->consola === 'PS4' ? 'primaria_ps4' : 'primaria_ps5';
            $query->where($primariaConsola, 2)
                ->where('secundaria', 0);
        }

        $cuentas = $query->orderBy('id')->lockForUpdate()->get();

        foreach ($cuentas as $cuenta) {
            $tieneCodigos = CodigoVerificacion::query()
                ->where('id_correo_juego', $cuenta->id)
                ->exists();

            if (! $tieneCodigos) {
                return [$cuenta, null, $licencia];
            }

            $codigo = CodigoVerificacion::query()
                ->where('id_correo_juego', $cuenta->id)
                ->where('disponible', 1)
                ->where('respaldo', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($codigo) {
                return [$cuenta, $codigo, $licencia];
            }

            $this->notificarCodigos($cuenta);
        }

        return null;
    }

    /**
     * Calcula el precio que se guarda en ventas.
     *
     * El precio bruto conserva la distribución comercial del pedido. Las
     * deducciones de las liquidaciones (bruto - neto) se reparten por igual
     * entre todas las unidades del pedido. El residuo se distribuye de a un
     * peso entre las primeras ventas, por lo que nunca se pierde ni se duplica.
     *
     * @return array{bruto: int, deduccion: int, neto: int}
     */
    private function calcularPrecioVenta(Pedido $pedido, PedidoDetalle $detalle): array
    {
        if ((float) $pedido->subtotal <= 0 || $detalle->cantidad <= 0) {
            return ['bruto' => 0, 'deduccion' => 0, 'neto' => 0];
        }

        $totalAsignadoDetalle = (int) round(
            (float) $pedido->total * ((float) $detalle->subtotal / (float) $pedido->subtotal)
        );

        // Se usa el bruto auditado y no el precio neto. De esta forma, cuando
        // el inventario se genera parcialmente, la deduccion de una venta
        // anterior no aumenta artificialmente el precio de la siguiente.
        $yaRegistrado = (int) Ventas::query()
            ->where('pedido_detalle_id', $detalle->id)
            ->where('estado', 'activa')
            ->selectRaw('COALESCE(SUM(COALESCE(precio_bruto_pedido, precio)), 0) AS total_bruto')
            ->value('total_bruto');

        $unidadesPendientes = max(1, $detalle->cantidad - $detalle->cantidad_generada);
        $saldoDetalle = max(0, $totalAsignadoDetalle - $yaRegistrado);

        $precioBruto = $unidadesPendientes === 1
            ? $saldoDetalle
            : (int) round($saldoDetalle / $unidadesPendientes);

        $deduccion = $this->deduccionParaSiguienteVenta($pedido);

        return [
            'bruto' => $precioBruto,
            'deduccion' => min($precioBruto, $deduccion),
            'neto' => max(0, $precioBruto - $deduccion),
        ];
    }

    /**
     * Distribuye las deducciones de todas las liquidaciones pertenecientes a
     * pagos aprobados. Ejemplo: 5.000 / 3 unidades = 1.667, 1.667 y 1.666.
     */
    private function deduccionParaSiguienteVenta(Pedido $pedido): int
    {
        $deduccionTotal = (int) round(
            $pedido->pagos
                ->where('estado', 'aprobado')
                ->flatMap(fn ($pago) => $pago->liquidaciones)
                ->sum(fn ($liquidacion) => max(
                    0,
                    (float) $liquidacion->valor_bruto - (float) $liquidacion->valor_neto
                ))
        );

        $totalUnidades = (int) $pedido->detalles->sum('cantidad');
        if ($deduccionTotal <= 0 || $totalUnidades <= 0) {
            return 0;
        }

        $ventasGeneradas = Ventas::query()
            ->where('estado', 'activa')
            ->whereHas('pedidoDetalle', fn ($query) => $query->where('pedido_id', $pedido->id))
            ->count();

        if ($ventasGeneradas >= $totalUnidades) {
            return 0;
        }

        $base = intdiv($deduccionTotal, $totalUnidades);
        $residuo = $deduccionTotal % $totalUnidades;

        return $base + ($ventasGeneradas < $residuo ? 1 : 0);
    }

    private function nombreCliente(Pedido $pedido): string
    {
        $cliente = $pedido->cliente;

        return $cliente->nombre
            ?: $cliente->telefono
            ?: $cliente->usuario
            ?: $cliente->email
            ?: "Cliente #{$cliente->id}";
    }

    private function notificarFaltante(PedidoDetalle $detalle): void
    {
        $query = CorreoJuego::query()
            ->where('juego', $detalle->juego)
            ->where('disponible', 1);

        if ($detalle->tipo_cuenta === 'Primaria') {
            $licencia = $detalle->consola === 'PS4' ? 'primaria_ps4' : 'primaria_ps5';
            $query->where($licencia, '<', 2);
        } else {
            $primaria = $detalle->consola === 'PS4' ? 'primaria_ps4' : 'primaria_ps5';
            $query->where($primaria, 2)->where('secundaria', 0);
        }

        // Si existe una cuenta compatible, el bloqueo fue por falta de códigos
        // y ya fue creada la notificación específica de códigos.
        if ($query->exists()) {
            return;
        }

        Notificaciones::firstOrCreate([
            'tipo' => 'crear_juego',
            'juego' => $detalle->juego,
            'mensaje' => "Se necesita crear el juego en cuenta {$detalle->tipo_cuenta} para {$detalle->consola}",
        ]);
    }

    private function notificarCodigos(CorreoJuego $correoJuego): void
    {
        Notificaciones::firstOrCreate([
            'id_correo_juego' => $correoJuego->id,
            'tipo' => 'crear_codigos',
            'mensaje' => 'Se necesita crear codigos',
        ]);
    }
}
