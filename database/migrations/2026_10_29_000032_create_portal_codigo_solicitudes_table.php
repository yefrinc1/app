<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_codigo_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('correo_juego_id');
            $table->foreign('correo_juego_id')->references('id')->on('correo_juegos')->cascadeOnDelete();
            $table->foreignId('pedido_entrega_id')->nullable()->constrained('pedido_entregas')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->unsignedBigInteger('codigo_verificacion_id')->nullable();
            $table->foreign('codigo_verificacion_id')->references('id')->on('codigo_verificacion')->nullOnDelete();
            $table->string('codigo');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'correo_juego_id', 'created_at'], 'portal_codigos_cliente_correo_fecha');
            $table->index(['correo_juego_id', 'created_at'], 'portal_codigos_correo_fecha');
            $table->index(['pedido_entrega_id', 'created_at'], 'portal_codigos_entrega_fecha');
            $table->index(['venta_id', 'created_at'], 'portal_codigos_venta_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_codigo_solicitudes');
    }
};
