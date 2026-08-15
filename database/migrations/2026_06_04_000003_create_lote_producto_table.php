<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lote_producto', function (Blueprint $table) {
            $table->integer('id_lote')->autoIncrement();
            $table->integer('producto_id_producto');
            $table->string('codigo_lote', 50);
            $table->date('fecha_vencimiento');
            $table->integer('cantidad')->default(0);
            $table->integer('ingreso_id_ingreso')->nullable();

            $table->foreign('producto_id_producto', 'lote_producto_producto_FK')
                ->references('id_producto')->on('producto');
            $table->foreign('ingreso_id_ingreso', 'lote_producto_ingreso_FK')
                ->references('id_ingreso')->on('ingreso');

            $table->index('fecha_vencimiento', 'lote_producto_vencimiento_idx');
            $table->index(['producto_id_producto', 'codigo_lote'], 'lote_producto_producto_lote_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_producto');
    }
};
