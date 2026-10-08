<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_unificaciones', function (Blueprint $table) {
            $table->id();
            // IDs históricos sin FK para conservar auditoría tras futuras eliminaciones.
            $table->unsignedBigInteger('principal_id_original')->index();
            $table->unsignedBigInteger('duplicado_id_original')->index();
            $table->unsignedBigInteger('operador_id_original');
            $table->text('motivo');
            $table->json('antes');
            $table->json('despues');
            $table->json('transferencias');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_unificaciones');
    }
};
