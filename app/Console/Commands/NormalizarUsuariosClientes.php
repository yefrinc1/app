<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizarUsuariosClientes extends Command
{
    protected $signature = 'clientes:normalizar-usuarios {--dry-run : Revisar sin modificar registros}';

    protected $description = 'Limpia usuarios de clientes y reporta colisiones sin fusionar registros';

    public function handle(): int
    {
        $cambios = 0;
        $conflictos = 0;

        DB::transaction(function () use (&$cambios, &$conflictos) {
            // Se incluyen los eliminados para respetar posibles índices únicos.
            $clientes = Cliente::withTrashed()->orderBy('id')->lockForUpdate()->get(['id', 'usuario']);
            $grupos = [];
            foreach ($clientes as $cliente) {
                $original = $cliente->getRawOriginal('usuario');
                if ($original !== null && ! mb_check_encoding($original, 'UTF-8')) {
                    $conflictos++;
                    $this->warn("Cliente #{$cliente->id}: usuario con codificación inválida; no se modifica.");
                    continue;
                }
                $normalizado = Cliente::normalizarUsuario($original);
                $clave = $normalizado === null ? "vacio:{$cliente->id}" : "usuario:{$normalizado}";
                $grupos[$clave][] = ['id' => $cliente->id, 'original' => $original, 'nuevo' => $normalizado];
            }

            foreach ($grupos as $grupo) {
                if (count($grupo) > 1) {
                    $conflictos++;
                    $ids = implode(', ', array_column($grupo, 'id'));
                    $this->warn("Usuario {$grupo[0]['nuevo']}: conflicto entre clientes {$ids}; no se modifica ninguno.");
                    continue;
                }
                $fila = $grupo[0];
                if ($fila['original'] === $fila['nuevo']) {
                    continue;
                }
                $cambios++;
                $this->line("Cliente #{$fila['id']}: ".($fila['nuevo'] ?? '(vacío → NULL)'));
                if (! $this->option('dry-run')) {
                    // Solo cambia el usuario, sin ejecutar acciones sobre ventas ni pedidos.
                    DB::table('clientes')->where('id', $fila['id'])->update(['usuario' => $fila['nuevo']]);
                }
            }
        });

        $this->info(($this->option('dry-run') ? 'Por actualizar: ' : 'Actualizados: ').$cambios);
        $this->info("Conflictos pendientes: {$conflictos}");

        return $conflictos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
