<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_venta', function (Blueprint $table) {
            $table->id('id_detalle_venta');
            $table->integer('venta_id_venta');
            $table->integer('producto_id_producto');
            $table->foreignId('producto_presentacion_id')
                ->constrained('producto_presentacion', 'id_producto_presentacion');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('factor', 12, 3);
            $table->decimal('cantidad_base', 12, 3);
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('sub_total', 10, 2);

            $table->foreign('venta_id_venta', 'detalle_venta_venta_FK')
                ->references('id_venta')->on('venta');
            $table->foreign('producto_id_producto', 'detalle_venta_producto_FK')
                ->references('id_producto')->on('producto');

            $table->index(['venta_id_venta', 'producto_id_producto'], 'detalle_venta_producto_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_venta');
    }
};
