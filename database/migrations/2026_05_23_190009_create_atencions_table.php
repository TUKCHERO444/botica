<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atencion', function (Blueprint $table) {
            $table->integer('id_atencion')->autoIncrement();
            $table->string('observacio', 50)->nullable();
            $table->decimal('total', 10, 2);
            $table->integer('cliente_id_cliente');
            $table->foreignId('user_id')->constrained('users');

            $table->foreign('cliente_id_cliente', 'atencion_cliente_FK')
                ->references('id_cliente')->on('cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atencion');
    }
};
