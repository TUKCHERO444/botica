<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->string('estado', 20)->default('confirmada')->after('user_id');
            $table->timestamp('anulada_at')->nullable()->after('estado');
            $table->foreignId('anulada_user_id')
                ->nullable()
                ->after('anulada_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable()->after('anulada_user_id');
            $table->index(['estado', 'created_at'], 'venta_estado_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table) {
            $table->dropIndex('venta_estado_fecha_idx');
            $table->dropConstrainedForeignId('anulada_user_id');
            $table->dropColumn(['estado', 'anulada_at', 'motivo_anulacion']);
        });
    }
};
