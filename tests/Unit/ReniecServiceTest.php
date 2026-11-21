<?php

namespace Tests\Unit;

use App\Exceptions\Reniec\ReniecNotFoundException;
use App\Exceptions\Reniec\ReniecServiceException;
use App\Services\ReniecService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReniecServiceTest extends TestCase
{
    private ReniecService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.jsonpe.token' => 'test-token']);
        config(['services.jsonpe.url' => 'https://api.json.pe/api']);
        config(['services.jsonpe.timeout' => 5]);
        config(['services.jsonpe.cache_minutos' => 60]);

        $this->service = app(ReniecService::class);
    }

    public function test_consulta_exitosa_retorna_datos_mapeados(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $result = $this->service->consultar('46027897');

        $this->assertSame('46027897', $result['dni']);
        $this->assertSame('ROXANA KARINA', $result['nombre']);
        $this->assertSame('DELGADO', $result['apellido_paterno']);
        $this->assertSame('HUAMANI', $result['apellido_materno']);
        $this->assertSame('DELGADO HUAMANI, ROXANA KARINA', $result['nombre_completo']);
        $this->assertSame('', $result['direccion']);
        $this->assertSame('', $result['ubigeo']);
    }

    public function test_envia_post_json_con_bearer_a_json_pe(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $this->service->consultar('46027897');

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.json.pe/api/dni'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['dni'] === '46027897';
        });
    }

    public function test_dni_no_encontrado_por_status_404(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response([
                'success' => false,
                'message' => 'DNI no encontrado',
            ], 404),
        ]);

        $this->expectException(ReniecNotFoundException::class);

        $this->service->consultar('12345678');
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

        $this->expectException(ReniecNotFoundException::class);

        $this->service->consultar('12345678');
    }

    public function test_error_servidor_lanza_reniec_service_exception(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response(null, 500),
        ]);

        $this->expectException(ReniecServiceException::class);

        $this->service->consultar('46027897');
    }

    public function test_token_invalido_lanza_reniec_service_exception(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response(['success' => false, 'message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(ReniecServiceException::class);

        $this->service->consultar('46027897');
    }

    public function test_error_de_conexion_reintenta_y_lanza_reniec_service_exception(): void
    {
        $intentos = 0;

        Http::fake(function () use (&$intentos) {
            $intentos++;

            throw new ConnectionException('timeout');
        });

        try {
            $this->service->consultar('46027897');
            $this->fail('Se esperaba una ReniecServiceException.');
        } catch (ReniecServiceException $e) {
            $this->assertStringContainsString('conexión', $e->getMessage());
        }

        $this->assertSame(3, $intentos);
    }

    public function test_token_no_configurado_lanza_reniec_service_exception(): void
    {
        config(['services.jsonpe.token' => null]);

        Http::fake();

        $this->expectException(ReniecServiceException::class);

        $this->service->consultar('46027897');
    }

    public function test_dni_invalido_lanza_exception(): void
    {
        $this->expectException(ReniecServiceException::class);

        $this->service->consultar('123');
    }

    public function test_cache_funciona_correctamente(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response($this->respuestaExitosa(), 200),
        ]);

        $this->service->consultar('46027897');
        Http::assertSentCount(1);

        $this->service->consultar('46027897');
        Http::assertSentCount(1);
    }

    public function test_dni_no_encontrado_se_cachea_para_evitar_llamadas_repetidas(): void
    {
        Http::fake([
            'api.json.pe/*' => Http::response(['success' => false, 'message' => 'No encontrado'], 404),
        ]);

        for ($intento = 0; $intento < 2; $intento++) {
            try {
                $this->service->consultar('12345678');
                $this->fail('Se esperaba una ReniecNotFoundException.');
            } catch (ReniecNotFoundException) {
                // esperado
            }
        }

        Http::assertSentCount(1);
    }

    public function test_truncamiento_de_nombre_largo(): void
    {
        $nombreLargo = str_repeat('A', 60);
        $apellidoLargo = str_repeat('B', 30);

        Http::fake([
            'api.json.pe/*' => Http::response([
                'success' => true,
                'message' => 'exito',
                'data' => [
                    'numero' => '46027897',
                    'nombres' => $nombreLargo,
                    'apellido_paterno' => $apellidoLargo,
                    'apellido_materno' => 'NORMAL',
                    'nombre_completo' => "NORMAL, {$nombreLargo}",
                    'codigo_verificacion' => 7,
                ],
            ], 200),
        ]);

        $result = $this->service->consultar('46027897');

        $this->assertSame(50, mb_strlen($result['nombre']));
        $this->assertSame(25, mb_strlen($result['apellido_paterno']));
        $this->assertSame('NORMAL', $result['apellido_materno']);
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
