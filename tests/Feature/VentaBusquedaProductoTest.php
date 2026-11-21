<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreaProductoConUnidad;
use Tests\TestCase;

class VentaBusquedaProductoTest extends TestCase
{
    use CreaProductoConUnidad;
    use RefreshDatabase;

    private Producto $producto;

    private ProductoPresentacion $presentacion;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'ventas']);

        $user = User::factory()->create();
        $user->givePermissionTo('ventas');
        $this->actingAs($user);

        $unidad = $this->crearUnidadBase();
        $categoria = Categoria::create(['nombre' => 'Medicamentos', 'cantidad' => 0, 'estado' => 1]);
        $marca = Marca::create(['nombre' => 'Genérico', 'cantidad' => 0, 'estado' => 1]);

        $this->producto = Producto::create([
            'nombre' => 'Paracetamol 500mg',
            'precio_venta' => 10.50,
            'estado' => 1,
            'inventario' => 20,
            'stock' => 20,
            'stock_minimo' => 5,
            'unidad_base_id' => $unidad->id_unidad_medida,
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $this->presentacion = $this->crearPresentacionBase($this->producto, $unidad);
        $this->presentacion->update(['codigo_barras' => '7751234567890']);
    }

    public function test_busca_presentaciones_por_nombre_de_producto(): void
    {
        $response = $this->getJson(route('admin.ventas.presentaciones.search', ['q' => 'paracet']));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $this->presentacion->id_producto_presentacion,
                'producto' => 'Paracetamol 500mg',
                'precio' => 10.5,
                'stock_formateado' => '20 UND',
            ]);
    }

    public function test_busca_presentaciones_por_codigo_de_barras(): void
    {
        $response = $this->getJson(route('admin.ventas.presentaciones.search', ['q' => '775123']));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.codigo_barras', '7751234567890');
    }

    public function test_no_busca_con_menos_de_dos_caracteres(): void
    {
        $this->getJson(route('admin.ventas.presentaciones.search', ['q' => 'p']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_omite_productos_sin_stock_por_defecto(): void
    {
        $this->producto->update(['stock' => 0]);

        $this->getJson(route('admin.ventas.presentaciones.search', ['q' => 'paracet']))
            ->assertOk()
            ->assertExactJson([]);

        $this->getJson(route('admin.ventas.presentaciones.search', [
            'q' => 'paracet',
            'solo_stock' => 0,
        ]))
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_expone_stock_vendible_y_alertas_de_minimo_y_vencimiento(): void
    {
        $this->crearLoteInicial(
            $this->producto,
            $this->presentacion,
            3,
            'LOT-BAJO',
            now()->addDays(10)->toDateString()
        );

        $this->getJson(route('admin.ventas.presentaciones.search', ['q' => 'paracet']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'stock_vendible_formateado' => '3 UND',
                'alerta_stock' => 'Bajo mínimo',
                'alerta_vencimiento' => 'Vence en 10 días',
            ]);
    }

    public function test_omite_productos_cuyo_stock_solo_esta_vencido(): void
    {
        $this->crearLoteInicial(
            $this->producto,
            $this->presentacion,
            20,
            'LOT-VENCIDO',
            now()->subDay()->toDateString()
        );

        $this->getJson(route('admin.ventas.presentaciones.search', ['q' => 'paracet']))
            ->assertOk()
            ->assertExactJson([]);

        $this->getJson(route('admin.ventas.presentaciones.search', [
            'q' => 'paracet',
            'solo_stock' => 0,
        ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.alerta_stock', 'Sin stock vendible')
            ->assertJsonPath('0.stock_vendible_formateado', '0 UND');
    }
}
