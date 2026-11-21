<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SunatConsultaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'proveedores']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('proveedores');

        config(['services.jsonpe.token' => 'test-token']);
        config(['services.jsonpe.url' => 'https://api.json.pe/api']);

        $this->actingAs($this->user);
    }

    public function test_admin_puede_consultar_ruc_no_existente(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $response = $this->postJson(route('admin.sunat.consultar'), [
            'ruc' => '20552103816',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'exists' => false,
            'data' => [
                'ruc' => '20552103816',
                'razon_social' => 'AGROLIGHT PERU S.A.C.',
                'condicion' => 'HABIDO',
                'estado' => 0,
                'direccion' => 'PJ. JORGE BASADRE NRO. 158 URB. POP LA UNIVERSAL 2DA ET.',
                'ubigeo' => '150137',
                'distrito' => 'SANTA ANITA',
                'provincia' => 'LIMA',
                'departamento' => 'LIMA',
            ],
        ]);
    }

    public function test_admin_consulta_ruc_existente_sin_consumir_api(): void
    {
        Http::fake();

        Proveedor::create([
            'ruc' => '20552103816',
            'razon_social' => 'AGROLIGHT PERU S.A.C.',
            'condicion' => 'HABIDO',
            'estado' => 1,
            'direccion' => 'AV. SIEMPRE VIVA 123',
            'ubigeo' => '150137',
            'distrito' => 'SANTA ANITA',
            'provincia' => 'LIMA',
            'departamento' => 'LIMA',
        ]);

        $response = $this->postJson(route('admin.sunat.consultar'), [
            'ruc' => '20552103816',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'exists' => true,
        ]);

        $response->assertJsonPath('proveedor.ruc', '20552103816');
        $response->assertJsonPath('proveedor.razon_social', 'AGROLIGHT PERU S.A.C.');

        Http::assertNothingSent();
    }

    public function test_verificar_ruc_existente_no_consume_api(): void
    {
        Http::fake();

        $proveedor = Proveedor::create([
            'ruc' => '20552103816',
            'razon_social' => 'AGROLIGHT PERU S.A.C.',
            'condicion' => 'HABIDO',
            'estado' => 1,
        ]);

        $response = $this->postJson(route('admin.proveedores.verificarRuc'), [
            'ruc' => '20552103816',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => true,
        ]);

        $response->assertJsonPath('proveedor.id_proveedor', $proveedor->id_proveedor);
        $response->assertJsonPath('proveedor.razon_social', 'AGROLIGHT PERU S.A.C.');
        Http::assertNothingSent();
    }

    public function test_verificar_ruc_inexistente_no_consume_api(): void
    {
        Http::fake();

        $response = $this->postJson(route('admin.proveedores.verificarRuc'), [
            'ruc' => '20123456789',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'exists' => false,
        ]);

        Http::assertNothingSent();
    }

    public function test_ruc_invalido_retorna_error_422(): void
    {
        $response = $this->postJson(route('admin.sunat.consultar'), [
            'ruc' => '123',
        ]);

        $response->assertStatus(422);
    }

    public function test_ruc_no_encontrado_api_retorna_422(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response([
                'success' => false,
                'message' => 'RUC no encontrado',
            ], 404),
        ]);

        $response = $this->postJson(route('admin.sunat.consultar'), [
            'ruc' => '20123456789',
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

        $response = $this->postJson(route('admin.sunat.consultar'), [
            'ruc' => '20552103816',
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
                'es_agente_de_retencion' => 'NO',
                'es_agente_de_percepcion' => 'NO',
                'es_agente_de_percepcion_combustible' => 'NO',
                'es_buen_contribuyente' => 'SI',
            ],
        ];
    }
}
