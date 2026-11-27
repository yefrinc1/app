<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['estado', 'created_at', 'consola', 'tipo_cuenta'], 'ventas_analisis_idx');
        });
        Schema::table('correo_juegos', function (Blueprint $table) {
            $table->index(['juego', 'disponible', 'es_cuenta_madre'], 'inventario_analisis_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->dropIndex('ventas_analisis_idx'));
        Schema::table('correo_juegos', fn (Blueprint $table) => $table->dropIndex('inventario_analisis_idx'));
    }
};
