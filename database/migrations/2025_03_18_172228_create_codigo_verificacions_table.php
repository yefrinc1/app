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
        Schema::create('codigo_verificacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_correo_juego')->nullable();
            $table->unsignedBigInteger('id_correo_madre')->nullable();
            $table->foreign('id_correo_madre')->references('id')->on('correo_madre');
            $table->string('codigo');
            $table->integer('disponible')->default(1);
            $table->integer('respaldo')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codigo_verificacion');
    }
};
