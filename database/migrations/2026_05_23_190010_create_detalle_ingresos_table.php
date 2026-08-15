<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_ingreso', function (Blueprint $table) {
            $table->integer('ingreso_id_ingreso');
            $table->integer('producto_id_producto');
            $table->integer('cantidad');

            $table->primary(['ingreso_id_ingreso', 'producto_id_producto'], 'detalle_ingreso_PK');
            $table->foreign('ingreso_id_ingreso', 'detalle_ingreso_ingreso_FK')
                ->references('id_ingreso')->on('ingreso');
            $table->foreign('producto_id_producto', 'detalle_ingreso_producto_FK')
                ->references('id_producto')->on('producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ingreso');
    }
};
