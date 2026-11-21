<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucion_proveedor', function (Blueprint $table) {
            $table->id('id_devolucion_proveedor');
            $table->string('correlativo', 20)->nullable()->unique();
            $table->integer('ingreso_id_ingreso');
            $table->integer('proveedor_id_proveedor');
            $table->date('fecha');
            $table->string('motivo', 255);
            $table->string('estado', 20)->default('registrada');
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('reembolsado_a_caja')->default(false);
            $table->string('metodo_pago', 20)->nullable();
            $table->string('referencia_pago', 100)->nullable();
            $table->integer('caja_id')->nullable();
            $table->integer('caja_movimiento_id')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('anulada_at')->nullable();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();

            $table->foreign('ingreso_id_ingreso', 'devolucion_proveedor_ingreso_FK')
                ->references('id_ingreso')->on('ingreso');
            $table->foreign('proveedor_id_proveedor', 'devolucion_proveedor_proveedor_FK')
                ->references('id_proveedor')->on('proveedor');

            $table->index('ingreso_id_ingreso');
            $table->index(['estado', 'fecha']);
        });

        Schema::create('detalle_devolucion_proveedor', function (Blueprint $table) {
            $table->id('id_detalle_devolucion_proveedor');
            $table->foreignId('devolucion_proveedor_id')
                ->constrained('devolucion_proveedor', 'id_devolucion_proveedor')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('detalle_ingreso_id');
            $table->foreignId('lote_ingreso_id')->constrained('lote_ingreso')->cascadeOnDelete();
            $table->integer('producto_id_producto');
            $table->foreignId('producto_presentacion_id')
                ->constrained('producto_presentacion', 'id_producto_presentacion');
            $table->foreignId('producto_lote_id')->constrained('producto_lote');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('factor', 12, 3);
            $table->decimal('cantidad_base', 12, 3);
            $table->decimal('costo_unitario_base', 12, 4);
            $table->decimal('costo_total', 12, 2);
            $table->timestamps();

            $table->foreign('detalle_ingreso_id', 'detalle_devolucion_ingreso_FK')
                ->references('id_detalle_ingreso')->on('detalle_ingreso');
            $table->foreign('producto_id_producto', 'detalle_devolucion_producto_FK')
                ->references('id_producto')->on('producto');

            $table->index('devolucion_proveedor_id', 'detalle_devolucion_proveedor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_devolucion_proveedor');
        Schema::dropIfExists('devolucion_proveedor');
    }
};
