<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria', function (Blueprint $table) {
            $table->integer('id_categoria')->autoIncrement();
            $table->string('nombre', 50);
            $table->string('descripcion', 50)->nullable();
            $table->integer('cantidad');
            $table->smallInteger('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria');
    }
};
