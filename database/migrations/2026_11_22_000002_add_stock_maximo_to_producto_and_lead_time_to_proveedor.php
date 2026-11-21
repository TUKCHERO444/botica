<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->decimal('stock_maximo', 12, 3)->nullable()->after('stock_minimo');
        });

        Schema::table('proveedor', function (Blueprint $table) {
            $table->unsignedSmallInteger('lead_time')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn('stock_maximo');
        });

        Schema::table('proveedor', function (Blueprint $table) {
            $table->dropColumn('lead_time');
        });
    }
};
