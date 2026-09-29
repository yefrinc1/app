<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('metodo_pago', 50);
            $table->decimal('valor_bruto', 14, 2);
            $table->char('moneda', 3)->default('COP');
            $table->string('referencia')->nullable();
            $table->string('comprobante_path')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_at')->nullable();
            $table->foreignId('rechazado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rechazado_at')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index(['pedido_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_pagos');
    }
};
