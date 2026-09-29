<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('juego');
            $table->string('tipo_cuenta', 20);
            $table->string('consola', 10);
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->unsignedInteger('cantidad_generada')->default(0);
            $table->string('estado', 30)->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['pedido_id', 'estado']);
            $table->index(['juego', 'tipo_cuenta', 'consola']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_detalles');
    }
};
