<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingreso', function (Blueprint $table) {
            $table->integer('id_ingreso')->autoIncrement();
            $table->string('correlativo', 20);
            $table->integer('proveedor_id_proveedor');

            $table->foreign('proveedor_id_proveedor', 'ingreso_proveedor_FK')
                ->references('id_proveedor')->on('proveedor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingreso');
    }
};
