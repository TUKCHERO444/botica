<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('slider_producto', function (Blueprint $table) {
            $table->id('id_slider_producto');
            $table->enum('slider_tipo', ['buscados', 'vendidos', 'descuentos']);
            $table->integer('producto_id_producto')->unsigned(false);
            $table->foreign('producto_id_producto')
                  ->references('id_producto')
                  ->on('producto')
                  ->onDelete('cascade');
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->unique(['slider_tipo', 'producto_id_producto']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slider_producto');
    }
};
