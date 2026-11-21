<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrito_item', function (Blueprint $table) {
            $table->bigIncrements('id_carrito_item');
            $table->unsignedBigInteger('carrito_id_carrito');
            $table->string('item_type', 20);
            $table->integer('item_id');
            $table->foreignId('producto_presentacion_id')
                ->nullable()
                ->constrained('producto_presentacion', 'id_producto_presentacion')
                ->nullOnDelete();
            $table->decimal('cantidad', 12, 3)->default(1);
            $table->decimal('precio_unitario', 12, 4)->nullable();
            $table->timestamps();

            $table->foreign('carrito_id_carrito')
                  ->references('id_carrito')
                  ->on('carrito')
                  ->onDelete('cascade');

            $table->unique(
                ['carrito_id_carrito', 'item_type', 'item_id', 'producto_presentacion_id'],
                'carrito_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrito_item');
    }
};
