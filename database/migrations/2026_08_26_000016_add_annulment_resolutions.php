<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_detalles', function (Blueprint $table) {
            $table->timestamp('inventario_intentado_at')->nullable()->after('cantidad_generada');
        });

        DB::statement(
            'UPDATE pedido_detalles d INNER JOIN pedidos p ON p.id = d.pedido_id '
            .'SET d.inventario_intentado_at = p.inventario_intentado_at'
        );

        Schema::create('pedido_anulacion_resoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_entrega_id')->unique('par_entrega_unique');
            $table->foreignId('pedido_detalle_origen_id');
            $table->foreignId('pedido_detalle_reemplazo_id')->nullable();
            $table->string('tipo', 30);
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('valor_origen', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('resuelto_por');
            $table->timestamp('resuelto_at')->useCurrent();
            $table->timestamps();

            $table->foreign('pedido_entrega_id', 'par_entrega_fk')->references('id')->on('pedido_entregas')->restrictOnDelete();
            $table->foreign('pedido_detalle_origen_id', 'par_origen_fk')->references('id')->on('pedido_detalles')->restrictOnDelete();
            $table->foreign('pedido_detalle_reemplazo_id', 'par_reemplazo_fk')->references('id')->on('pedido_detalles')->restrictOnDelete();
            $table->foreign('resuelto_por', 'par_usuario_fk')->references('id')->on('users')->restrictOnDelete();
            $table->index(['pedido_detalle_origen_id', 'tipo'], 'par_origen_tipo_idx');
        });

        Schema::table('pedido_reembolsos', function (Blueprint $table) {
            $table->foreignId('pedido_entrega_anulada_id')
                ->nullable()
                ->after('pedido_detalle_id')
                ->constrained('pedido_entregas')
                ->restrictOnDelete();
            $table->index(['pedido_entrega_anulada_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('pedido_reembolsos', function (Blueprint $table) {
            $table->dropForeign(['pedido_entrega_anulada_id']);
            $table->dropIndex(['pedido_entrega_anulada_id', 'estado']);
            $table->dropColumn('pedido_entrega_anulada_id');
        });

        Schema::dropIfExists('pedido_anulacion_resoluciones');

        Schema::table('pedido_detalles', function (Blueprint $table) {
            $table->dropColumn('inventario_intentado_at');
        });
    }
};
