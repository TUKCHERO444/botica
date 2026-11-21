<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\KardexMovimiento;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\ProductoPresentacion;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Venta;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class LotesFefoTest extends TestCase
{
    use RefreshDatabase, RegistraPagos;

    private User $user;

    private Producto $producto;

    private ProductoPresentacion $presentacion;

    private Cliente $cliente;

    private InventarioService $inventario;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['ventas', 'productos', 'caja', 'ingresos', 'lotes'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['ventas', 'productos', 'caja', 'ingresos', 'lotes']);
        $this->actingAs($this->user);

        Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $presentacion = Presentacion::create(['nombre' => 'Tableta', 'activo' => true]);
        $unidad = UnidadMedida::create([
            'codigo' => 'TAB',
            'nombre' => 'Tableta',
            'permite_fraccion' => true,
            'presentacion_base_id' => $presentacion->id_presentacion,
            'activo' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Medicamentos', 'cantidad' => 0, 'estado' => 1]);
        $marca = Marca::create(['nombre' => 'Genérico', 'cantidad' => 0, 'estado' => 1]);

        $this->producto = Producto::create([
            'nombre' => 'Ibuprofeno 400mg',
            'precio_compra' => 0.30,
            'precio_venta' => 1.00,
            'estado' => 1,
            'inventario' => 0,
            'stock' => 0,
            'stock_minimo' => 5,
            'unidad_base_id' => $unidad->id_unidad_medida,
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $this->presentacion = ProductoPresentacion::create([
            'producto_id_producto' => $this->producto->id_producto,
            'presentacion_id' => $presentacion->id_presentacion,
            'factor' => 1,
            'precio_venta' => 1.00,
            'paso' => 1,
            'es_base' => true,
            'activo' => true,
        ]);

        $this->cliente = Cliente::create([
            'dni' => '12345678',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Lotes',
        ]);

        $this->inventario = app(InventarioService::class);
    }

    private function crearLote(string $codigo, int $cantidad, string $vencimiento): void
    {
        $this->inventario->ingresar(
            $this->presentacion,
            $cantidad,
            $codigo,
            $vencimiento,
            0.30,
            'Ajuste',
            0,
            'SETUP',
            'Lote de prueba'
        );
    }

    private function vender(int $cantidad)
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
                        'cantidad' => $cantidad,
                    ],
                ],
                ...$this->pagos((float) $this->presentacion->precio_venta * $cantidad),
            ]);
    }

    public function test_fefo_consume_primero_el_lote_que_vence_antes(): void
    {
        $this->crearLote('A', 10, now()->addDays(10)->toDateString());
        $this->crearLote('B', 10, now()->addDays(60)->toDateString());

        $this->assertNotNull(ProductoLote::where('codigo_lote', 'A')->value('correlativo'));

        $this->vender(3)->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'A', 'cantidad_disponible_base' => 7]);
        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'B', 'cantidad_disponible_base' => 10]);
        $this->assertDatabaseHas('lote_consumo', ['cantidad_base' => 3]);
        $this->assertDatabaseHas('kardex_movimientos', ['lote_codigo' => 'A', 'cantidad' => -3]);
        $this->assertSame('17.000', $this->producto->fresh()->stock);
    }

    public function test_fefo_puede_consumir_varias_capas_en_una_venta(): void
    {
        $this->crearLote('A', 10, now()->addDays(10)->toDateString());
        $this->crearLote('B', 10, now()->addDays(60)->toDateString());

        $this->vender(15)->assertOk();

        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'A', 'cantidad_disponible_base' => 0]);
        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'B', 'cantidad_disponible_base' => 5]);
        $this->assertDatabaseCount('lote_consumo', 2);
        $this->assertSame(2, KardexMovimiento::where('tipo_movimiento', 'VENTA')->count());
    }

    public function test_no_vende_lotes_vencidos(): void
    {
        $lote = ProductoLote::create([
            'producto_id' => $this->producto->id_producto,
            'codigo_lote' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDay()->toDateString(),
            'estado' => 'disponible',
            'cantidad_inicial_base' => 10,
            'cantidad_disponible_base' => 10,
        ]);

        $lote->capas()->create([
            'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
            'cantidad_presentacion' => 10,
            'factor' => 1,
            'cantidad_base' => 10,
            'cantidad_disponible_base' => 10,
            'costo_unitario_base' => 0.30,
        ]);

        $this->producto->update(['stock' => 10, 'inventario' => 10]);

        $this->vender(2)
            ->assertStatus(422)
            ->assertJsonValidationErrors('productos.0.cantidad');
        $this->assertDatabaseCount('venta', 0);
        $this->assertSame('10.000', $this->producto->fresh()->stock);
    }

    public function test_valida_la_venta_contra_el_stock_vendible_y_no_contra_el_fisico(): void
    {
        $this->crearLote('VIGENTE', 3, now()->addDays(10)->toDateString());

        $vencido = ProductoLote::create([
            'producto_id' => $this->producto->id_producto,
            'codigo_lote' => 'VENCIDO',
            'fecha_vencimiento' => now()->subDay()->toDateString(),
            'estado' => 'disponible',
            'cantidad_inicial_base' => 10,
            'cantidad_disponible_base' => 10,
        ]);

        $vencido->capas()->create([
            'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
            'cantidad_presentacion' => 10,
            'factor' => 1,
            'cantidad_base' => 10,
            'cantidad_disponible_base' => 10,
            'costo_unitario_base' => 0.30,
        ]);

        $this->producto->update(['stock' => 13, 'inventario' => 13]);

        // Hay 13 unidades físicas, pero solo 3 vendibles: la venta se rechaza.
        $this->vender(5)
            ->assertStatus(422)
            ->assertJsonValidationErrors('productos.0.cantidad');

        $this->assertDatabaseCount('venta', 0);
    }

    public function test_revertir_venta_restaura_lotes_exactos(): void
    {
        $this->crearLote('A', 10, now()->addDays(10)->toDateString());
        $this->crearLote('B', 10, now()->addDays(60)->toDateString());

        $this->vender(15)->assertOk();
        $venta = Venta::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [
                'motivo_anulacion' => 'Prueba de reversión FEFO',
            ])
            ->assertOk();

        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'A', 'cantidad_disponible_base' => 10]);
        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'B', 'cantidad_disponible_base' => 10]);
        $this->assertDatabaseCount('lote_consumo', 0);
        $this->assertSame('20.000', $this->producto->fresh()->stock);
    }

    public function test_editar_ingreso_consumido_se_bloquea(): void
    {
        $ingreso = Ingreso::create([
            'correlativo' => 'ING-TEST',
            'estado' => 'recibida',
            'tipo_documento' => 'factura',
            'numero_documento' => 'F-ING-1',
            'proveedor_id_proveedor' => Proveedor::create([
                'ruc' => '20100088880',
                'razon_social' => 'Proveedor',
                'condicion' => 'Habilitado',
                'estado' => 1,
            ])->id_proveedor,
            'fecha' => now()->toDateString(),
            'user_id' => $this->user->id,
        ]);

        // Crear un ingreso real con lote y consumir parte.
        $detalleId = DB::table('detalle_ingreso')->insertGetId([
            'ingreso_id_ingreso' => $ingreso->id_ingreso,
            'producto_id_producto' => $this->producto->id_producto,
            'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
            'cantidad' => 5,
            'factor' => 1,
            'cantidad_base' => 5,
        ]);

        $this->inventario->ingresar(
            $this->presentacion,
            5,
            'ING-LOTE',
            now()->addDays(30)->toDateString(),
            0.30,
            'Ingreso',
            $ingreso->id_ingreso,
            $ingreso->correlativo,
            'Ingreso',
            $detalleId
        );

        $this->vender(2)->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.ingresos.update', $ingreso), [
                'proveedor_id_proveedor' => $ingreso->proveedor_id_proveedor,
                'fecha' => now()->toDateString(),
                'tipo_documento' => 'factura',
                'numero_documento' => 'F-ING-1',
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
                        'cantidad' => 5,
                        'lote' => 'ING-LOTE',
                        'fecha_vencimiento' => now()->addDays(30)->toDateString(),
                        'costo_unitario' => 0.30,
                    ],
                ],
            ])
            ->assertStatus(422);

        $this->assertSame('3.000', $this->producto->fresh()->stock);
    }

    public function test_baja_de_lote_ajusta_stock_y_kardex(): void
    {
        $this->crearLote('A', 10, now()->addDays(10)->toDateString());

        $lote = ProductoLote::where('codigo_lote', 'A')->firstOrFail();
        $this->inventario->darDeBaja($lote, 'Vencimiento');

        $this->assertSame('0.000', $this->producto->fresh()->stock);
        $this->assertDatabaseHas('producto_lote', ['id' => $lote->id, 'estado' => 'baja', 'cantidad_disponible_base' => 0]);
        $this->assertDatabaseHas('kardex_movimientos', ['tipo_movimiento' => 'BAJA', 'lote_codigo' => 'A']);
    }

    public function test_vista_de_lotes_y_baja_desde_panel(): void
    {
        $this->crearLote('A', 10, now()->addDays(10)->toDateString());

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.lotes.index'));

        $response->assertOk();
        $response->assertSee('A');
        $response->assertSee('Lotes y vencimientos');

        $lote = ProductoLote::where('codigo_lote', 'A')->firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.lotes.baja', $lote))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('0.000', $this->producto->fresh()->stock);
        $this->assertDatabaseHas('producto_lote', ['id' => $lote->id, 'estado' => 'baja']);
    }
}
