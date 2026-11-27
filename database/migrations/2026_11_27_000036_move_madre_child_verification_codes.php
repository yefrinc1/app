<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('correo_juegos')
            ->where('es_cuenta_madre', 1)
            ->whereNotNull('id_correo_madre')
            ->select('id', 'id_correo_madre')
            ->orderBy('id')
            ->each(function ($cuenta) {
                $tieneCodigos = DB::table('codigo_verificacion')
                    ->where('id_correo_juego', $cuenta->id)
                    ->exists();

                if (! $tieneCodigos) {
                    return;
                }

                // Los códigos pegados desde Correos juegos son el banco más reciente
                // y reemplazan el banco anterior, igual que en la edición normal.
                DB::table('codigo_verificacion')
                    ->where('id_correo_madre', $cuenta->id_correo_madre)
                    ->delete();

                DB::table('codigo_verificacion')
                    ->where('id_correo_juego', $cuenta->id)
                    ->update([
                        'id_correo_juego' => null,
                        'id_correo_madre' => $cuenta->id_correo_madre,
                    ]);
            });
    }

    public function down(): void
    {
        // La asociación original no se puede reconstruir de forma inequívoca.
    }
};
