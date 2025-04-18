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
        Schema::create('correo_madre', function (Blueprint $table) {
            $table->id();
            $table->string('correo');
            $table->string('contrasena');
            $table->unsignedBigInteger('id_correo_globales');
            $table->foreign('id_correo_globales')->references('id')->on('correo_globales');
            $table->float('saldo_usd');
            $table->integer('saldo_cop');
            $table->dateTime('fecha_nacimiento');
            $table->integer('disponible')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correo_madre');
    }
};
