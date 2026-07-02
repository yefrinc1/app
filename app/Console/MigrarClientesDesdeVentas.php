<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Ventas;
use Illuminate\Console\Command;

class MigrarClientesDesdeVentas extends Command
{
    protected $signature = 'clientes:migrar-desde-ventas';

    protected $description = 'Crea clientes desde el campo cliente de la tabla ventas y asigna cliente_id';

    public function handle(): int
    {
        Ventas::whereNotNull('cliente')
            ->whereNull('cliente_id')
            ->chunkById(100, function ($ventas) {
                foreach ($ventas as $venta) {
                    $cliente = $this->buscarOCrearCliente($venta->cliente);

                    $venta->cliente_id = $cliente->id;
                    $venta->save();

                    $this->info("Venta {$venta->id} asignada al cliente {$cliente->id}");
                }
            });

        $this->info('Migración terminada correctamente.');

        return Command::SUCCESS;
    }

    private function buscarOCrearCliente(string $cliente): Cliente
    {
        $cliente = trim($cliente);

        $soloNumeros = preg_replace('/\D/', '', $cliente);

        if (strlen($soloNumeros) >= 7 && strlen($soloNumeros) <= 15) {
            $codigoPais = '57';
            $telefono = $soloNumeros;

            if (strlen($soloNumeros) > 10) {
                $codigoPais = substr($soloNumeros, 0, strlen($soloNumeros) - 10);
                $telefono = substr($soloNumeros, -10);
            }

            return Cliente::firstOrCreate([
                'codigo_pais' => $codigoPais,
                'telefono' => $telefono,
            ]);
        }

        return Cliente::firstOrCreate([
            'usuario' => strtolower($cliente),
        ]);
    }
}