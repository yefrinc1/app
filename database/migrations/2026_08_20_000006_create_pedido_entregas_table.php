<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_detalle_id')->constrained('pedido_detalles')->cascadeOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('correo_juego_id')->constrained('correo_juegos')->restrictOnDelete();
            $table->unsignedBigInteger('codigo_verificacion_id')->nullable();
            $table->foreign('codigo_verificacion_id')->references('id')->on('codigo_verificacion')->nullOnDelete();
            $table->string('estado', 20)->default('asignada');
            $table->foreignId('asignado_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('asignado_at')->useCurrent();
            $table->timestamp('entregado_at')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['pedido_detalle_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_entregas');
    }
};
