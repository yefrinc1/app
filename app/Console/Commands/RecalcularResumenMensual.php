<?php

namespace App\Console\Commands;

use App\Services\ResumenMensualService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalcularResumenMensual extends Command
{
    protected $signature = 'resumen:recalcular {--mes=} {--anio=}';
    protected $description = 'Recalcula el resumen mensual excluyendo ventas anuladas y aplicando reembolsos';

    public function handle(ResumenMensualService $service): int
    {
        $mes = (int) ($this->option('mes') ?: now()->month);
        $anio = (int) ($this->option('anio') ?: now()->year);

        if ($mes < 1 || $mes > 12 || $anio < 2000) {
            $this->error('Mes o año inválido.');
            return self::FAILURE;
        }

        $service->recalcular(Carbon::create($anio, $mes, 1));
        $this->info(sprintf('Resumen %02d/%d recalculado.', $mes, $anio));

        return self::SUCCESS;
    }
}
