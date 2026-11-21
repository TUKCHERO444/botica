<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_inventario_inicial', function (Blueprint $table) {
            $table->decimal('cantidad_presentacion', 14, 3)->default(0)->after('producto_presentacion_id');
            $table->decimal('factor', 14, 3)->default(1)->after('cantidad_presentacion');
            $table->decimal('costo_unitario_presentacion', 14, 4)->default(0)->after('costo_unitario_base');
        });

        DB::table('detalle_inventario_inicial')
            ->orderBy('id_detalle_inventario_inicial')
            ->chunkById(200, function ($detalles) {
                foreach ($detalles as $detalle) {
                    $presentacion = DB::table('producto_presentacion')
                        ->where('id_producto_presentacion', $detalle->producto_presentacion_id)
                        ->first();

                    $factor = (float) ($presentacion->factor ?? 1);
                    $factor = $factor > 0 ? $factor : 1;

                    DB::table('detalle_inventario_inicial')
                        ->where('id_detalle_inventario_inicial', $detalle->id_detalle_inventario_inicial)
                        ->update([
                            'cantidad_presentacion' => round((float) $detalle->cantidad_base / $factor, 3),
                            'factor' => $factor,
                            'costo_unitario_presentacion' => round((float) $detalle->costo_unitario_base * $factor, 4),
                        ]);
                }
            }, 'id_detalle_inventario_inicial');
    }

    public function down(): void
    {
        Schema::table('detalle_inventario_inicial', function (Blueprint $table) {
            $table->dropColumn([
                'cantidad_presentacion',
                'factor',
                'costo_unitario_presentacion',
            ]);
        });
    }
};
