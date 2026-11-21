<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atencion', function (Blueprint $table) {
            $table->string('correlativo', 20)->nullable()->unique()->after('id_atencion');
            $table->string('estado', 20)->default('confirmada')->after('correlativo');
            $table->timestamp('anulada_at')->nullable()->after('estado');
            $table->foreignId('anulada_user_id')
                ->nullable()
                ->after('anulada_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('motivo_anulacion', 255)->nullable()->after('anulada_user_id');
            $table->index(['estado', 'created_at'], 'atencion_estado_fecha_idx');
        });

        DB::table('atencion')
            ->select('id_atencion')
            ->chunkById(500, function ($atenciones) {
                foreach ($atenciones as $atencion) {
                    DB::table('atencion')
                        ->where('id_atencion', $atencion->id_atencion)
                        ->update([
                            'correlativo' => 'A001-'.str_pad((string) $atencion->id_atencion, 8, '0', STR_PAD_LEFT),
                        ]);
                }
            }, 'id_atencion');
    }

    public function down(): void
    {
        Schema::table('atencion', function (Blueprint $table) {
            $table->dropIndex('atencion_estado_fecha_idx');
            $table->dropUnique('atencion_correlativo_unique');
            $table->dropConstrainedForeignId('anulada_user_id');
            $table->dropColumn(['correlativo', 'estado', 'anulada_at', 'motivo_anulacion']);
        });
    }
};
