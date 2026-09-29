<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_entregas', function (Blueprint $table) {
            $table->timestamp('anulada_at')->nullable()->after('entregado_at');
            $table->foreignId('anulada_por')->nullable()->after('anulada_at')->constrained('users')->nullOnDelete();
            $table->text('motivo_anulacion')->nullable()->after('anulada_por');
            $table->boolean('inventario_liberado')->default(false)->after('motivo_anulacion');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_entregas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn(['anulada_at', 'motivo_anulacion', 'inventario_liberado']);
        });
    }
};
