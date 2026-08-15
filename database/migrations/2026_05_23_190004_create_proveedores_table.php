<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedor', function (Blueprint $table) {
            $table->integer('id_proveedor')->autoIncrement();
            $table->char('ruc', 11);
            $table->string('razon_social', 150);
            $table->string('condicion', 15);
            $table->smallInteger('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor');
    }
};
