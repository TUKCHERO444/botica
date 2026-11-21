<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_lote', function (Blueprint $table) {
            $table->id();
            $table->integer('producto_id');
            $table->string('codigo_lote', 50);
            $table->date('fecha_vencimiento');
            $table->string('estado', 20)->default('disponible');
            $table->decimal('cantidad_inicial_base', 12, 3)->default(0);
            $table->decimal('cantidad_disponible_base', 12, 3)->default(0);
            $table->boolean('es_inicial')->default(false);
            $table->timestamps();

            $table->foreign('producto_id')->references('id_producto')->on('producto')->cascadeOnDelete();
            $table->unique(['producto_id', 'codigo_lote', 'fecha_vencimiento'], 'producto_lote_unique');
            $table->index(['producto_id', 'estado', 'fecha_vencimiento'], 'producto_lote_fefo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_lote');
    }
};
