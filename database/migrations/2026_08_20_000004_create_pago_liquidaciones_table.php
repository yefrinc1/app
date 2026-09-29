<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_pago_id')->constrained('pedido_pagos')->cascadeOnDelete();
            $table->decimal('valor_bruto', 14, 2);
            $table->decimal('comision', 14, 2)->default(0);
            $table->decimal('retencion', 14, 2)->default(0);
            $table->decimal('otros_descuentos', 14, 2)->default(0);
            $table->decimal('valor_neto', 14, 2);
            $table->string('comprobante_path')->nullable();
            $table->dateTime('fecha_liquidacion');
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_liquidaciones');
    }
};
