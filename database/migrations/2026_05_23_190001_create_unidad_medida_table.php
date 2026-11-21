<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidad_medida', function (Blueprint $table) {
            $table->id('id_unidad_medida');
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 50);
            $table->boolean('permite_fraccion')->default(false);
            $table->foreignId('presentacion_base_id')
                ->nullable()
                ->constrained('presentacion', 'id_presentacion')
                ->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_medida');
    }
};
