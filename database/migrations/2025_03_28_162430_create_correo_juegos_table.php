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
        Schema::create('correo_juegos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_correo_madre')->nullable();
            $table->foreign('id_correo_madre')->references('id')->on('correo_madre');
            $table->unsignedBigInteger('id_correo_globales');
            $table->foreign('id_correo_globales')->references('id')->on('correo_globales');
            $table->string('juego')->nullable();
            $table->string('correo');
            $table->string('contrasena');
            $table->dateTime('fecha_nacimiento');
            $table->double('precio_usd')->nullable();
            $table->integer('precio_cop')->nullable();
            $table->integer('primaria_ps4')->default(0);
            $table->integer('primaria_ps5')->default(0);
            $table->integer('secundaria')->default(0);
            $table->integer('disponible')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correo_juegos');
    }
};
