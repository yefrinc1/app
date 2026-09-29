<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_anulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->unique()->constrained('ventas')->restrictOnDelete();
            $table->foreignId('pedido_entrega_id')->unique()->constrained('pedido_entregas')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->text('motivo');
            $table->boolean('inventario_liberado')->default(false);
            $table->unsignedInteger('valor_anulado')->default(0);
            $table->json('datos_inventario')->nullable();
            $table->foreignId('anulada_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_anulaciones');
    }
};
