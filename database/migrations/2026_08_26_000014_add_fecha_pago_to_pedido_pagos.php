<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_pagos', function (Blueprint $table) {
            $table->dateTime('fecha_pago')->nullable()->after('moneda');
            $table->index('fecha_pago');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_pagos', function (Blueprint $table) {
            $table->dropIndex(['fecha_pago']);
            $table->dropColumn('fecha_pago');
        });
    }
};
