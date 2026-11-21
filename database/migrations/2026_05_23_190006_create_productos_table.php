<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto', function (Blueprint $table) {
            $table->integer('id_producto')->autoIncrement();
            $table->string('nombre', 50);
            $table->string('descripcion', 50)->nullable();
            $table->decimal('precio_compra', 12, 4)->nullable();
            $table->decimal('precio_venta', 12, 4);
            $table->smallInteger('estado');
            $table->decimal('inventario', 12, 3);
            $table->decimal('stock', 12, 3);
            $table->decimal('stock_minimo', 12, 3)->default(5);
            $table->foreignId('unidad_base_id')
                ->constrained('unidad_medida', 'id_unidad_medida');
            $table->integer('categoria_id_categoria');
            $table->integer('marca_id_marca');

            $table->foreign('categoria_id_categoria', 'producto_categoria_FK')
                ->references('id_categoria')->on('categoria');
            $table->foreign('marca_id_marca', 'producto_marca_FK')
                ->references('id_marca')->on('marca');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto');
    }
};
