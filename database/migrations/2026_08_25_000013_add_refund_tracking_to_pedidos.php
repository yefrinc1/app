<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->timestamp('inventario_intentado_at')->nullable()->after('estado_entrega');
            $table->foreignId('reembolsado_por')->nullable()->after('cancelado_por')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reembolsado_at')->nullable()->after('reembolsado_por');
        });

        Schema::table('pedido_reembolsos', function (Blueprint $table) {
            $table->foreignId('pedido_detalle_id')->nullable()->after('pedido_pago_id')
                ->constrained('pedido_detalles')->nullOnDelete();
            $table->unsignedInteger('cantidad')->nullable()->after('pedido_detalle_id');
            $table->index(['pedido_detalle_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('pedido_reembolsos', function (Blueprint $table) {
            $table->dropIndex(['pedido_detalle_id', 'estado']);
            $table->dropConstrainedForeignId('pedido_detalle_id');
            $table->dropColumn('cantidad');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reembolsado_por');
            $table->dropColumn(['inventario_intentado_at', 'reembolsado_at']);
        });
    }
};
