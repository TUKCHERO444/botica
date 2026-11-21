<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodo_pago', function (Blueprint $table) {
            $table->id('id_metodo_pago');
            $table->string('nombre', 50)->unique();
            $table->string('descripcion', 100)->nullable();
            $table->boolean('estado')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodo_pago');
    }
};
