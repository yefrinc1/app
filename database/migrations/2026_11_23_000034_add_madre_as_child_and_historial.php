<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correo_juegos', function (Blueprint $table) {
            $table->boolean('es_cuenta_madre')->default(false)->after('disponible');
        });

        Schema::create('correo_madre_hijos_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correo_madre_id')->constrained('correo_madre')->cascadeOnDelete();
            $table->unsignedBigInteger('correo_juego_id')->nullable();
            $table->boolean('es_cuenta_madre')->default(false);
            $table->string('correo');
            $table->string('contrasena')->nullable();
            $table->string('juego')->nullable();
            $table->dateTime('fecha_nacimiento')->nullable();
            $table->string('accion', 30);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correo_madre_hijos_historial');

        Schema::table('correo_juegos', function (Blueprint $table) {
            $table->dropColumn('es_cuenta_madre');
        });
    }
};
