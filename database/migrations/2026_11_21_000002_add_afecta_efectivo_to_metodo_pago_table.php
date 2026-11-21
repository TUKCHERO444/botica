<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metodo_pago', function (Blueprint $table) {
            $table->boolean('afecta_efectivo')->default(true)->after('estado');
        });

        // Los métodos digitales no deben inflar el efectivo esperado del cajón.
        DB::table('metodo_pago')
            ->whereRaw('LOWER(nombre) NOT LIKE ?', ['%efectivo%'])
            ->update(['afecta_efectivo' => false]);
    }

    public function down(): void
    {
        Schema::table('metodo_pago', function (Blueprint $table) {
            $table->dropColumn('afecta_efectivo');
        });
    }
};
