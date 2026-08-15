<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->string('email', 255)->unique()->nullable()->after('apellido_materno');
            $table->string('telefono', 9)->nullable()->after('email');
            $table->string('password', 255)->nullable()->after('telefono');
            $table->boolean('registro_web')->default(false)->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('registro_web');
            $table->rememberToken()->after('email_verified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'telefono',
                'password',
                'registro_web',
                'email_verified_at',
                'remember_token',
                'created_at',
                'updated_at',
            ]);
        });
    }
};
