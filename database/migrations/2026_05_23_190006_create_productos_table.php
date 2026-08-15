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
            $table->decimal('precio_compra', 10, 2)->nullable();
            $table->decimal('precio_venta', 10, 2);
            $table->smallInteger('estado');
            $table->integer('inventario');
            $table->integer('stock');
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
