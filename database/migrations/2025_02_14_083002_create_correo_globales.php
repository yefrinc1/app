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
        Schema::create('correo_globales', function (Blueprint $table) {
            $table->id();
            $table->string('correo');
            $table->unsignedBigInteger('id_correo_principal')->nullable();
            $table->foreign('id_correo_principal')->references('id')->on('correo_principal')->onDelete('cascade');
            $table->integer('disponible')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correo_globales');
    }
};
