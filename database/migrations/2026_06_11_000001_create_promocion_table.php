<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocion', function (Blueprint $table) {
            $table->id('id_promocion');
            $table->string('titulo', 255)->nullable();
            $table->string('subtitulo', 255)->nullable();
            $table->text('imagen_url');
            $table->string('public_id')->nullable();
            $table->string('enlace', 255)->nullable();
            $table->enum('tipo', ['principal', 'secundaria', 'terciaria'])->default('secundaria');
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocion');
    }
};
