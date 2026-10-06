<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instalacion_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('pedido_entrega_id')->nullable()->constrained('pedido_entregas')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('tipo_archivo', 15);
            $table->string('archivo_path');
            $table->string('nombre_original');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamano_bytes');
            $table->string('estado', 25)->default('en_revision');
            $table->boolean('declaracion_aceptada')->default(false);
            $table->timestamp('enviado_at')->useCurrent();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'estado']);
            $table->index(['pedido_entrega_id', 'estado']);
            $table->index(['venta_id', 'estado']);
        });

        Schema::create('portal_juego_accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pedido_entrega_id')->nullable()->constrained('pedido_entregas')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('accion', 40);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'created_at']);
            $table->index(['pedido_entrega_id', 'created_at']);
            $table->index(['venta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_juego_accesos');
        Schema::dropIfExists('instalacion_evidencias');
    }
};
