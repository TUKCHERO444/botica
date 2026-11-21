<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atencion_producto', function (Blueprint $table) {
            $table->id('id_atencion_producto');
            $table->integer('producto_id_producto');
            $table->integer('atencion_id_atencion');
            $table->foreignId('producto_presentacion_id')
                ->constrained('producto_presentacion', 'id_producto_presentacion');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('factor', 12, 3);
            $table->decimal('cantidad_base', 12, 3);
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('sub_total', 10, 2);

            $table->foreign('atencion_id_atencion', 'atencion_producto_atencion_FK')
                ->references('id_atencion')->on('atencion');
            $table->foreign('producto_id_producto', 'atencion_producto_producto_FK')
                ->references('id_producto')->on('producto');

            $table->index(['atencion_id_atencion', 'producto_id_producto'], 'atencion_producto_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atencion_producto');
    }
};
