<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajuste_inventario', function (Blueprint $table) {
            $table->id('id_ajuste_inventario');
            $table->string('correlativo', 20)->nullable()->unique();
            $table->string('tipo', 30);
            $table->date('fecha');
            $table->string('motivo', 255);
            $table->text('observaciones')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->index(['tipo', 'fecha']);
        });

        Schema::create('detalle_ajuste_inventario', function (Blueprint $table) {
            $table->id('id_detalle_ajuste_inventario');
            $table->foreignId('ajuste_inventario_id')
                ->constrained('ajuste_inventario', 'id_ajuste_inventario')
                ->cascadeOnDelete();
            $table->integer('producto_id_producto');
            $table->foreignId('producto_lote_id')->nullable()->constrained('producto_lote')->nullOnDelete();
            $table->foreignId('producto_presentacion_id')
                ->nullable()
                ->constrained('producto_presentacion', 'id_producto_presentacion')
                ->nullOnDelete();
            $table->foreignId('lote_ingreso_id')->nullable()->constrained('lote_ingreso')->nullOnDelete();
            $table->decimal('cantidad_base', 12, 3);
            $table->decimal('costo_unitario_base', 12, 4)->default(0);
            $table->decimal('costo_total', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('producto_id_producto', 'detalle_ajuste_producto_FK')
                ->references('id_producto')->on('producto');

            $table->index('ajuste_inventario_id', 'detalle_ajuste_inventario_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ajuste_inventario');
        Schema::dropIfExists('ajuste_inventario');
    }
};
