<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evento', 60);
            $table->text('descripcion');
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->timestamps();

            $table->index(['pedido_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_historial');
    }
};
