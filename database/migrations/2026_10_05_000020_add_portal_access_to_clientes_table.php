<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')
                ->constrained('users')->nullOnDelete();
            $table->char('portal_token_hash', 64)->nullable()->unique()->after('notas');
            $table->timestamp('portal_token_expires_at')->nullable()->after('portal_token_hash');
            $table->timestamp('portal_activated_at')->nullable()->after('portal_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropUnique(['portal_token_hash']);
            $table->dropColumn(['portal_token_hash', 'portal_token_expires_at', 'portal_activated_at']);
        });
    }
};
