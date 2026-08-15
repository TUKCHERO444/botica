<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta', function (Blueprint $table) {
            $table->integer('id_venta')->autoIncrement();
            $table->string('correlativo', 15);
            $table->decimal('total', 10, 2);
            $table->integer('cliente_id_cliente');
            $table->foreignId('user_id')->constrained('users');

            $table->foreign('cliente_id_cliente', 'venta_cliente_FK')
                ->references('id_cliente')->on('cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta');
    }
};
