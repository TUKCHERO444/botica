<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atencion_producto_lote', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->integer('atencion_id_atencion');
            $table->integer('producto_id_producto');
            $table->integer('lote_id_lote');
            $table->integer('cantidad');

            $table->foreign('atencion_id_atencion', 'atencion_producto_lote_atencion_FK')
                ->references('id_atencion')->on('atencion');
            $table->foreign('producto_id_producto', 'atencion_producto_lote_producto_FK')
                ->references('id_producto')->on('producto');
            $table->foreign('lote_id_lote', 'atencion_producto_lote_lote_FK')
                ->references('id_lote')->on('lote_producto');

            $table->index('atencion_id_atencion', 'atencion_producto_lote_atencion_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atencion_producto_lote');
    }
};
