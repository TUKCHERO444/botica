<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentacion', function (Blueprint $table) {
            $table->id('id_presentacion');
            $table->string('nombre', 50)->unique();
            $table->string('abreviatura', 10)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentacion');
    }
};
