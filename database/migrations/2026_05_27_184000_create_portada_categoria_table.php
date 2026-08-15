<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portada_categoria', function (Blueprint $table) {
            $table->id('id_portada_categoria');
            $table->integer('categoria_id_categoria');
            $table->foreign('categoria_id_categoria')
                  ->references('id_categoria')
                  ->on('categoria')
                  ->onDelete('cascade');
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->unique('categoria_id_categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portada_categoria');
    }
};
