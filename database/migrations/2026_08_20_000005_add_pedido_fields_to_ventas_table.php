<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addCliente = ! Schema::hasColumn('ventas', 'cliente_id');
        $addDetalle = ! Schema::hasColumn('ventas', 'pedido_detalle_id');

        Schema::table('ventas', function (Blueprint $table) use ($addCliente, $addDetalle) {
            if ($addCliente) {
                $table->foreignId('cliente_id')->nullable()->after('id')->constrained('clientes')->nullOnDelete();
            }

            if ($addDetalle) {
                $table->foreignId('pedido_detalle_id')->nullable()->after('cliente_id')->constrained('pedido_detalles')->nullOnDelete();
                $table->index('pedido_detalle_id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ventas', 'pedido_detalle_id')) {
            Schema::table('ventas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pedido_detalle_id');
            });
        }

        // No se elimina cliente_id porque pudo existir antes de esta migración.
    }
};
