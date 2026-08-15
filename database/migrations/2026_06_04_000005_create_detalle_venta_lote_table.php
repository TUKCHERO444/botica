<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_venta_lote', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('venta_id_venta');
            $table->integer('producto_id_producto');
            $table->integer('lote_id_lote');
            $table->integer('cantidad');

            $table->foreign('venta_id_venta', 'detalle_venta_lote_venta_FK')
                ->references('id_venta')->on('venta');
            $table->foreign('producto_id_producto', 'detalle_venta_lote_producto_FK')
                ->references('id_producto')->on('producto');
            $table->foreign('lote_id_lote', 'detalle_venta_lote_lote_FK')
                ->references('id_lote')->on('lote_producto');

            $table->index('venta_id_venta', 'detalle_venta_lote_venta_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_venta_lote');
    }
};
