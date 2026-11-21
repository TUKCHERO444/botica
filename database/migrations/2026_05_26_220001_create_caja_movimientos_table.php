<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_movimiento', function (Blueprint $table) {
            $table->integer('id_movimiento')->autoIncrement();
            $table->integer('caja_id_caja');
            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->decimal('monto', 10, 2);
            $table->text('descripcion')->nullable();
            $table->enum('referencia_tipo', ['venta', 'atencion', 'egreso_manual', 'compra', 'devolucion_compra']);
            $table->integer('referencia_id')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('caja_id_caja', 'movimiento_caja_FK')
                ->references('id_caja')->on('caja');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_movimiento');
    }
};
