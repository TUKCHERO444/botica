<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreaProductoConUnidad;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class VentaFraccionadaTest extends TestCase
{
    use CreaProductoConUnidad, RefreshDatabase, RegistraPagos;

    private User $user;

    private Producto $producto;

    private ProductoPresentacion $base;

    private ProductoPresentacion $blister;

    private ProductoPresentacion $caja;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['ventas', 'productos', 'caja', 'ingresos'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['ventas', 'productos', 'caja', 'ingresos']);
        $this->actingAs($this->user);

        Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $presentacionBase = Presentacion::create(['nombre' => 'Tableta', 'activo' => true]);
        $presentacionBlister = Presentacion::create(['nombre' => 'Blíster', 'activo' => true]);
        $presentacionCaja = Presentacion::create(['nombre' => 'Caja', 'activo' => true]);

        $unidad = UnidadMedida::create([
            'codigo' => 'TAB',
            'nombre' => 'Tableta',
            'permite_fraccion' => true,
            'presentacion_base_id' => $presentacionBase->id_presentacion,
            'activo' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Medicamentos', 'cantidad' => 0, 'estado' => 1]);
        $marca = Marca::create(['nombre' => 'Genérico', 'cantidad' => 0, 'estado' => 1]);

        $this->producto = Producto::create([
            'nombre' => 'Ibuprofeno 400mg',
            'precio_compra' => 0.20,
            'precio_venta' => 0.80,
            'estado' => 1,
            'inventario' => 250,
            'stock' => 250,
            'stock_minimo' => 20,
            'unidad_base_id' => $unidad->id_unidad_medida,
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $this->base = ProductoPresentacion::create([
            'producto_id_producto' => $this->producto->id_producto,
            'presentacion_id' => $presentacionBase->id_presentacion,
            'factor' => 1,
            'precio_venta' => 0.80,
            'paso' => 0.5,
            'es_base' => true,
            'activo' => true,
            'orden' => 0,
        ]);

        $this->blister = ProductoPresentacion::create([
            'producto_id_producto' => $this->producto->id_producto,
            'presentacion_id' => $presentacionBlister->id_presentacion,
            'factor' => 10,
            'precio_venta' => 6.00,
            'paso' => 1,
            'es_base' => false,
            'activo' => true,
            'orden' => 1,
        ]);

        $this->caja = ProductoPresentacion::create([
            'producto_id_producto' => $this->producto->id_producto,
            'presentacion_id' => $presentacionCaja->id_presentacion,
            'factor' => 100,
            'precio_venta' => 50.00,
            'paso' => 1,
            'es_base' => false,
            'activo' => true,
            'orden' => 2,
        ]);

        $this->cliente = Cliente::create([
            'dni' => '12345678',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Test',
        ]);

        $this->crearLoteInicial($this->producto, $this->base, 250);
    }

    private function vender(array $productos)
    {
        $total = collect($productos)->sum(function ($p) {
            $presentacion = ProductoPresentacion::find($p['producto_presentacion_id']);

            return (float) $presentacion->precio_venta * (float) $p['cantidad'];
        });

        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => $productos,
                ...$this->pagos($total),
            ]);
    }

    public function test_vende_caja_y_tabletas_descontando_stock_base(): void
    {
        $response = $this->vender([
            [
                'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                'cantidad' => 1,
            ],
            [
                'producto_presentacion_id' => $this->base->id_producto_presentacion,
                'cantidad' => 5,
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->producto->refresh();
        $this->assertSame('145.000', $this->producto->stock);

        $venta = Venta::firstOrFail();
        $this->assertSame('54.00', $venta->total);
        $this->assertDatabaseCount('detalle_venta', 2);
        $this->assertDatabaseHas('detalle_venta', [
            'venta_id_venta' => $venta->id_venta,
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
            'cantidad' => 1,
            'cantidad_base' => 100,
            'precio_unitario' => 50,
            'sub_total' => 50,
        ]);

        $this->assertDatabaseHas('kardex_movimientos', [
            'referencia_tipo' => 'Venta',
            'referencia_id' => $venta->id_venta,
            'cantidad' => -100,
            'cantidad_presentacion' => 1,
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
        ]);
        $this->assertDatabaseHas('kardex_movimientos', [
            'referencia_tipo' => 'Venta',
            'referencia_id' => $venta->id_venta,
            'cantidad' => -5,
        ]);

        $this->assertDatabaseHas('caja_movimiento', [
            'referencia_tipo' => 'venta',
            'referencia_id' => $venta->id_venta,
            'monto' => 54,
        ]);

        $this->assertDatabaseHas('caja', ['total_ingresos' => 54]);
    }

    public function test_rechaza_stock_insuficiente(): void
    {
        $response = $this->vender([
            [
                'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                'cantidad' => 3,
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('venta', 0);
        $this->assertSame('250.000', $this->producto->fresh()->stock);
    }

    public function test_permite_media_tableta_si_el_paso_lo_permite(): void
    {
        $response = $this->vender([
            [
                'producto_presentacion_id' => $this->base->id_producto_presentacion,
                'cantidad' => 0.5,
            ],
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame('249.500', $this->producto->fresh()->stock);

        $venta = Venta::firstOrFail();
        $this->assertSame('0.40', $venta->total);
    }

    public function test_rechaza_cantidad_con_mas_de_tres_decimales(): void
    {
        $this->vender([
            [
                'producto_presentacion_id' => $this->base->id_producto_presentacion,
                'cantidad' => 0.0001,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('productos.0.cantidad');

        $this->assertDatabaseCount('venta', 0);
    }

    public function test_no_se_puede_editar_venta_confirmada(): void
    {
        $this->vender([
            [
                'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                'cantidad' => 1,
            ],
        ])->assertOk();

        $venta = Venta::firstOrFail();
        $this->assertSame('150.000', $this->producto->fresh()->stock);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.ventas.update', $venta), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->blister->id_producto_presentacion,
                        'cantidad' => 2,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame('150.000', $this->producto->fresh()->stock);
        $this->assertSame('50.00', $venta->fresh()->total);
        $this->assertDatabaseCount('detalle_venta', 1);
    }

    public function test_no_se_puede_eliminar_venta_confirmada(): void
    {
        $this->vender([
            [
                'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                'cantidad' => 1,
            ],
        ])->assertOk();

        $venta = Venta::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.ventas.destroy', $venta))
            ->assertStatus(422);

        $this->assertDatabaseCount('venta', 1);
        $this->assertDatabaseCount('detalle_venta', 1);
        $this->assertSame('150.000', $this->producto->fresh()->stock);
        $this->assertDatabaseHas('caja', ['total_ingresos' => 50]);
    }

    public function test_ingreso_de_cajas_convierte_a_unidad_base(): void
    {
        $proveedor = Proveedor::create([
            'ruc' => '20100088880',
            'razon_social' => 'Proveedor Test',
            'condicion' => 'Habilitado',
            'estado' => 1,
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.ingresos.store'), [
                'proveedor_id_proveedor' => $proveedor->id_proveedor,
                'fecha' => now()->toDateString(),
                'tipo_documento' => 'factura',
                'numero_documento' => 'F001-000001',
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 2,
                        'lote' => 'LOT-TEST',
                        'fecha_vencimiento' => now()->addYear()->toDateString(),
                        'costo_unitario' => 50,
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $ingreso = Ingreso::firstOrFail();
        $this->assertSame('borrador', $ingreso->estado);
        $this->assertSame('250.000', $this->producto->fresh()->stock);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), ['pagado_desde_caja' => 0])
            ->assertOk();

        $this->producto->refresh();
        $this->assertSame('450.000', $this->producto->stock);
        $this->assertSame('450.000', $this->producto->inventario);

        $this->assertDatabaseHas('detalle_ingreso', [
            'producto_id_producto' => $this->producto->id_producto,
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
            'cantidad' => 2,
            'cantidad_base' => 200,
        ]);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'INGRESO',
            'cantidad' => 200,
            'stock_anterior' => 250,
            'stock_nuevo' => 450,
        ]);
    }

    public function test_editar_borrador_y_recibir_no_duplica_stock(): void
    {
        $proveedor = Proveedor::create([
            'ruc' => '20100088880',
            'razon_social' => 'Proveedor Test',
            'condicion' => 'Habilitado',
            'estado' => 1,
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.store'), [
                'proveedor_id_proveedor' => $proveedor->id_proveedor,
                'fecha' => now()->toDateString(),
                'tipo_documento' => 'factura',
                'numero_documento' => 'F001-000002',
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 2,
                        'lote' => 'LOT-TEST',
                        'fecha_vencimiento' => now()->addYear()->toDateString(),
                        'costo_unitario' => 50,
                    ],
                ],
            ])->assertOk();

        $ingreso = Ingreso::firstOrFail();
        $this->assertSame('250.000', $this->producto->fresh()->stock);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.ingresos.update', $ingreso), [
                'proveedor_id_proveedor' => $proveedor->id_proveedor,
                'fecha' => now()->toDateString(),
                'tipo_documento' => 'factura',
                'numero_documento' => 'F001-000002',
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 1,
                        'lote' => 'LOT-TEST',
                        'fecha_vencimiento' => now()->addYear()->toDateString(),
                        'costo_unitario' => 50,
                    ],
                ],
            ])->assertOk();

        $this->assertSame('250.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('detalle_ingreso', 1);
        $this->assertDatabaseCount('kardex_movimientos', 0);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), ['pagado_desde_caja' => 0])
            ->assertOk();

        $this->producto->refresh();
        $this->assertSame('350.000', $this->producto->stock);
        $this->assertSame('350.000', $this->producto->inventario);
        $this->assertDatabaseCount('detalle_ingreso', 1);
        $this->assertDatabaseHas('detalle_ingreso', [
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
            'cantidad' => 1,
            'cantidad_base' => 100,
        ]);
        $this->assertDatabaseCount('kardex_movimientos', 1);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'INGRESO',
            'cantidad' => 100,
            'stock_anterior' => 250,
            'stock_nuevo' => 350,
        ]);
    }

    public function test_eliminar_borrador_de_ingreso_no_toca_stock(): void
    {
        $proveedor = Proveedor::create([
            'ruc' => '20100088880',
            'razon_social' => 'Proveedor Test',
            'condicion' => 'Habilitado',
            'estado' => 1,
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.store'), [
                'proveedor_id_proveedor' => $proveedor->id_proveedor,
                'fecha' => now()->toDateString(),
                'tipo_documento' => 'factura',
                'numero_documento' => 'F001-000003',
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 2,
                        'lote' => 'LOT-TEST',
                        'fecha_vencimiento' => now()->addYear()->toDateString(),
                        'costo_unitario' => 50,
                    ],
                ],
            ])->assertOk();

        $ingreso = Ingreso::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.ingresos.destroy', $ingreso))
            ->assertOk();

        $this->producto->refresh();
        $this->assertSame('250.000', $this->producto->stock);
        $this->assertSame('250.000', $this->producto->inventario);
        $this->assertDatabaseCount('detalle_ingreso', 0);
        $this->assertDatabaseCount('kardex_movimientos', 0);
    }

    public function test_formulario_de_venta_usa_buscador_de_presentaciones(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ventas.create'));

        $response->assertOk();
        $response->assertSee('Nueva venta');
        $response->assertSee('producto-search', false);
        $response->assertSee('productos[0][producto_presentacion_id]', false);
        $response->assertSee('/admin/ventas/presentaciones/search', false);
    }

    public function test_formulario_de_ingreso_usa_buscador_de_productos(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.create'));

        $response->assertOk();
        $response->assertSee('producto-search', false);
        $response->assertSee('/admin/ingresos/presentaciones/search', false);
        $response->assertSee('producto_presentacion_id', false);
        $response->assertSee('fecha_vencimiento', false);
        $response->assertSee('Lote');
    }

    public function test_formulario_de_edicion_de_venta_ya_no_esta_disponible(): void
    {
        $this->vender([
            [
                'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                'cantidad' => 1,
            ],
        ])->assertOk();

        $venta = Venta::firstOrFail();

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ventas.edit', $venta));

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
