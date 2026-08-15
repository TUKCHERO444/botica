<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atencion_producto', function (Blueprint $table) {
            $table->integer('producto_id_producto');
            $table->integer('atencion_id_atencion');
            $table->decimal('sub_total', 10, 2);
            $table->integer('cantidad');

            $table->primary(['producto_id_producto', 'atencion_id_atencion'], 'atencion_producto_PK');
            $table->foreign('atencion_id_atencion', 'atencion_producto_atencion_FK')
                ->references('id_atencion')->on('atencion');
            $table->foreign('producto_id_producto', 'atencion_producto_producto_FK')
                ->references('id_producto')->on('producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atencion_producto');
    }
};
