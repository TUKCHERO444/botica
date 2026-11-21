<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_ingreso', function (Blueprint $table) {
            $table->id('id_detalle_ingreso');
            $table->integer('ingreso_id_ingreso');
            $table->integer('producto_id_producto');
            $table->foreignId('producto_presentacion_id')
                ->constrained('producto_presentacion', 'id_producto_presentacion');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('factor', 12, 3);
            $table->decimal('cantidad_base', 12, 3);

            $table->foreign('ingreso_id_ingreso', 'detalle_ingreso_ingreso_FK')
                ->references('id_ingreso')->on('ingreso');
            $table->foreign('producto_id_producto', 'detalle_ingreso_producto_FK')
                ->references('id_producto')->on('producto');

            $table->index(['ingreso_id_ingreso', 'producto_id_producto'], 'detalle_ingreso_producto_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ingreso');
    }
};
