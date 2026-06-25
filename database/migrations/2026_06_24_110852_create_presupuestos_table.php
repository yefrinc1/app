<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio');
            $table->tinyInteger('mes');
            $table->unsignedInteger('ventas_objetivo')->default(0);
            $table->decimal('ingresos_objetivo', 14, 2)->default(0);
            $table->decimal('utilidad_objetivo', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Solo puede existir un presupuesto por año y mes
            $table->unique(['anio', 'mes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};