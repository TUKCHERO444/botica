<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReniecPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.reniec.token' => 'test-token']);
        config(['services.reniec.url' => 'https://api.decolecta.com/v1/reniec/dni']);
    }

    public function test_publico_puede_consultar_dni_sin_autenticacion(): void
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
                'nombre_completo' => 'DELGADO HUAMANI ROXANA KARINA',
            ],
        ]);
    }

    public function test_publico_no_recibe_id_cliente(): void
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
}
