<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja', function (Blueprint $table) {
            $table->integer('id_caja')->autoIncrement();
            $table->decimal('monto_inicial', 10, 2)->default(0);
            $table->decimal('total_ingresos', 10, 2)->default(0);
            $table->decimal('total_egresos', 10, 2)->default(0);
            $table->decimal('monto_final', 10, 2)->nullable();
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            $table->foreignId('apertura_user_id')->constrained('users');
            $table->foreignId('cierre_user_id')->nullable()->constrained('users');
            $table->dateTime('apertura_at');
            $table->dateTime('cierre_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja');
    }
};
