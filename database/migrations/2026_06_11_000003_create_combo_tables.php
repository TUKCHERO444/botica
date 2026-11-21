<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combo', function (Blueprint $table) {
            $table->id('id_combo');
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->text('imagen_url')->nullable();
            $table->string('public_id')->nullable();
            $table->decimal('precio_sugerido', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('combo_producto', function (Blueprint $table) {
            $table->id('id_combo_producto');
            $table->foreignId('combo_id_combo')->constrained('combo', 'id_combo')->onDelete('cascade');
            $table->integer('producto_id_producto');
            $table->foreign('producto_id_producto')->references('id_producto')->on('producto');
            $table->foreignId('producto_presentacion_id')
                ->nullable()
                ->constrained('producto_presentacion', 'id_producto_presentacion')
                ->nullOnDelete();
            $table->decimal('cantidad', 12, 3)->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_producto');
        Schema::dropIfExists('combo');
    }
};
