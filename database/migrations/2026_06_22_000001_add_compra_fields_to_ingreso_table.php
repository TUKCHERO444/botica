<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingreso', function (Blueprint $table) {
            // El correlativo se asigna recién al recibir la compra.
            $table->string('correlativo', 20)->nullable()->change();

            $table->string('estado', 20)->default('recibida')->after('correlativo');
            $table->string('tipo_documento', 30)->nullable()->after('fecha');
            $table->string('numero_documento', 30)->nullable()->after('tipo_documento');
            $table->decimal('subtotal', 12, 2)->default(0)->after('numero_documento');
            $table->decimal('total', 12, 2)->default(0)->after('subtotal');
            $table->text('observaciones')->nullable()->after('total');

            $table->date('fecha_recepcion')->nullable()->after('observaciones');
            $table->unsignedBigInteger('recepcion_user_id')->nullable()->after('fecha_recepcion');

            $table->boolean('pagado_desde_caja')->default(false)->after('recepcion_user_id');
            $table->string('metodo_pago', 20)->nullable()->after('pagado_desde_caja');
            $table->string('referencia_pago', 100)->nullable()->after('metodo_pago');
            $table->integer('caja_id')->nullable()->after('referencia_pago');
            $table->integer('egreso_caja_id')->nullable()->after('caja_id');

            $table->timestamp('anulada_at')->nullable()->after('egreso_caja_id');
            $table->string('motivo_anulacion', 255)->nullable()->after('anulada_at');

            $table->timestamps();

            $table->unique(
                ['proveedor_id_proveedor', 'tipo_documento', 'numero_documento'],
                'ingreso_documento_unique'
            );

            $table->foreign('recepcion_user_id', 'ingreso_recepcion_user_FK')
                ->references('id')->on('users');
        });

        Schema::table('detalle_ingreso', function (Blueprint $table) {
            $table->decimal('costo_unitario', 12, 4)->nullable()->after('cantidad_base');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ingreso', function (Blueprint $table) {
            $table->dropColumn('costo_unitario');
        });

        Schema::table('ingreso', function (Blueprint $table) {
            $table->dropUnique('ingreso_documento_unique');
            $table->dropForeign('ingreso_recepcion_user_FK');
            $table->dropColumn([
                'estado',
                'tipo_documento',
                'numero_documento',
                'subtotal',
                'total',
                'observaciones',
                'fecha_recepcion',
                'recepcion_user_id',
                'pagado_desde_caja',
                'metodo_pago',
                'referencia_pago',
                'caja_id',
                'egreso_caja_id',
                'anulada_at',
                'motivo_anulacion',
                'created_at',
                'updated_at',
            ]);
        });
    }
};
