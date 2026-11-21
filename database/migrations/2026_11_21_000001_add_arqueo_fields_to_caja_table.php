<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->decimal('total_ingresos_efectivo', 12, 2)->default(0)->after('total_ingresos');
            $table->decimal('total_ingresos_digital', 12, 2)->default(0)->after('total_ingresos_efectivo');
            $table->decimal('total_egresos_efectivo', 12, 2)->default(0)->after('total_egresos');
            $table->decimal('total_egresos_digital', 12, 2)->default(0)->after('total_egresos_efectivo');
            $table->decimal('efectivo_esperado', 12, 2)->nullable()->after('monto_final');
            $table->decimal('efectivo_contado', 12, 2)->nullable()->after('efectivo_esperado');
            $table->decimal('diferencia', 12, 2)->nullable()->after('efectivo_contado');
            $table->json('arqueo_detalle')->nullable()->after('diferencia');
            $table->text('observacion_apertura')->nullable()->after('arqueo_detalle');
            $table->text('observacion_cierre')->nullable()->after('observacion_apertura');
            $table->foreignId('arqueo_user_id')->nullable()->after('cierre_user_id')->constrained('users');
            $table->dateTime('arqueo_at')->nullable()->after('cierre_at');
            $table->boolean('abierta_unica')->nullable()->after('estado');
        });

        $abiertas = DB::table('caja')->where('estado', 'abierta')->count();

        if ($abiertas > 1) {
            throw new RuntimeException(
                'Existe más de una caja abierta. Cierre las cajas duplicadas antes de aplicar esta migración.'
            );
        }

        DB::table('caja')->where('estado', 'abierta')->update(['abierta_unica' => 1]);
        DB::table('caja')->where('estado', '!=', 'abierta')->update(['abierta_unica' => null]);

        Schema::table('caja', function (Blueprint $table) {
            $table->unique('abierta_unica', 'caja_abierta_unica_unique');
        });
    }

    public function down(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->dropUnique('caja_abierta_unica_unique');
            $table->dropForeign(['arqueo_user_id']);
            $table->dropColumn([
                'total_ingresos_efectivo',
                'total_ingresos_digital',
                'total_egresos_efectivo',
                'total_egresos_digital',
                'efectivo_esperado',
                'efectivo_contado',
                'diferencia',
                'arqueo_detalle',
                'observacion_apertura',
                'observacion_cierre',
                'arqueo_user_id',
                'arqueo_at',
                'abierta_unica',
            ]);
        });
    }
};
