<?php

namespace Tests\Unit;

use App\Exceptions\JsonPe\ConsumoApiBloqueadoException;
use App\Support\ApiExternaGuard;
use Tests\TestCase;

class ApiExternaGuardTest extends TestCase
{
    public function test_bloquea_comandos_de_base_de_datos(): void
    {
        foreach (['migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:rollback', 'db:seed', 'db:wipe'] as $comando) {
            $this->assertTrue(
                ApiExternaGuard::debeBloquear($comando),
                "El comando {$comando} debería estar bloqueado."
            );
        }
    }

    public function test_no_bloquea_comandos_de_aplicacion(): void
    {
        foreach (['test', 'queue:work', 'serve', 'config:cache', 'inventario:conciliar'] as $comando) {
            $this->assertFalse(
                ApiExternaGuard::debeBloquear($comando),
                "El comando {$comando} no debería estar bloqueado."
            );
        }
    }

    public function test_verificar_lanza_excepcion_durante_db_seed(): void
    {
        $argvOriginal = $_SERVER['argv'] ?? [];
        $_SERVER['argv'] = ['artisan', 'db:seed'];

        try {
            $this->expectException(ConsumoApiBloqueadoException::class);
            ApiExternaGuard::verificar('json.pe');
        } finally {
            $_SERVER['argv'] = $argvOriginal;
        }
    }
}
