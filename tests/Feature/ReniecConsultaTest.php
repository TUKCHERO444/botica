<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReniecConsultaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'clientes']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('clientes');

        config(['services.reniec.token' => 'test-token']);
        config(['services.reniec.url' => 'https://api.decolecta.com/v1/reniec/dni']);

        $this->actingAs($this->user);
    }

    public function test_admin_puede_consultar_dni_no_existente(): void
    {
        Http::fake([
            'api.decolecta.com/*' => Http::response([
                'first_name' => 'ROXANA KARINA',
                'first_last_name' => 'DELGADO',
                'second_last_name' => 'HUAMANI',
                'full_name' => 'DELGADO HUAMANI ROXANA KARINA',
                'document_number' => '46027897',
            ], 200),
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
                'nombre_completo' => 'DELGADO HUAMANI ROXANA KARINA',
            ],
        ]);
    }

    public function test_admin_consulta_dni_existente_retorna_cliente_local(): void
    {
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
            'api.decolecta.com/*' => Http::response(['error' => 'Invalid request'], 400),
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
            'api.decolecta.com/*' => Http::response(null, 500),
        ]);

        $response = $this->postJson(route('admin.reniec.consultar'), [
            'dni' => '46027897',
        ]);

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
        ]);
    }
}
