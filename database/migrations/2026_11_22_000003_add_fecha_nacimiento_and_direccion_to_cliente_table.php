<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->date('fecha_nacimiento')->nullable()->after('telefono');
            $table->string('direccion', 255)->nullable()->after('fecha_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropColumn(['fecha_nacimiento', 'direccion']);
        });
    }
};
