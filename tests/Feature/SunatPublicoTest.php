<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SunatPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.jsonpe.token' => 'test-token']);
        config(['services.jsonpe.url' => 'https://api.json.pe/api']);
    }

    public function test_publico_puede_verificar_ruc_inexistente_sin_consumir_api(): void
    {
        Http::fake();

        $response = $this->postJson('/api/sunat/verificar', [
            'ruc' => '20552103816',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => false,
        ]);

        $this->assertArrayNotHasKey('proveedor', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());
        Http::assertNothingSent();
    }

    public function test_publico_verifica_ruc_existente_sin_devolver_datos_ni_consumir_api(): void
    {
        Http::fake();

        Proveedor::create([
            'ruc' => '20552103816',
            'razon_social' => 'AGROLIGHT PERU S.A.C.',
            'condicion' => 'HABIDO',
            'estado' => 1,
        ]);

        $response = $this->postJson('/api/sunat/verificar', [
            'ruc' => '20552103816',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => true,
        ]);

        $this->assertArrayNotHasKey('proveedor', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());
        Http::assertNothingSent();
    }

    public function test_publico_consultar_ruc_existente_no_consume_api_ni_devuelve_datos(): void
    {
        Http::fake();

        Proveedor::create([
            'ruc' => '20552103816',
            'razon_social' => 'AGROLIGHT PERU S.A.C.',
            'condicion' => 'HABIDO',
            'estado' => 1,
        ]);

        $response = $this->postJson('/api/sunat/consultar', [
            'ruc' => '20552103816',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => true,
        ]);

        $this->assertArrayNotHasKey('proveedor', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());
        Http::assertNothingSent();
    }

    public function test_publico_puede_consultar_ruc_sin_autenticacion(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $response = $this->postJson('/api/sunat/consultar', [
            'ruc' => '20552103816',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'ruc' => '20552103816',
                'razon_social' => 'AGROLIGHT PERU S.A.C.',
                'condicion' => 'HABIDO',
                'estado' => 0,
            ],
        ]);
    }

    public function test_ruc_invalido_retorna_422(): void
    {
        $response = $this->postJson('/api/sunat/consultar', [
            'ruc' => 'abc',
        ]);

        $response->assertStatus(422);
    }

    private function respuestaExitosa(): array
    {
        return [
            'success' => true,
            'message' => 'exito',
            'data' => [
                'ruc' => '20552103816',
                'nombre_o_razon_social' => 'AGROLIGHT PERU S.A.C.',
                'estado' => 'SUSPENSION TEMPORAL',
                'condicion' => 'HABIDO',
                'departamento' => 'LIMA',
                'provincia' => 'LIMA',
                'distrito' => 'SANTA ANITA',
                'direccion' => 'PJ. JORGE BASADRE NRO. 158 URB. POP LA UNIVERSAL 2DA ET.',
                'direccion_completa' => 'PJ. JORGE BASADRE NRO. 158 URB. POP LA UNIVERSAL 2DA ET., LIMA - LIMA - SANTA ANITA',
                'ubigeo_sunat' => '150137',
                'ubigeo' => ['15', '1501', '150137'],
            ],
        ];
    }
}
