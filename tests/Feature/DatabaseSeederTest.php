<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_seeders_crean_unidades_y_presentaciones_para_los_productos(): void
    {
        $this->seed();

        $this->assertDatabaseCount('presentacion', 11);
        $this->assertDatabaseCount('unidad_medida', 5);
        $this->assertSame(10, Producto::count());
        $this->assertSame(10, ProductoLote::count());
        $this->assertSame(0, ProductoLote::whereNull('correlativo')->count());

        $this->assertSame(0, Producto::doesntHave('presentaciones')->count());

        foreach (Producto::all() as $producto) {
            $this->assertDatabaseHas('producto_presentacion', [
                'producto_id_producto' => $producto->id_producto,
                'es_base' => true,
                'factor' => 1,
            ]);
            $this->assertNotNull($producto->unidad_base_id);
        }

        $paracetamol = Producto::where('nombre', 'Paracetamol 500mg')->firstOrFail();
        $this->assertSame(3, ProductoPresentacion::where('producto_id_producto', $paracetamol->id_producto)->count());

        $unidad = UnidadMedida::where('codigo', 'TAB')->firstOrFail();
        $this->assertTrue($unidad->permite_fraccion);
        $this->assertNotNull($unidad->presentacion_base_id);
    }
}
