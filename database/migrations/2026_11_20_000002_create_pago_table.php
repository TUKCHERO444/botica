<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago', function (Blueprint $table) {
            $table->id('id_pago');
            $table->string('referencia_tipo', 20);
            $table->unsignedBigInteger('referencia_id');
            $table->foreignId('metodo_pago_id')
                ->constrained('metodo_pago', 'id_metodo_pago')
                ->restrictOnDelete();
            $table->decimal('monto', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['referencia_tipo', 'referencia_id'], 'pago_referencia_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago');
    }
};
