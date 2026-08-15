<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atencion_servicio', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2);
            $table->integer('servicio_id_servicio');
            $table->integer('atencion_id_atencion');

            $table->primary(['servicio_id_servicio', 'atencion_id_atencion'], 'atencion_servicio_PK');
            $table->foreign('atencion_id_atencion', 'atencion_servicio_atencion_FK')
                ->references('id_atencion')->on('atencion');
            $table->foreign('servicio_id_servicio', 'atencion_servicio_servicio_FK')
                ->references('id_servicio')->on('servicio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atencion_servicio');
    }
};
