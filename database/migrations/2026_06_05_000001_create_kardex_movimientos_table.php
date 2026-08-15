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
            $table->enum('tipo_movimiento', ['INGRESO', 'VENTA', 'ATENCION']);
            $table->string('referencia_tipo', 50);
            $table->unsignedBigInteger('referencia_id');
            $table->string('referencia_codigo', 50);
            $table->integer('cantidad');
            $table->integer('stock_anterior');
            $table->integer('stock_nuevo');
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
