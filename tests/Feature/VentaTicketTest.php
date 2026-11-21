<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreaProductoConUnidad;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class VentaTicketTest extends TestCase
{
    use CreaProductoConUnidad;
    use RefreshDatabase;
    use RegistraPagos;

    private User $user;

    private Producto $producto;

    private ProductoPresentacion $presentacion;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['ventas', 'productos', 'caja'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['ventas', 'productos', 'caja']);
        $this->actingAs($this->user);

        Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'monto_final' => 100,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $unidad = $this->crearUnidadBase();
        $categoria = Categoria::create(['nombre' => 'Test', 'cantidad' => 0, 'estado' => 1]);
        $marca = Marca::create(['nombre' => 'Test', 'cantidad' => 0, 'estado' => 1]);

        $this->producto = Producto::create([
            'nombre' => 'Paracetamol 500mg',
            'precio_venta' => 10.50,
            'estado' => 1,
            'inventario' => 50,
            'stock' => 50,
            'stock_minimo' => 5,
            'unidad_base_id' => $unidad->id_unidad_medida,
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $this->presentacion = $this->crearPresentacionBase($this->producto, $unidad);
        $this->crearLoteInicial($this->producto, $this->presentacion, 50);
    }

    public function test_ticket_muestra_datos_de_la_venta_y_acciones_de_impresion(): void
    {
        $venta = $this->crearVenta();

        $response = $this->get(route('admin.ventas.ticket', $venta->id_venta));

        $response->assertOk();
        $response->assertSee($venta->correlativo);
        $response->assertSee('JUAN PEREZ GOMEZ');
        $response->assertSee('Paracetamol 500mg');
        $response->assertSee('Imprimir ticket');
        $response->assertSee('Volver al panel');
        $response->assertSee('21.00');
    }

    public function test_ticket_muestra_aviso_cuando_se_abre_despues_de_registrar_la_venta(): void
    {
        $venta = $this->crearVenta();

        $response = $this->get(route('admin.ventas.ticket', [
            'venta' => $venta->id_venta,
            'nueva' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('Venta registrada correctamente');
    }

    public function test_guardar_venta_redirige_a_la_pantalla_del_ticket(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->postJson(route('admin.ventas.store'), [
            'cliente_data' => [
                'dni' => '46027897',
                'nombre' => 'ROXANA KARINA',
                'apellido_paterno' => 'DELGADO',
                'apellido_materno' => 'HUAMANI',
            ],
            'productos' => [
                [
                    'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
                    'cantidad' => 2,
                    'precio' => 10.50,
                ],
            ],
            ...$this->pagos(21),
        ]);

        $venta = Venta::firstOrFail();

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect' => route('admin.ventas.ticket', [
                'venta' => $venta->id_venta,
                'nueva' => 1,
            ]),
        ]);
    }

    public function test_ticket_muestra_cliente_general_y_dni_vacio_para_venta_sin_identificar(): void
    {
        $clienteGenerico = Cliente::create([
            'dni' => '00000001',
            'nombre' => 'SIN IDENTIFICAR',
            'registro_web' => false,
        ]);

        $venta = $this->crearVenta($clienteGenerico);

        $response = $this->get(route('admin.ventas.ticket', $venta->id_venta));

        $response->assertOk();
        $response->assertSee('CLIENTE GENERAL');
        $response->assertSee('Atendido por:');
        $response->assertSee('DNI:');
        $response->assertSee('---');
        $response->assertDontSee('SIN IDENTIFICAR');
    }

    public function test_ticket_detalla_cantidad_precio_unitario_e_importe_por_producto(): void
    {
        $venta = $this->crearVenta();

        $response = $this->get(route('admin.ventas.ticket', $venta->id_venta));

        $response->assertOk();
        $response->assertSee('Cant.');
        $response->assertSee('P. Unit.');
        $response->assertSee('Importe');
        $response->assertSee('10.50');
        $response->assertSee('21.00');
        $response->assertSee('Artículos:');
    }

    private function crearVenta(?Cliente $cliente = null): Venta
    {
        $cliente ??= Cliente::create([
            'dni' => '12345678',
            'nombre' => 'JUAN',
            'apellido_paterno' => 'PEREZ',
            'apellido_materno' => 'GOMEZ',
            'registro_web' => false,
        ]);

        $venta = Venta::create([
            'correlativo' => 'T001-00000001',
            'total' => '21.00',
            'cliente_id_cliente' => $cliente->id_cliente,
            'user_id' => $this->user->id,
        ]);

        $venta->productos()->attach($this->producto->id_producto, [
            'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
            'cantidad' => 2,
            'factor' => 1,
            'cantidad_base' => 2,
            'precio_unitario' => 10.50,
            'sub_total' => 21.00,
        ]);

        return $venta;
    }
}
