<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_imagen', function (Blueprint $table) {
            $table->id('id_producto_imagen');
            $table->integer('producto_id_producto');
            $table->foreign('producto_id_producto')
                  ->references('id_producto')->on('producto')
                  ->onDelete('cascade');
            $table->text('url');
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index(['producto_id_producto', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_imagen');
    }
};
