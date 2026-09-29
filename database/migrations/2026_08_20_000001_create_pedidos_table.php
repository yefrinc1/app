<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable()->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('canal_venta', 30)->default('manual');
            $table->char('moneda', 3)->default('COP');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('estado_pago', 20)->default('pendiente');
            $table->string('estado_entrega', 30)->default('pendiente');
            $table->string('estado', 20)->default('borrador');
            $table->text('observaciones')->nullable();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('completado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completado_at')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelado_at')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index(['estado_pago', 'estado_entrega']);
            $table->index(['cliente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
