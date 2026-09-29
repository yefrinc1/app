<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_anulaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('venta_id_original')->nullable()->after('venta_id');
        });

        DB::table('venta_anulaciones')
            ->whereNull('venta_id_original')
            ->update(['venta_id_original' => DB::raw('venta_id')]);

        Schema::table('venta_anulaciones', function (Blueprint $table) {
            $table->dropForeign(['venta_id']);
            $table->dropUnique(['venta_id']);
        });

        DB::statement('ALTER TABLE venta_anulaciones MODIFY venta_id BIGINT UNSIGNED NULL');

        Schema::table('venta_anulaciones', function (Blueprint $table) {
            $table->unique('venta_id');
            $table->foreign('venta_id')->references('id')->on('ventas')->nullOnDelete();
            $table->index('venta_id_original');
        });
    }

    public function down(): void
    {
        Schema::table('venta_anulaciones', function (Blueprint $table) {
            $table->dropForeign(['venta_id']);
            $table->dropUnique(['venta_id']);
            $table->dropIndex(['venta_id_original']);
        });

        DB::statement('ALTER TABLE venta_anulaciones MODIFY venta_id BIGINT UNSIGNED NOT NULL');

        Schema::table('venta_anulaciones', function (Blueprint $table) {
            $table->unique('venta_id');
            $table->foreign('venta_id')->references('id')->on('ventas')->restrictOnDelete();
            $table->dropColumn('venta_id_original');
        });
    }
};
