<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('estado_financiero', 30)
                ->default('pendiente')
                ->after('estado_pago');
            $table->index('estado_financiero');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['estado_financiero']);
            $table->dropColumn('estado_financiero');
        });
    }
};
