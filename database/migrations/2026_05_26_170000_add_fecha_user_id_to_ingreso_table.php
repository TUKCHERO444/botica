<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ingreso', 'fecha')) {
            Schema::table('ingreso', function (Blueprint $table) {
                $table->date('fecha')->nullable()->after('proveedor_id_proveedor');
            });
        }

        if (Schema::hasColumn('ingreso', 'user_id')) {
            Schema::table('ingreso', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }

        Schema::table('ingreso', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('fecha');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('ingreso', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropColumn('fecha');
        });
    }
};
