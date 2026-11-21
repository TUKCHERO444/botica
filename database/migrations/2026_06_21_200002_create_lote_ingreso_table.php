<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lote_ingreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_lote_id')->constrained('producto_lote')->cascadeOnDelete();
            $table->integer('ingreso_id')->nullable();
            $table->unsignedBigInteger('detalle_ingreso_id')->nullable();
            $table->foreignId('producto_presentacion_id')
                ->constrained('producto_presentacion', 'id_producto_presentacion');
            $table->decimal('cantidad_presentacion', 12, 3);
            $table->decimal('factor', 12, 3);
            $table->decimal('cantidad_base', 12, 3);
            $table->decimal('cantidad_disponible_base', 12, 3);
            $table->decimal('costo_unitario_base', 12, 4)->nullable();
            $table->decimal('costo_total', 12, 2)->nullable();
            $table->timestamps();

            $table->foreign('ingreso_id')->references('id_ingreso')->on('ingreso')->nullOnDelete();
            $table->foreign('detalle_ingreso_id')->references('id_detalle_ingreso')->on('detalle_ingreso')->nullOnDelete();
            $table->unique('detalle_ingreso_id');
            $table->index(['producto_lote_id', 'cantidad_disponible_base'], 'lote_ingreso_disponible_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_ingreso');
    }
};
