<?php

namespace App\Services\JsonPe;

use App\Exceptions\JsonPe\JsonPeException;
use App\Support\ApiExternaGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP del proveedor json.pe.
 *
 * Es la única puerta de entrada a la API externa. Al centralizar aquí el
 * transporte quedan en un solo lugar el token, el timeout, los reintentos y
 * el bloqueo de consumo durante migraciones/seeders.
 */
class JsonPeClient
{
    private const MAX_REINTENTOS = 2;

    private const ESPERA_REINTENTO_MS = 100;

    private const TIMEOUT_DEFECTO = 8;

    /**
     * @throws ConnectionException
     * @throws JsonPeException
     */
    public function post(string $ruta, array $datos): Response
    {
        ApiExternaGuard::verificar('json.pe');

        $token = (string) config('services.jsonpe.token');

        if ($token === '') {
            throw new JsonPeException(
                'No se configuró JSONPE_API_TOKEN. La consulta a json.pe no está disponible.'
            );
        }

        $intentos = 0;

        while (true) {
            try {
                return Http::baseUrl(rtrim((string) config('services.jsonpe.url'), '/'))
                    ->withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout((int) config('services.jsonpe.timeout', self::TIMEOUT_DEFECTO))
                    ->post(ltrim($ruta, '/'), $datos);
            } catch (ConnectionException $e) {
                $intentos++;

                if ($intentos > self::MAX_REINTENTOS) {
                    throw $e;
                }

                usleep(self::ESPERA_REINTENTO_MS * 1000);
            }
        }
    }
}
