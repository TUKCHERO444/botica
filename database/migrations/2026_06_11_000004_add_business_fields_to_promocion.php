<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promocion', function (Blueprint $table) {
            $table->enum('tipo_layout', ['principal', 'secundaria', 'terciaria'])->default('secundaria')->after('public_id');
            $table->dropColumn('tipo');

            $table->enum('tipo_promocion', ['descuento', 'combo'])->nullable()->after('tipo_layout');
            $table->enum('ambito', ['producto', 'marca', 'categoria'])->nullable()->after('tipo_promocion');
            $table->integer('ambito_id')->nullable()->after('ambito');
            $table->enum('descuento_tipo', ['porcentaje', 'fijo'])->nullable()->after('ambito_id');
            $table->decimal('descuento_valor', 10, 2)->nullable()->after('descuento_tipo');
            $table->foreignId('combo_id_combo')->nullable()->constrained('combo', 'id_combo')->after('descuento_valor');
        });
    }

    public function down(): void
    {
        Schema::table('promocion', function (Blueprint $table) {
            $table->dropForeign(['combo_id_combo']);
            $table->dropColumn([
                'tipo_promocion', 'ambito', 'ambito_id',
                'descuento_tipo', 'descuento_valor', 'combo_id_combo',
            ]);
            $table->dropColumn('tipo_layout');
        });
        Schema::table('promocion', function (Blueprint $table) {
            $table->enum('tipo', ['principal', 'secundaria', 'terciaria'])->default('secundaria');
        });
    }
};
