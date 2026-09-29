<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_pagos', function (Blueprint $table) {
            $table->string('referencia_normalizada', 150)->nullable()->after('referencia');
            $table->char('comprobante_hash', 64)->nullable()->after('comprobante_path');
        });

        $referenciasVistas = [];
        $hashesVistos = [];

        DB::table('pedido_pagos')
            ->select(['id', 'referencia', 'comprobante_path'])
            ->orderBy('id')
            ->chunkById(200, function ($pagos) use (&$referenciasVistas, &$hashesVistos) {
                foreach ($pagos as $pago) {
                    $referencia = $pago->referencia
                        ? preg_replace('/[^A-Z0-9]/u', '', Str::upper(trim($pago->referencia)))
                        : null;

                    if ($referencia === '' || isset($referenciasVistas[$referencia])) {
                        $referencia = null;
                    } elseif ($referencia) {
                        $referenciasVistas[$referencia] = true;
                    }

                    $hash = null;
                    if ($pago->comprobante_path && Storage::disk('local')->exists($pago->comprobante_path)) {
                        $hashCalculado = hash_file('sha256', Storage::disk('local')->path($pago->comprobante_path));
                        if ($hashCalculado && ! isset($hashesVistos[$hashCalculado])) {
                            $hash = $hashCalculado;
                            $hashesVistos[$hashCalculado] = true;
                        }
                    }

                    DB::table('pedido_pagos')->where('id', $pago->id)->update([
                        'referencia_normalizada' => $referencia,
                        'comprobante_hash' => $hash,
                    ]);
                }
            });

        Schema::table('pedido_pagos', function (Blueprint $table) {
            $table->unique('referencia_normalizada', 'pedido_pagos_referencia_normalizada_unique');
            $table->unique('comprobante_hash', 'pedido_pagos_comprobante_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_pagos', function (Blueprint $table) {
            $table->dropUnique('pedido_pagos_referencia_normalizada_unique');
            $table->dropUnique('pedido_pagos_comprobante_hash_unique');
            $table->dropColumn(['referencia_normalizada', 'comprobante_hash']);
        });
    }
};
