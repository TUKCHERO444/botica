<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedor', function (Blueprint $table) {
            $table->string('direccion', 255)->nullable()->after('estado');
            $table->char('ubigeo', 6)->nullable()->after('direccion');
            $table->string('distrito', 100)->nullable()->after('ubigeo');
            $table->string('provincia', 100)->nullable()->after('distrito');
            $table->string('departamento', 100)->nullable()->after('provincia');
        });
    }

    public function down(): void
    {
        Schema::table('proveedor', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'ubigeo', 'distrito', 'provincia', 'departamento']);
        });
    }
};
