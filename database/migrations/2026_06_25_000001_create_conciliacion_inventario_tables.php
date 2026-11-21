<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conciliacion_inventario', function (Blueprint $table) {
            $table->id('id_conciliacion_inventario');
            $table->timestamp('fecha')->useCurrent();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origen', 20)->default('comando');
            $table->integer('total_productos')->default(0);
            $table->integer('productos_con_diferencia')->default(0);
            $table->integer('lotes_inconsistentes')->default(0);
            $table->decimal('diferencia_total', 14, 3)->default(0);
            $table->string('estado', 20)->default('ok');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['fecha', 'estado']);
        });

        Schema::create('detalle_conciliacion_inventario', function (Blueprint $table) {
            $table->id('id_detalle_conciliacion_inventario');
            $table->foreignId('conciliacion_inventario_id');
            $table->foreign('conciliacion_inventario_id', 'det_conc_inv_conciliacion_fk')
                ->references('id_conciliacion_inventario')
                ->on('conciliacion_inventario')
                ->cascadeOnDelete();
            $table->integer('producto_id');
            $table->foreign('producto_id', 'det_conc_inv_producto_fk')
                ->references('id_producto')
                ->on('producto')
                ->cascadeOnDelete();
            $table->decimal('stock_cache', 14, 3);
            $table->decimal('stock_lotes', 14, 3);
            $table->decimal('diferencia', 14, 3);
            $table->integer('lotes_inconsistentes')->default(0);
            $table->timestamps();

            $table->index('conciliacion_inventario_id', 'detalle_conciliacion_idx');
        });

        Schema::create('detalle_conciliacion_lote', function (Blueprint $table) {
            $table->id('id_detalle_conciliacion_lote');
            $table->foreignId('detalle_conciliacion_inventario_id');
            $table->foreign('detalle_conciliacion_inventario_id', 'det_conc_lote_detalle_fk')
                ->references('id_detalle_conciliacion_inventario')
                ->on('detalle_conciliacion_inventario')
                ->cascadeOnDelete();
            $table->foreignId('producto_lote_id')->constrained('producto_lote');
            $table->decimal('cantidad_lote', 14, 3);
            $table->decimal('suma_capas', 14, 3);
            $table->decimal('diferencia', 14, 3);
            $table->timestamps();

            $table->index('detalle_conciliacion_inventario_id', 'detalle_conciliacion_lote_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_conciliacion_lote');
        Schema::dropIfExists('detalle_conciliacion_inventario');
        Schema::dropIfExists('conciliacion_inventario');
    }
};
