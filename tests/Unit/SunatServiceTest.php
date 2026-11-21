<?php

namespace Tests\Unit;

use App\Exceptions\Sunat\SunatNotFoundException;
use App\Exceptions\Sunat\SunatServiceException;
use App\Services\SunatService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SunatServiceTest extends TestCase
{
    private SunatService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.jsonpe.token' => 'test-token']);
        config(['services.jsonpe.url' => 'https://api.json.pe/api']);
        config(['services.jsonpe.timeout' => 5]);
        config(['services.jsonpe.cache_minutos' => 60]);

        $this->service = app(SunatService::class);
    }

    public function test_consulta_exitosa_retorna_datos_mapeados(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $result = $this->service->consultar('20552103816');

        $this->assertSame('20552103816', $result['ruc']);
        $this->assertSame('AGROLIGHT PERU S.A.C.', $result['razon_social']);
        $this->assertSame('HABIDO', $result['condicion']);
        $this->assertSame(0, $result['estado']);
        $this->assertSame('PJ. JORGE BASADRE NRO. 158 URB. POP LA UNIVERSAL 2DA ET.', $result['direccion']);
        $this->assertSame('150137', $result['ubigeo']);
        $this->assertSame('SANTA ANITA', $result['distrito']);
        $this->assertSame('LIMA', $result['provincia']);
        $this->assertSame('LIMA', $result['departamento']);
    }

    public function test_envia_post_json_con_bearer_a_json_pe(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $this->service->consultar('20552103816');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.json.pe/api/ruc'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['ruc'] === '20552103816';
        });
    }

    public function test_estado_activo_se_mapea_a_uno(): void
    {
        $respuesta = $this->respuestaExitosa();
        $respuesta['data']['estado'] = 'ACTIVO';

        Http::fake([
            'api.json.pe/*' => Http::response($respuesta, 200),
        ]);

        $result = $this->service->consultar('20552103816');

        $this->assertSame(1, $result['estado']);
    }

    public function test_ruc_no_encontrado_por_status_404(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response([
                'success' => false,
                'message' => 'RUC no encontrado',
            ], 404),
        ]);

        $this->expectException(SunatNotFoundException::class);

        $this->service->consultar('20123456789');
    }

    public function test_respuesta_success_false_lanza_not_found(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response([
                'success' => false,
                'message' => 'No se encontraron resultados',
                'data' => [],
            ], 200),
        ]);

        $this->expectException(SunatNotFoundException::class);

        $this->service->consultar('20123456789');
    }

    public function test_error_servidor_lanza_sunat_service_exception(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response(null, 500),
        ]);

        $this->expectException(SunatServiceException::class);

        $this->service->consultar('20552103816');
    }

    public function test_token_invalido_lanza_sunat_service_exception(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response(['success' => false, 'message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(SunatServiceException::class);

        $this->service->consultar('20552103816');
    }

    public function test_error_de_conexion_reintenta_y_lanza_sunat_service_exception(): void
    {
        $intentos = 0;

        Http::fake(function () use (&$intentos) {
            $intentos++;

            throw new ConnectionException('timeout');
        });

        try {
            $this->service->consultar('20552103816');
            $this->fail('Se esperaba una SunatServiceException.');
        } catch (SunatServiceException $e) {
            $this->assertStringContainsString('conexión', $e->getMessage());
        }

        $this->assertSame(3, $intentos);
    }

    public function test_token_no_configurado_lanza_sunat_service_exception(): void
    {
        config(['services.jsonpe.token' => null]);

        Http::fake();

        $this->expectException(SunatServiceException::class);

        $this->service->consultar('20552103816');
    }

    public function test_ruc_invalido_lanza_exception(): void
    {
        $this->expectException(SunatServiceException::class);

        $this->service->consultar('123');
    }

    public function test_cache_funciona_correctamente(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $this->service->consultar('20552103816');
        Http::assertSentCount(1);

        $this->service->consultar('20552103816');
        Http::assertSentCount(1);
    }

    public function test_ruc_no_encontrado_se_cachea_para_evitar_llamadas_repetidas(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response(['success' => false, 'message' => 'No encontrado'], 404),
        ]);

        for ($intento = 0; $intento < 2; $intento++) {
            try {
                $this->service->consultar('20123456789');
                $this->fail('Se esperaba una SunatNotFoundException.');
            } catch (SunatNotFoundException) {
                // esperado
            }
        }

        Http::assertSentCount(1);
    }

    public function test_usa_direccion_completa_y_ubigeo_array_como_respaldo(): void
    {
        $respuesta = $this->respuestaExitosa();
        $respuesta['data']['direccion'] = '';
        $respuesta['data']['ubigeo_sunat'] = '';
        $respuesta['data']['ubigeo'] = ['15', '1501', '150137'];

        Http::fake([
            'api.json.pe/*' => Http::response($respuesta, 200),
        ]);

        $result = $this->service->consultar('20552103816');

        $this->assertStringContainsString('LIMA - LIMA - SANTA ANITA', $result['direccion']);
        $this->assertSame('150137', $result['ubigeo']);
    }

    public function test_truncamiento_de_campos_largos(): void
    {
        $respuesta = $this->respuestaExitosa();
        $respuesta['data']['nombre_o_razon_social'] = str_repeat('A', 200);
        $respuesta['data']['distrito'] = str_repeat('B', 120);

        Http::fake([
            'api.json.pe/*' => Http::response($respuesta, 200),
        ]);

        $result = $this->service->consultar('20552103816');

        $this->assertSame(150, mb_strlen($result['razon_social']));
        $this->assertSame(100, mb_strlen($result['distrito']));
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
