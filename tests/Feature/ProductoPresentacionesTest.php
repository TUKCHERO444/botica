<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductoPresentacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Categoria $categoria;

    private Marca $marca;

    private UnidadMedida $unidad;

    private Presentacion $presentacionBase;

    private Presentacion $caja;

    private Presentacion $blister;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'productos']);
        Permission::firstOrCreate(['name' => 'unidades-medida']);
        Permission::firstOrCreate(['name' => 'presentaciones']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['productos', 'unidades-medida', 'presentaciones']);
        $this->actingAs($this->user);

        $this->presentacionBase = Presentacion::create(['nombre' => 'Tableta', 'activo' => true]);
        $this->caja = Presentacion::create(['nombre' => 'Caja', 'activo' => true]);
        $this->blister = Presentacion::create(['nombre' => 'Blíster', 'activo' => true]);

        $this->unidad = UnidadMedida::create([
            'codigo' => 'TAB',
            'nombre' => 'Tableta',
            'permite_fraccion' => true,
            'presentacion_base_id' => $this->presentacionBase->id_presentacion,
            'activo' => true,
        ]);

        $this->categoria = Categoria::create(['nombre' => 'Medicamentos', 'cantidad' => 0, 'estado' => 1]);
        $this->marca = Marca::create(['nombre' => 'Genérico', 'cantidad' => 0, 'estado' => 1]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Ibuprofeno 400mg',
            'descripcion' => 'Antiinflamatorio',
            'precio_compra' => 0.20,
            'precio_venta' => 0.80,
            'estado' => 1,
            'stock' => 250,
            'stock_minimo' => 20,
            'unidad_base_id' => $this->unidad->id_unidad_medida,
            'categoria_id_categoria' => $this->categoria->id_categoria,
            'marca_id_marca' => $this->marca->id_marca,
            'presentaciones' => [
                [
                    'presentacion_id' => $this->blister->id_presentacion,
                    'factor' => 10,
                    'precio_venta' => 6.00,
                    'paso' => 1,
                    'activo' => 1,
                ],
                [
                    'presentacion_id' => $this->caja->id_presentacion,
                    'factor' => 100,
                    'precio_venta' => 50.00,
                    'paso' => 1,
                    'activo' => 1,
                ],
            ],
        ], $overrides);
    }

    public function test_crea_producto_con_presentacion_base_y_adicionales(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.productos.store'), $this->payload());

        $response->assertOk()->assertJson(['success' => true]);

        $producto = Producto::where('nombre', 'Ibuprofeno 400mg')->firstOrFail();

        $this->assertSame('0.000', $producto->stock);
        $this->assertSame('0.000', $producto->inventario);
        $this->assertSame($this->unidad->id_unidad_medida, $producto->unidad_base_id);

        $base = $producto->presentaciones()->where('es_base', true)->firstOrFail();
        $this->assertSame($this->presentacionBase->id_presentacion, $base->presentacion_id);
        $this->assertSame('1.000', $base->factor);
        $this->assertSame('1.000', $base->paso);
        $this->assertSame('0.8000', $base->precio_venta);

        $this->assertDatabaseMissing('producto_lote', [
            'producto_id' => $producto->id_producto,
            'codigo_lote' => 'SIN-LOTE',
        ]);

        $this->assertCount(2, $producto->presentaciones()->where('es_base', false)->get());
        $this->assertDatabaseHas('producto_presentacion', [
            'producto_id_producto' => $producto->id_producto,
            'presentacion_id' => $this->caja->id_presentacion,
            'factor' => 100,
            'precio_venta' => 50,
        ]);
    }

    public function test_actualiza_precio_base_y_desactiva_presentaciones_quitadas(): void
    {
        $producto = Producto::create([
            'nombre' => 'Ibuprofeno 400mg',
            'precio_venta' => 0.80,
            'estado' => 1,
            'inventario' => 250,
            'stock' => 250,
            'stock_minimo' => 20,
            'unidad_base_id' => $this->unidad->id_unidad_medida,
            'categoria_id_categoria' => $this->categoria->id_categoria,
            'marca_id_marca' => $this->marca->id_marca,
        ]);

        ProductoPresentacion::create([
            'producto_id_producto' => $producto->id_producto,
            'presentacion_id' => $this->presentacionBase->id_presentacion,
            'factor' => 1,
            'precio_venta' => 0.80,
            'paso' => 1,
            'es_base' => true,
            'activo' => true,
        ]);

        ProductoPresentacion::create([
            'producto_id_producto' => $producto->id_producto,
            'presentacion_id' => $this->caja->id_presentacion,
            'factor' => 100,
            'precio_venta' => 50,
            'paso' => 1,
            'es_base' => false,
            'activo' => true,
        ]);

        $payload = $this->payload([
            'nombre' => 'Ibuprofeno 400mg',
            'precio_venta' => 0.90,
            'presentaciones' => [
                [
                    'presentacion_id' => $this->blister->id_presentacion,
                    'factor' => 10,
                    'precio_venta' => 7.00,
                    'paso' => 1,
                    'activo' => 1,
                ],
            ],
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.productos.update', $producto), $payload);

        $response->assertOk()->assertJson(['success' => true]);

        $producto->refresh();
        $this->assertSame('0.9000', $producto->precio_venta);

        $base = $producto->presentaciones()->where('es_base', true)->firstOrFail();
        $this->assertSame('0.9000', $base->precio_venta);

        $caja = $producto->presentaciones()->where('presentacion_id', $this->caja->id_presentacion)->firstOrFail();
        $this->assertFalse($caja->activo);

        $blister = $producto->presentaciones()->where('presentacion_id', $this->blister->id_presentacion)->firstOrFail();
        $this->assertTrue($blister->activo);
        $this->assertSame('7.0000', $blister->precio_venta);
    }

    public function test_no_permite_repetir_la_presentacion_base(): void
    {
        $payload = $this->payload([
            'presentaciones' => [
                [
                    'presentacion_id' => $this->presentacionBase->id_presentacion,
                    'factor' => 1,
                    'precio_venta' => 0.80,
                    'paso' => 1,
                    'activo' => 1,
                ],
            ],
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.productos.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('presentaciones.0.presentacion_id');
    }

    public function test_valida_factor_y_paso_de_presentaciones(): void
    {
        $payload = $this->payload([
            'presentaciones' => [
                [
                    'presentacion_id' => $this->caja->id_presentacion,
                    'factor' => 0,
                    'precio_venta' => 50,
                    'paso' => 10,
                    'activo' => 1,
                ],
            ],
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.productos.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['presentaciones.0.factor']);
    }

    public function test_requiere_unidad_base(): void
    {
        $payload = $this->payload();
        unset($payload['unidad_base_id']);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.productos.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('unidad_base_id');
    }

    public function test_formulario_de_producto_muestra_unidad_y_presentaciones(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.productos.create'));

        $response->assertOk();
        $response->assertSee('unidad_base_id', false);
        $response->assertSee('Stock mínimo');
        $response->assertSee('presentacion-template', false);
        $response->assertSee('Caja');
    }

    public function test_formulario_de_producto_no_muestra_inventario_legacy(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.productos.create'));

        $response->assertOk();
        $response->assertDontSee('name="inventario"', false);
        $response->assertDontSee('name="stock"', false);
    }

    public function test_rechaza_precios_con_mas_de_dos_decimales(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.productos.store'), $this->payload([
                'precio_venta' => 0.999,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio_venta');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.productos.store'), $this->payload([
                'precio_compra' => 0.209,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio_compra');
    }

    public function test_formulario_de_edicion_formatea_decimales_sin_ceros_sobrantes(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.productos.store'), $this->payload())
            ->assertOk();

        $producto = Producto::where('nombre', 'Ibuprofeno 400mg')->firstOrFail();

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.productos.edit', $producto));

        $response->assertOk();
        $response->assertSee('name="precio_compra" value="0.20"', false);
        $response->assertSee('name="precio_venta" value="0.80"', false);
        $response->assertSee('name="stock" value="0"', false);
        $response->assertSee('name="stock_minimo" value="20"', false);
        $response->assertSee('name="presentaciones[0][factor]" value="10"', false);
        $response->assertSee('name="presentaciones[0][precio_venta]" value="6.00"', false);
        $response->assertSee('name="presentaciones[0][paso]" value="1"', false);

        $response->assertDontSee('value="0.8000"', false);
        $response->assertDontSee('value="20.000"', false);
        $response->assertDontSee('value="10.000"', false);
        $response->assertDontSee('value="6.0000"', false);
    }

    public function test_crear_producto_sin_paso_usa_uno(): void
    {
        $payload = $this->payload();
        unset($payload['presentaciones'][0]['paso']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.productos.store'), $payload)
            ->assertOk();

        $producto = Producto::where('nombre', 'Ibuprofeno 400mg')->firstOrFail();
        $blister = $producto->presentaciones()
            ->where('presentacion_id', $this->blister->id_presentacion)
            ->firstOrFail();

        $this->assertSame('1.000', $blister->paso);
    }

    public function test_permite_paso_fraccionado_en_presentacion_adicional(): void
    {
        $payload = $this->payload();
        $payload['presentaciones'][0]['paso'] = 0.5;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.productos.store'), $payload)
            ->assertOk();

        $producto = Producto::where('nombre', 'Ibuprofeno 400mg')->firstOrFail();
        $blister = $producto->presentaciones()
            ->where('presentacion_id', $this->blister->id_presentacion)
            ->firstOrFail();

        $this->assertSame('0.500', $blister->paso);
    }

    public function test_permite_editar_paso_de_la_presentacion_base(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.productos.store'), $this->payload(['presentacion_base_paso' => 0.5]))
            ->assertOk();

        $producto = Producto::where('nombre', 'Ibuprofeno 400mg')->firstOrFail();
        $base = $producto->presentaciones()->where('es_base', true)->firstOrFail();
        $this->assertSame('0.500', $base->paso);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.productos.update', $producto), $this->payload(['presentacion_base_paso' => 0.25]))
            ->assertOk();

        $this->assertSame('0.250', $base->fresh()->paso);
    }

    public function test_paso_base_mayor_a_uno_se_rechaza(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.productos.store'), $this->payload(['presentacion_base_paso' => 2]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('presentacion_base_paso');
    }

    public function test_formulario_muestra_configuracion_avanzada_del_paso(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.productos.store'), $this->payload(['presentacion_base_paso' => 0.5]))
            ->assertOk();

        $producto = Producto::where('nombre', 'Ibuprofeno 400mg')->firstOrFail();

        $create = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.productos.create'));
        $create->assertOk()
            ->assertSee('base-paso-avanzado', false)
            ->assertSee('Configuración avanzada');

        $edit = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.productos.edit', $producto));
        $edit->assertOk()
            ->assertSee('name="presentacion_base_paso"', false)
            ->assertSee('value="0.5"', false)
            ->assertSee('paso-avanzado', false);
    }

    public function test_formulario_de_edicion_muestra_presentaciones_del_producto(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.productos.store'), $this->payload())
            ->assertOk();

        $producto = Producto::where('nombre', 'Ibuprofeno 400mg')->firstOrFail();

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.productos.edit', $producto));

        $response->assertOk();
        $response->assertSee('Caja');
        $response->assertSee('Blíster');
        $response->assertSee('presentacion-template', false);
    }
}
