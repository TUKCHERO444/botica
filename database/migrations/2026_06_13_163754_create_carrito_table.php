<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrito', function (Blueprint $table) {
            $table->bigIncrements('id_carrito');
            $table->integer('cliente_id_cliente');
            $table->timestamps();

            $table->foreign('cliente_id_cliente')
                  ->references('id_cliente')
                  ->on('cliente')
                  ->onDelete('cascade');

            $table->unique('cliente_id_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrito');
    }
};
