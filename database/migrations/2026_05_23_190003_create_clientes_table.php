<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente', function (Blueprint $table) {
            $table->integer('id_cliente')->autoIncrement();
            $table->char('dni', 8)->nullable();
            $table->string('nombre', 50);
            $table->string('apellido_paterno', 25)->nullable();
            $table->string('apellido_materno', 25)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};
