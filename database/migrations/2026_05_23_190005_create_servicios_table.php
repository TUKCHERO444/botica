<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio', function (Blueprint $table) {
            $table->integer('id_servicio')->autoIncrement();
            $table->string('nombre', 25);
            $table->string('descripcion', 100)->nullable();
            $table->decimal('precio', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio');
    }
};
