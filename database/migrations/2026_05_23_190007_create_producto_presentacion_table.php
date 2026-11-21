<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_presentacion', function (Blueprint $table) {
            $table->id('id_producto_presentacion');
            $table->integer('producto_id_producto');
            $table->foreignId('presentacion_id')
                ->constrained('presentacion', 'id_presentacion');
            $table->string('nombre', 100)->nullable();
            $table->decimal('factor', 12, 3);
            $table->decimal('precio_venta', 12, 4);
            $table->decimal('paso', 12, 3)->default(1);
            $table->boolean('es_base')->default(false);
            $table->boolean('activo')->default(true);
            $table->string('codigo_barras', 50)->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->foreign('producto_id_producto', 'producto_presentacion_producto_FK')
                ->references('id_producto')->on('producto')
                ->cascadeOnDelete();

            $table->unique(['producto_id_producto', 'presentacion_id'], 'producto_presentacion_unique');
            $table->index(['producto_id_producto', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_presentacion');
    }
};
