<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_ingreso', function (Blueprint $table) {
            $table->foreignId('producto_lote_id')
                ->nullable()
                ->after('producto_presentacion_id')
                ->constrained('producto_lote')
                ->nullOnDelete();
            $table->string('lote_codigo', 50)->nullable()->after('producto_lote_id');
            $table->date('fecha_vencimiento')->nullable()->after('lote_codigo');
            $table->decimal('costo_unitario_base', 12, 4)->nullable()->after('cantidad_base');
            $table->decimal('costo_total', 12, 2)->nullable()->after('costo_unitario_base');
        });

        Schema::table('detalle_venta', function (Blueprint $table) {
            $table->decimal('costo_total', 12, 2)->nullable()->after('sub_total');
        });

        Schema::table('atencion_producto', function (Blueprint $table) {
            $table->decimal('costo_total', 12, 2)->nullable()->after('sub_total');
        });

        Schema::table('kardex_movimientos', function (Blueprint $table) {
            $table->foreignId('producto_lote_id')
                ->nullable()
                ->after('producto_presentacion_id')
                ->constrained('producto_lote')
                ->nullOnDelete();
            $table->string('lote_codigo', 50)->nullable()->after('producto_lote_id');
            $table->date('fecha_vencimiento')->nullable()->after('lote_codigo');
            $table->decimal('costo_unitario_base', 12, 4)->nullable()->after('factor');
        });
    }

    public function down(): void
    {
        Schema::table('kardex_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_lote_id');
            $table->dropColumn(['lote_codigo', 'fecha_vencimiento', 'costo_unitario_base']);
        });

        Schema::table('atencion_producto', function (Blueprint $table) {
            $table->dropColumn('costo_total');
        });

        Schema::table('detalle_venta', function (Blueprint $table) {
            $table->dropColumn('costo_total');
        });

        Schema::table('detalle_ingreso', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_lote_id');
            $table->dropColumn(['lote_codigo', 'fecha_vencimiento', 'costo_unitario_base', 'costo_total']);
        });
    }
};
