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

        config(['services.reniec.token' => 'test-token']);
        config(['services.reniec.url' => 'https://api.decolecta.com/v1/reniec/dni']);

        $this->service = app(ReniecService::class);
    }

    public function test_consulta_exitosa_retorna_datos_mapeados(): void
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

        $result = $this->service->consultar('46027897');

        $this->assertSame('46027897', $result['dni']);
        $this->assertSame('ROXANA KARINA', $result['nombre']);
        $this->assertSame('DELGADO', $result['apellido_paterno']);
        $this->assertSame('HUAMANI', $result['apellido_materno']);
        $this->assertSame('DELGADO HUAMANI ROXANA KARINA', $result['nombre_completo']);
    }

    public function test_dni_no_encontrado_lanza_reniec_not_found_exception(): void
    {
        Http::fake([
            'api.decolecta.com/*' => Http::response(['error' => 'Invalid request'], 400),
        ]);

        $this->expectException(ReniecNotFoundException::class);

        $this->service->consultar('12345678');
    }

    public function test_error_servidor_lanza_reniec_service_exception(): void
    {
        Http::fake([
            'api.decolecta.com/*' => Http::response(null, 500),
        ]);

        $this->expectException(ReniecServiceException::class);

        $this->service->consultar('46027897');
    }

    public function test_token_invalido_lanza_reniec_service_exception(): void
    {
        Http::fake([
            'api.decolecta.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

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
            'api.decolecta.com/*' => Http::response([
                'first_name' => 'ROXANA KARINA',
                'first_last_name' => 'DELGADO',
                'second_last_name' => 'HUAMANI',
                'full_name' => 'DELGADO HUAMANI ROXANA KARINA',
                'document_number' => '46027897',
            ], 200),
        ]);

        $this->service->consultar('46027897');
        Http::assertSentCount(1);

        $this->service->consultar('46027897');
        Http::assertSentCount(1);
    }

    public function test_truncamiento_de_nombre_largo(): void
    {
        $nombreLargo = str_repeat('A', 60);
        $apellidoLargo = str_repeat('B', 30);

        Http::fake([
            'api.decolecta.com/*' => Http::response([
                'first_name' => $nombreLargo,
                'first_last_name' => $apellidoLargo,
                'second_last_name' => 'NORMAL',
                'full_name' => "NORMAL {$apellidoLargo} {$nombreLargo}",
                'document_number' => '46027897',
            ], 200),
        ]);

        $result = $this->service->consultar('46027897');

        $this->assertSame(50, mb_strlen($result['nombre']));
        $this->assertSame(25, mb_strlen($result['apellido_paterno']));
        $this->assertSame('NORMAL', $result['apellido_materno']);
    }
}
