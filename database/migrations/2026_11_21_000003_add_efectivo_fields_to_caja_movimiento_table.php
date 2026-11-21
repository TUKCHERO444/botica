<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->foreignId('metodo_pago_id')
                ->nullable()
                ->after('referencia_id')
                ->constrained('metodo_pago', 'id_metodo_pago');
            $table->boolean('afecta_efectivo')->default(true)->after('metodo_pago_id');
            $table->string('categoria', 40)->nullable()->after('afecta_efectivo');
            $table->string('naturaleza', 20)->default('operativo')->after('categoria');
            $table->integer('movimiento_origen_id')->nullable()->after('naturaleza');
            $table->integer('caja_origen_id')->nullable()->after('movimiento_origen_id');
            $table->string('comprobante', 100)->nullable()->after('caja_origen_id');

            $table->index('caja_id_caja', 'caja_movimiento_caja_idx');
            $table->index(['referencia_tipo', 'referencia_id'], 'caja_movimiento_referencia_idx');
            $table->index('categoria', 'caja_movimiento_categoria_idx');
            $table->index('created_at', 'caja_movimiento_created_idx');
        });

        $this->backfill();
    }

    /**
     * Separa los movimientos históricos de ventas/atenciones usando el desglose
     * de `pago`, para que el efectivo esperado no incluya cobros digitales.
     */
    private function backfill(): void
    {
        $movimientos = DB::table('caja_movimiento')
            ->where('tipo', 'ingreso')
            ->whereIn('referencia_tipo', ['venta', 'atencion'])
            ->orderBy('id_movimiento')
            ->get();

        foreach ($movimientos as $movimiento) {
            $tipoPago = $movimiento->referencia_tipo === 'venta' ? 'Venta' : 'Atencion';
            $pagos = DB::table('pago')
                ->where('referencia_tipo', $tipoPago)
                ->where('referencia_id', $movimiento->referencia_id)
                ->orderBy('id_pago')
                ->get();

            if ($pagos->isEmpty()) {
                continue;
            }

            $suma = '0.00';

            foreach ($pagos as $pago) {
                $suma = bcadd($suma, (string) $pago->monto, 2);
            }

            if (bccomp($suma, (string) $movimiento->monto, 2) !== 0) {
                continue;
            }

            foreach ($pagos as $indice => $pago) {
                $metodo = DB::table('metodo_pago')->where('id_metodo_pago', $pago->metodo_pago_id)->first();
                $afecta = $metodo ? (bool) $metodo->afecta_efectivo : false;
                $categoria = $movimiento->referencia_tipo;

                if ($indice === 0) {
                    DB::table('caja_movimiento')
                        ->where('id_movimiento', $movimiento->id_movimiento)
                        ->update([
                            'metodo_pago_id' => $pago->metodo_pago_id,
                            'afecta_efectivo' => $afecta,
                            'categoria' => $categoria,
                            'naturaleza' => 'operativo',
                        ]);

                    continue;
                }

                DB::table('caja_movimiento')->insert([
                    'caja_id_caja' => $movimiento->caja_id_caja,
                    'tipo' => $movimiento->tipo,
                    'monto' => $pago->monto,
                    'descripcion' => $movimiento->descripcion,
                    'referencia_tipo' => $movimiento->referencia_tipo,
                    'referencia_id' => $movimiento->referencia_id,
                    'metodo_pago_id' => $pago->metodo_pago_id,
                    'afecta_efectivo' => $afecta,
                    'categoria' => $categoria,
                    'naturaleza' => 'operativo',
                    'movimiento_origen_id' => null,
                    'caja_origen_id' => null,
                    'comprobante' => null,
                    'user_id' => $movimiento->user_id,
                    'created_at' => $movimiento->created_at ?? now(),
                ]);
            }
        }

        // Movimientos manuales y de compras/devoluciones: completar categoría y afectación.
        DB::table('caja_movimiento')->where('referencia_tipo', 'egreso_manual')->update([
            'categoria' => 'otro_egreso',
            'naturaleza' => 'operativo',
        ]);

        DB::table('caja_movimiento')->where('referencia_tipo', 'compra')->update([
            'categoria' => 'compra',
            'naturaleza' => 'operativo',
        ]);

        DB::table('caja_movimiento')->where('referencia_tipo', 'devolucion_compra')->update([
            'categoria' => 'devolucion_compra',
            'naturaleza' => 'operativo',
        ]);

        // Compras y devoluciones: la afectación depende del método guardado en su documento.
        $compras = DB::table('caja_movimiento as cm')
            ->join('ingreso as i', 'i.id_ingreso', '=', 'cm.referencia_id')
            ->where('cm.referencia_tipo', 'compra')
            ->select('cm.id_movimiento', 'i.metodo_pago')
            ->get();

        foreach ($compras as $compra) {
            DB::table('caja_movimiento')->where('id_movimiento', $compra->id_movimiento)->update([
                'afecta_efectivo' => mb_strtolower((string) $compra->metodo_pago) === 'efectivo',
            ]);
        }

        $devoluciones = DB::table('caja_movimiento as cm')
            ->join('devolucion_proveedor as dp', 'dp.id_devolucion_proveedor', '=', 'cm.referencia_id')
            ->where('cm.referencia_tipo', 'devolucion_compra')
            ->select('cm.id_movimiento', 'dp.metodo_pago')
            ->get();

        foreach ($devoluciones as $devolucion) {
            DB::table('caja_movimiento')->where('id_movimiento', $devolucion->id_movimiento)->update([
                'afecta_efectivo' => mb_strtolower((string) $devolucion->metodo_pago) === 'efectivo',
            ]);
        }

        // Anulaciones históricas: si la venta original tuvo efectivo, el egreso afecta caja.
        $egresosVenta = DB::table('caja_movimiento')
            ->where('referencia_tipo', 'venta')
            ->where('tipo', 'egreso')
            ->get();

        foreach ($egresosVenta as $egreso) {
            $tuvoEfectivo = DB::table('caja_movimiento')
                ->where('referencia_tipo', 'venta')
                ->where('referencia_id', $egreso->referencia_id)
                ->where('tipo', 'ingreso')
                ->where('afecta_efectivo', 1)
                ->exists();

            DB::table('caja_movimiento')->where('id_movimiento', $egreso->id_movimiento)->update([
                'afecta_efectivo' => $tuvoEfectivo,
            ]);
        }

        DB::table('caja_movimiento')
            ->where('referencia_tipo', 'venta')
            ->where('tipo', 'egreso')
            ->update([
                'categoria' => 'anulacion_venta',
                'naturaleza' => 'reversion',
            ]);

        DB::table('caja_movimiento')
            ->where('referencia_tipo', 'atencion')
            ->where('tipo', 'egreso')
            ->update([
                'categoria' => 'ajuste_atencion',
                'naturaleza' => 'reversion',
            ]);

        // Anulaciones de compras/devoluciones: son reversiones, no operaciones nuevas.
        DB::table('caja_movimiento')
            ->where('referencia_tipo', 'compra')
            ->where('tipo', 'ingreso')
            ->update(['naturaleza' => 'reversion']);

        DB::table('caja_movimiento')
            ->where('referencia_tipo', 'devolucion_compra')
            ->where('tipo', 'egreso')
            ->update(['naturaleza' => 'reversion']);

        $this->backfillTotales();
    }

    /**
     * Recalcula los totales de cada caja desde sus movimientos.
     */
    private function backfillTotales(): void
    {
        DB::table('caja')->orderBy('id_caja')->chunk(100, function ($cajas) {
            foreach ($cajas as $caja) {
                $totales = DB::table('caja_movimiento')
                    ->where('caja_id_caja', $caja->id_caja)
                    ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos")
                    ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) as egresos")
                    ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' AND afecta_efectivo = 1 THEN monto ELSE 0 END), 0) as ingresos_efectivo")
                    ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'egreso' AND afecta_efectivo = 1 THEN monto ELSE 0 END), 0) as egresos_efectivo")
                    ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' AND afecta_efectivo = 0 THEN monto ELSE 0 END), 0) as ingresos_digital")
                    ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'egreso' AND afecta_efectivo = 0 THEN monto ELSE 0 END), 0) as egresos_digital")
                    ->first();

                DB::table('caja')->where('id_caja', $caja->id_caja)->update([
                    'total_ingresos' => $totales->ingresos,
                    'total_ingresos_efectivo' => $totales->ingresos_efectivo,
                    'total_ingresos_digital' => $totales->ingresos_digital,
                    'total_egresos' => $totales->egresos,
                    'total_egresos_efectivo' => $totales->egresos_efectivo,
                    'total_egresos_digital' => $totales->egresos_digital,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            $table->dropIndex('caja_movimiento_caja_idx');
            $table->dropIndex('caja_movimiento_referencia_idx');
            $table->dropIndex('caja_movimiento_categoria_idx');
            $table->dropIndex('caja_movimiento_created_idx');
            $table->dropForeign(['metodo_pago_id']);
            $table->dropColumn([
                'metodo_pago_id',
                'afecta_efectivo',
                'categoria',
                'naturaleza',
                'movimiento_origen_id',
                'caja_origen_id',
                'comprobante',
            ]);
        });
    }
};
