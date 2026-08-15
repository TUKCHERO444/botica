<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id('id_auditoria');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('modulo', 50);
            $table->string('accion', 30);
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->text('descripcion');
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('modulo');
            $table->index('accion');
            $table->index(['modulo', 'accion']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
