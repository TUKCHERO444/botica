<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex_movimientos', function (Blueprint $table) {
            $table->id();
            $table->integer('producto_id');
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();

            $table->foreign('producto_id')->references('id_producto')->on('producto')->cascadeOnDelete();
            $table->enum('tipo_movimiento', ['INGRESO', 'VENTA', 'ATENCION', 'AJUSTE', 'BAJA', 'DEVOLUCION', 'REVERSION']);
            $table->string('referencia_tipo', 50);
            $table->unsignedBigInteger('referencia_id');
            $table->string('referencia_codigo', 50);
            $table->decimal('cantidad', 12, 3);
            $table->decimal('stock_anterior', 12, 3);
            $table->decimal('stock_nuevo', 12, 3);
            $table->foreignId('producto_presentacion_id')
                ->nullable()
                ->constrained('producto_presentacion', 'id_producto_presentacion')
                ->nullOnDelete();
            $table->decimal('cantidad_presentacion', 12, 3)->nullable();
            $table->string('unidad_presentacion', 100)->nullable();
            $table->decimal('factor', 12, 3)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index('producto_id');
            $table->index('tipo_movimiento');
            $table->index(['referencia_tipo', 'referencia_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex_movimientos');
    }
};
