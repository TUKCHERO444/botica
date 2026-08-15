<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banner', function (Blueprint $table) {
            $table->text('public_id')->nullable()->after('imagen_url');
        });

        Schema::table('producto_imagen', function (Blueprint $table) {
            $table->text('public_id')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('banner', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });

        Schema::table('producto_imagen', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }
};
