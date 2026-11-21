<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreaProductoConUnidad;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class VentaConReniecTest extends TestCase
{
    use CreaProductoConUnidad;
    use RefreshDatabase;
    use RegistraPagos;

    private User $user;

    private Producto $producto;

    private UnidadMedida $unidad;

    private ProductoPresentacion $presentacion;

    protected function setUp(): void
    {
        parent::setUp();

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'ventas']);
        Permission::firstOrCreate(['name' => 'productos']);
        Permission::firstOrCreate(['name' => 'caja']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['ventas', 'productos', 'caja']);

        $this->actingAs($this->user);

        $this->unidad = $this->crearUnidadBase();

        Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'monto_final' => 100,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $categoria = Categoria::create(['nombre' => 'Test', 'cantidad' => 0, 'estado' => 1]);
        $marca = Marca::create(['nombre' => 'Test', 'cantidad' => 0, 'estado' => 1]);

        $this->producto = Producto::create([
            'nombre' => 'Producto Test',
            'precio_venta' => 10.50,
            'estado' => 1,
            'inventario' => 50,
            'stock' => 50,
            'stock_minimo' => 5,
            'unidad_base_id' => $this->unidad->id_unidad_medida,
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $this->crearPresentacionBase($this->producto, $this->unidad);
        $this->presentacion = $this->producto->presentaciones()->where('es_base', true)->firstOrFail();
        $this->crearLoteInicial($this->producto, $this->presentacion, 50);
    }

    public function test_crear_venta_con_cliente_data_crea_cliente_y_venta(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post(route('admin.ventas.store'), [
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

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('cliente', [
            'dni' => '46027897',
            'nombre' => 'ROXANA KARINA',
            'apellido_paterno' => 'DELGADO',
            'apellido_materno' => 'HUAMANI',
            'registro_web' => false,
        ]);

        $cliente = Cliente::where('dni', '46027897')->first();
        $this->assertDatabaseHas('venta', [
            'total' => 21.00,
            'cliente_id_cliente' => $cliente->id_cliente,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_crear_venta_con_cliente_id_ignora_cliente_data(): void
    {
        $clienteExistente = Cliente::create([
            'dni' => '11111111',
            'nombre' => 'EXISTENTE',
            'apellido_paterno' => 'USUARIO',
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post(route('admin.ventas.store'), [
            'cliente_id_cliente' => $clienteExistente->id_cliente,
            'cliente_data' => [
                'dni' => '46027897',
                'nombre' => 'ROXANA KARINA',
                'apellido_paterno' => 'DELGADO',
                'apellido_materno' => 'HUAMANI',
            ],
            'productos' => [
                [
                    'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
                    'cantidad' => 1,
                    'precio' => 10.50,
                ],
            ],
            ...$this->pagos(10.50),
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('cliente', ['dni' => '46027897']);
        $this->assertDatabaseHas('venta', [
            'cliente_id_cliente' => $clienteExistente->id_cliente,
        ]);
    }

    public function test_crear_venta_sin_cliente_id_ni_data_falla(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->postJson(route('admin.ventas.store'), [
            'productos' => [
                [
                    'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
                    'cantidad' => 1,
                    'precio' => 10.50,
                ],
            ],
            ...$this->pagos(10.50),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cliente_id_cliente']);
    }

    public function test_crear_venta_sin_caja_abierta_falla(): void
    {
        Caja::where('estado', 'abierta')->update(['estado' => 'cerrada']);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post(route('admin.ventas.store'), [
            'cliente_data' => [
                'dni' => '46027897',
                'nombre' => 'ROXANA KARINA',
                'apellido_paterno' => 'DELGADO',
                'apellido_materno' => 'HUAMANI',
            ],
            'productos' => [
                [
                    'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
                    'cantidad' => 1,
                    'precio' => 10.50,
                ],
            ],
            ...$this->pagos(10.50),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_crear_venta_con_cliente_data_dni_duplicado_se_asocia_a_existente(): void
    {
        $clienteExistente = Cliente::create([
            'dni' => '46027897',
            'nombre' => 'CLIENTE',
            'apellido_paterno' => 'EXISTENTE',
        ]);

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
                    'cantidad' => 1,
                    'precio' => 10.50,
                ],
            ],
            ...$this->pagos(10.50),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cliente_data.dni']);
    }
}
