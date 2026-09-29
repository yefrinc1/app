<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Models\PedidoHistorial;
use App\Services\PedidoTotalesService;
use Illuminate\Console\Command;

class RecalcularEstadosFinancierosPedidos extends Command
{
    protected $signature = 'pedidos:recalcular-estados-financieros {--pedido=}';
    protected $description = 'Recalcula pagos, reembolsos y cierre automático de los pedidos';

    public function handle(PedidoTotalesService $totales): int
    {
        $query = Pedido::query()->orderBy('id');

        if ($pedidoId = $this->option('pedido')) {
            $query->whereKey($pedidoId);
        }

        $procesados = 0;
        $reembolsados = 0;

        $query->chunkById(100, function ($pedidos) use ($totales, &$procesados, &$reembolsados) {
            foreach ($pedidos as $pedido) {
                $estadoAnterior = $pedido->estado_financiero;
                $estadoGeneralAnterior = $pedido->estado;
                $pedido = $totales->recalcular($pedido);
                $procesados++;

                if (($estadoAnterior !== 'reembolsado' || $estadoGeneralAnterior !== 'reembolsado')
                    && $pedido->estado_financiero === 'reembolsado') {
                    $ultimoReembolso = $pedido->reembolsos()
                        ->where('estado', 'aprobado')
                        ->latest('aprobado_at')
                        ->first();
                    $pedido->forceFill([
                        'reembolsado_por' => $pedido->reembolsado_por ?: $ultimoReembolso?->aprobado_por,
                        'reembolsado_at' => $pedido->reembolsado_at ?: ($ultimoReembolso?->aprobado_at ?? now()),
                    ])->save();

                    PedidoHistorial::create([
                        'pedido_id' => $pedido->id,
                        'usuario_id' => null,
                        'evento' => 'pedido_cerrado_reembolso',
                        'descripcion' => 'El pedido quedó en estado reembolsado por devolución total aprobada.',
                        'datos_anteriores' => ['estado_financiero' => $estadoAnterior],
                        'datos_nuevos' => ['estado' => 'reembolsado', 'estado_financiero' => 'reembolsado'],
                    ]);
                    $reembolsados++;
                }
            }
        });

        $this->info("Pedidos recalculados: {$procesados}. Marcados como reembolsados: {$reembolsados}.");

        return self::SUCCESS;
    }
}
