<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'precio_bruto_pedido')) {
                $table->integer('precio_bruto_pedido')->nullable()->after('precio');
            }

            if (! Schema::hasColumn('ventas', 'deduccion_liquidacion')) {
                $table->integer('deduccion_liquidacion')->default(0)->after('precio_bruto_pedido');
            }
        });

        // Las ventas historicas no tuvieron deduccion. Su precio actual es el
        // bruto de referencia para que las generaciones parciales sigan bien.
        DB::table('ventas')
            ->whereNull('precio_bruto_pedido')
            ->update(['precio_bruto_pedido' => DB::raw('precio')]);
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $columnas = array_values(array_filter([
                Schema::hasColumn('ventas', 'deduccion_liquidacion') ? 'deduccion_liquidacion' : null,
                Schema::hasColumn('ventas', 'precio_bruto_pedido') ? 'precio_bruto_pedido' : null,
            ]));

            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });
    }
};
