<?php

namespace App\Console\Commands;

use App\Services\VincularVentasAntiguasService;
use Illuminate\Console\Command;

class VincularVentasAntiguas extends Command
{
    protected $signature = 'clientes:vincular-ventas-antiguas';
    protected $description = 'Relaciona ventas antiguas con clientes mediante teléfono, Instagram o correo exactos';

    public function handle(VincularVentasAntiguasService $servicio): int
    {
        $resultado = $servicio->vincularTodas();
        $this->info("Ventas vinculadas: {$resultado['vinculadas']}");
        $this->warn("Sin coincidencia: {$resultado['sin_coincidencia']}");
        $this->warn("Coincidencias ambiguas: {$resultado['ambiguas']}");
        return self::SUCCESS;
    }
}
