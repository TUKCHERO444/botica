<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReniecConsultaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'clientes']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('clientes');

        config(['services.jsonpe.token' => 'test-token']);
        config(['services.jsonpe.url' => 'https://api.json.pe/api']);

        $this->actingAs($this->user);
    }

    public function test_admin_puede_consultar_dni_no_existente(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $response = $this->postJson(route('admin.reniec.consultar'), [
            'dni' => '46027897',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'exists' => false,
            'data' => [
                'dni' => '46027897',
                'nombre' => 'ROXANA KARINA',
                'apellido_paterno' => 'DELGADO',
                'apellido_materno' => 'HUAMANI',
                'nombre_completo' => 'DELGADO HUAMANI, ROXANA KARINA',
            ],
        ]);
    }

    public function test_admin_consulta_dni_existente_retorna_cliente_local_sin_consumir_api(): void
    {
        Http::fake();

        Cliente::create([
            'dni' => '46027897',
            'nombre' => 'ROXANA',
            'apellido_paterno' => 'DELGADO',
            'apellido_materno' => 'HUAMANI',
        ]);

        $response = $this->postJson(route('admin.reniec.consultar'), [
            'dni' => '46027897',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'exists' => true,
        ]);

        $response->assertJsonPath('cliente.dni', '46027897');
        $response->assertJsonPath('cliente.nombre', 'ROXANA');
        $this->assertArrayHasKey('id_cliente', $response->json('cliente'));

        Http::assertNothingSent();
    }

    public function test_verificar_dni_existente_no_consume_api(): void
    {
        Http::fake();

        $cliente = Cliente::create([
            'dni' => '46027897',
            'nombre' => 'ROXANA',
            'apellido_paterno' => 'DELGADO',
            'apellido_materno' => 'HUAMANI',
        ]);

        $response = $this->postJson(route('admin.clientes.verificarDni'), [
            'dni' => '46027897',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => true,
        ]);

        $response->assertJsonPath('cliente.id_cliente', $cliente->id_cliente);
        $response->assertJsonPath('cliente.nombre', 'ROXANA');
        Http::assertNothingSent();
    }

    public function test_verificar_dni_inexistente_no_consume_api(): void
    {
        Http::fake();

        $response = $this->postJson(route('admin.clientes.verificarDni'), [
            'dni' => '12345678',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => false,
        ]);

        Http::assertNothingSent();
    }

    public function test_vendedor_puede_verificar_y_buscar_clientes_sin_permiso_clientes(): void
    {
        Permission::firstOrCreate(['name' => 'ventas']);

        $vendedor = User::factory()->create();
        $vendedor->givePermissionTo('ventas');

        $this->actingAs($vendedor);

        Http::fake();

        Cliente::create([
            'dni' => '46027897',
            'nombre' => 'ROXANA',
            'apellido_paterno' => 'DELGADO',
            'apellido_materno' => 'HUAMANI',
        ]);

        $this->postJson(route('admin.clientes.verificarDni'), ['dni' => '46027897'])
            ->assertOk()
            ->assertJson(['exists' => true]);

        $this->getJson(route('admin.clientes.search', ['q' => '4602']))
            ->assertOk();

        $this->postJson(route('admin.reniec.consultar'), ['dni' => '46027897'])
            ->assertOk()
            ->assertJson(['exists' => true]);

        Http::assertNothingSent();
    }

    public function test_dni_invalido_retorna_error_422(): void
    {
        $response = $this->postJson(route('admin.reniec.consultar'), [
            'dni' => '123',
        ]);

        $response->assertStatus(422);
    }

    public function test_sin_autenticacion_retorna_redirect(): void
    {
        auth()->logout();

        $response = $this->postJson(route('admin.reniec.consultar'), [
            'dni' => '46027897',
        ]);

        $response->assertStatus(401);
    }

    public function test_dni_no_encontrado_api_retorna_422(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response([
                'success' => false,
                'message' => 'DNI no encontrado',
            ], 404),
        ]);

        $response = $this->postJson(route('admin.reniec.consultar'), [
            'dni' => '12345678',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_error_servicio_api_retorna_503(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response(null, 500),
        ]);

        $response = $this->postJson(route('admin.reniec.consultar'), [
            'dni' => '46027897',
        ]);

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
        ]);
    }

    private function respuestaExitosa(): array
    {
        return [
            'success' => true,
            'message' => 'exito',
            'data' => [
                'numero' => '46027897',
                'nombre_completo' => 'DELGADO HUAMANI, ROXANA KARINA',
                'nombres' => 'ROXANA KARINA',
                'apellido_paterno' => 'DELGADO',
                'apellido_materno' => 'HUAMANI',
                'codigo_verificacion' => 7,
                'direccion' => '',
                'direccion_completa' => '',
                'ubigeo_reniec' => '',
                'ubigeo_sunat' => '',
            ],
        ];
    }
}
