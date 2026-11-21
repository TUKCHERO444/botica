<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_lote', function (Blueprint $table) {
            $table->string('correlativo', 20)->nullable()->after('id');
        });

        // Backfill para instalaciones que ya tuvieran lotes.
        DB::table('producto_lote')->orderBy('id')->each(function ($lote) {
            DB::table('producto_lote')
                ->where('id', $lote->id)
                ->update(['correlativo' => sprintf('LOTE-%08d', $lote->id)]);
        });

        Schema::table('producto_lote', function (Blueprint $table) {
            $table->unique('correlativo', 'producto_lote_correlativo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('producto_lote', function (Blueprint $table) {
            $table->dropUnique('producto_lote_correlativo_unique');
            $table->dropColumn('correlativo');
        });
    }
};
