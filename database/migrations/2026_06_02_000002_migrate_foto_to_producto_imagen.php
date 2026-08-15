<?php

use App\Models\Producto;
use App\Models\ProductoImagen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Producto::whereNotNull('foto')->chunk(100, function ($productos) {
            foreach ($productos as $producto) {
                ProductoImagen::create([
                    'producto_id_producto' => $producto->id_producto,
                    'url' => $producto->foto,
                    'orden' => 0,
                ]);
            }
        });

        Schema::table('producto', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }

    public function down(): void
    {
        Schema::table('producto', function (Blueprint $table) {
            $table->text('foto')->nullable()->after('marca_id_marca');
        });

        ProductoImagen::where('orden', 0)->chunk(100, function ($imagenes) {
            foreach ($imagenes as $img) {
                Producto::where('id_producto', $img->producto_id_producto)
                    ->update(['foto' => $img->url]);
            }
        });
    }
};
