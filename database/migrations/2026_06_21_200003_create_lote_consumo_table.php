<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lote_consumo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_ingreso_id')->constrained('lote_ingreso')->cascadeOnDelete();
            $table->foreignId('producto_lote_id')->constrained('producto_lote')->cascadeOnDelete();
            $table->string('referencia_tipo', 30);
            $table->unsignedBigInteger('referencia_id');
            $table->foreignId('producto_presentacion_id')
                ->nullable()
                ->constrained('producto_presentacion', 'id_producto_presentacion')
                ->nullOnDelete();
            $table->decimal('cantidad_base', 12, 3);
            $table->decimal('costo_unitario_base', 12, 4)->nullable();
            $table->decimal('costo_total', 12, 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['referencia_tipo', 'referencia_id'], 'lote_consumo_referencia_idx');
            $table->index('lote_ingreso_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_consumo');
    }
};
