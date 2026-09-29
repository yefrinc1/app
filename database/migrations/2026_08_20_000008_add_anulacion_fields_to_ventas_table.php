<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('estado', 20)->default('activa')->after('medio_pago')->index();
            $table->timestamp('anulada_at')->nullable()->after('estado');
            $table->foreignId('anulada_por')->nullable()->after('anulada_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulada_por');
            $table->dropColumn(['estado', 'anulada_at']);
        });
    }
};
