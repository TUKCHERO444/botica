<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReniecPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.jsonpe.token' => 'test-token']);
        config(['services.jsonpe.url' => 'https://api.json.pe/api']);
    }

    public function test_publico_puede_verificar_dni_inexistente_sin_consumir_api(): void
    {
        Http::fake();

        $response = $this->postJson('/api/reniec/verificar', [
            'dni' => '46027897',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => false,
        ]);

        $this->assertArrayNotHasKey('cliente', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());
        Http::assertNothingSent();
    }

    public function test_publico_verifica_dni_existente_sin_devolver_datos_ni_consumir_api(): void
    {
        Http::fake();

        Cliente::create([
            'dni' => '46027897',
            'nombre' => 'ROXANA',
            'apellido_paterno' => 'DELGADO',
            'apellido_materno' => 'HUAMANI',
        ]);

        $response = $this->postJson('/api/reniec/verificar', [
            'dni' => '46027897',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => true,
        ]);

        $this->assertArrayNotHasKey('cliente', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());
        Http::assertNothingSent();
    }

    public function test_publico_consultar_dni_existente_no_consume_api_ni_devuelve_datos(): void
    {
        Http::fake();

        Cliente::create([
            'dni' => '46027897',
            'nombre' => 'ROXANA',
            'apellido_paterno' => 'DELGADO',
            'apellido_materno' => 'HUAMANI',
        ]);

        $response = $this->postJson('/api/reniec/consultar', [
            'dni' => '46027897',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => true,
        ]);

        $this->assertArrayNotHasKey('cliente', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());
        Http::assertNothingSent();
    }

    public function test_publico_puede_consultar_dni_sin_autenticacion(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $response = $this->postJson('/api/reniec/consultar', [
            'dni' => '46027897',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'nombre' => 'ROXANA KARINA',
                'apellido_paterno' => 'DELGADO',
                'apellido_materno' => 'HUAMANI',
                'nombre_completo' => 'DELGADO HUAMANI, ROXANA KARINA',
            ],
        ]);
    }

    public function test_publico_no_recibe_id_cliente(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $response = $this->postJson('/api/reniec/consultar', [
            'dni' => '46027897',
        ]);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('id_cliente', $response->json('data'));
        $this->assertArrayNotHasKey('exists', $response->json());
    }

    public function test_dni_invalido_retorna_422(): void
    {
        $response = $this->postJson('/api/reniec/consultar', [
            'dni' => 'abc',
        ]);

        $response->assertStatus(422);
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
