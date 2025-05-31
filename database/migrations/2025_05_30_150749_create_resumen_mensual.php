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
        Schema::create('resumen_mensual', function (Blueprint $table) {
            $table->id();
            $table->string('periodo');
            $table->integer('cantidad');
            $table->integer('ingresos');
            $table->integer('egresos');
            $table->integer('utilidad_neta');
            $table->double('margen_ganancia');
            $table->double('roi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resumen_mensual');
    }
};
