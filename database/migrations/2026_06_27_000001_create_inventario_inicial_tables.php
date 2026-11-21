<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_inicial', function (Blueprint $table) {
            $table->id('id_inventario_inicial');
            $table->string('correlativo', 20)->unique();
            $table->date('fecha');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('borrador');
            $table->text('observaciones')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->string('archivo_hash', 64)->nullable();
            $table->unsignedInteger('total_lineas')->default(0);
            $table->decimal('total_costo', 14, 2)->default(0);
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'fecha'], 'inv_inicial_estado_fecha_idx');
        });

        Schema::create('detalle_inventario_inicial', function (Blueprint $table) {
            $table->id('id_detalle_inventario_inicial');
            $table->unsignedBigInteger('inventario_inicial_id');
            $table->foreign('inventario_inicial_id', 'det_ini_inv_header_fk')
                ->references('id_inventario_inicial')
                ->on('inventario_inicial')
                ->cascadeOnDelete();

            $table->integer('producto_id');
            $table->foreign('producto_id', 'det_ini_inv_producto_fk')
                ->references('id_producto')
                ->on('producto')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('producto_presentacion_id');
            $table->foreign('producto_presentacion_id', 'det_ini_inv_presentacion_fk')
                ->references('id_producto_presentacion')
                ->on('producto_presentacion')
                ->cascadeOnDelete();

            $table->string('codigo_lote', 50);
            $table->date('fecha_vencimiento');
            $table->decimal('cantidad_base', 14, 3);
            $table->decimal('costo_unitario_base', 14, 4)->default(0);
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->unsignedBigInteger('producto_lote_id')->nullable();
            $table->foreign('producto_lote_id', 'det_ini_inv_lote_fk')
                ->references('id')
                ->on('producto_lote')
                ->nullOnDelete();
            $table->unsignedBigInteger('lote_ingreso_id')->nullable();
            $table->foreign('lote_ingreso_id', 'det_ini_inv_capa_fk')
                ->references('id')
                ->on('lote_ingreso')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('inventario_inicial_id', 'det_ini_inv_header_idx');
            $table->index(
                ['producto_id', 'codigo_lote', 'fecha_vencimiento'],
                'det_ini_inv_producto_lote_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_inventario_inicial');
        Schema::dropIfExists('inventario_inicial');
    }
};
