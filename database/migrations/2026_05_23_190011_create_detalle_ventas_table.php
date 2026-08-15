<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_venta', function (Blueprint $table) {
            $table->decimal('sub_total', 10, 2);
            $table->integer('cantidad');
            $table->integer('venta_id_venta');
            $table->integer('producto_id_producto');

            $table->primary(['venta_id_venta', 'producto_id_producto'], 'detalle_venta_PK');
            $table->foreign('venta_id_venta', 'detalle_venta_venta_FK')
                ->references('id_venta')->on('venta');
            $table->foreign('producto_id_producto', 'detalle_venta_producto_FK')
                ->references('id_producto')->on('producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_venta');
    }
};
