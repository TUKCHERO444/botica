<?php

namespace App\Services;

use App\Exceptions\JsonPe\JsonPeException;
use App\Exceptions\Reniec\ReniecNotFoundException;
use App\Exceptions\Reniec\ReniecServiceException;
use App\Services\JsonPe\JsonPeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

class ReniecService
{
    private const CACHE_MINUTOS_DEFECTO = 60;

    private const CACHE_NEGATIVO_MINUTOS_DEFECTO = 10;

    public function __construct(
        private readonly JsonPeClient $jsonPe
    ) {}

    public function consultar(string $dni): array
    {
        $this->validarFormato($dni);

        $clave = "reniec:{$dni}";
        $claveNegativa = "reniec:no-encontrado:{$dni}";

        if (Cache::has($clave)) {
            return Cache::get($clave);
        }

        if (Cache::has($claveNegativa)) {
            throw new ReniecNotFoundException(
                "El DNI {$dni} no fue encontrado en los registros RENIEC."
            );
        }

        try {
            $resultado = $this->consultarApi($dni);
        } catch (ReniecNotFoundException $e) {
            $minutosNegativos = max(
                1,
                (int) config('services.jsonpe.cache_negativo_minutos', self::CACHE_NEGATIVO_MINUTOS_DEFECTO)
            );

            Cache::put($claveNegativa, true, now()->addMinutes($minutosNegativos));

            throw $e;
        }

        $minutos = max(1, (int) config('services.jsonpe.cache_minutos', self::CACHE_MINUTOS_DEFECTO));

        Cache::put($clave, $resultado, now()->addMinutes($minutos));
        Cache::forget($claveNegativa);

        return $resultado;
    }

    private function validarFormato(string $dni): void
    {
        if (! preg_match('/^\d{8}$/', $dni)) {
            throw new ReniecServiceException('El DNI debe tener 8 dígitos numéricos.');
        }
    }

    private function consultarApi(string $dni): array
    {
        try {
            $response = $this->jsonPe->post('dni', ['dni' => $dni]);
        } catch (ConnectionException $e) {
            throw new ReniecServiceException(
                'Error de conexión con el servicio RENIEC. Intente nuevamente.',
                0,
                $e
            );
        } catch (JsonPeException $e) {
            logger()->error('json.pe: configuración inválida', [
                'error' => $e->getMessage(),
            ]);

            throw new ReniecServiceException(
                'El servicio RENIEC no está configurado. Contacte al administrador.',
                0,
                $e
            );
        }

        return $this->mapearRespuesta($response, $dni);
    }

    private function mapearRespuesta(Response $response, string $dni): array
    {
        $status = $response->status();
        $payload = (array) ($response->json() ?? []);

        if (in_array($status, [401, 403], true)) {
            throw new ReniecServiceException(
                'Error de autenticación con el servicio RENIEC. Contacte al administrador.'
            );
        }

        if (in_array($status, [404, 422], true) || ($status === 200 && ($payload['success'] ?? false) !== true)) {
            throw new ReniecNotFoundException(
                "El DNI {$dni} no fue encontrado en los registros RENIEC."
            );
        }

        if ($status >= 500 || $response->failed()) {
            throw new ReniecServiceException(
                'Error al consultar el servicio RENIEC. Intente nuevamente.'
            );
        }

        $data = (array) ($payload['data'] ?? []);

        if (($data['numero'] ?? '') === '' && ($data['nombres'] ?? '') === '') {
            throw new ReniecNotFoundException(
                "El DNI {$dni} no fue encontrado en los registros RENIEC."
            );
        }

        return $this->mapearDatos($data, $dni);
    }

    private function mapearDatos(array $data, string $dni): array
    {
        $nombres = (string) ($data['nombres'] ?? '');
        $apellidoPaterno = (string) ($data['apellido_paterno'] ?? '');
        $apellidoMaterno = (string) ($data['apellido_materno'] ?? '');

        $nombreCompleto = (string) ($data['nombre_completo'] ?? '');

        if ($nombreCompleto === '') {
            $apellidos = trim("{$apellidoPaterno} {$apellidoMaterno}");
            $nombreCompleto = trim($apellidos.($nombres !== '' ? ", {$nombres}" : ''), ' ,');
        }

        return [
            'dni' => (string) ($data['numero'] ?? $dni),
            'nombre' => $this->truncar($nombres, 50),
            'apellido_paterno' => $this->truncar($apellidoPaterno, 25),
            'apellido_materno' => $this->truncar($apellidoMaterno, 25),
            'nombre_completo' => $nombreCompleto,
            'direccion' => $this->truncar(
                (string) ($data['direccion_completa'] ?? $data['direccion'] ?? ''),
                255
            ),
            'ubigeo' => (string) ($data['ubigeo_reniec'] ?? ''),
            'codigo_verificacion' => (string) ($data['codigo_verificacion'] ?? ''),
        ];
    }

    private function truncar(string $valor, int $maximo): string
    {
        if (mb_strlen($valor) > $maximo) {
            logger()->warning('RENIEC: valor truncado', [
                'original' => $valor,
                'maximo' => $maximo,
                'truncado' => mb_substr($valor, 0, $maximo),
            ]);

            return mb_substr($valor, 0, $maximo);
        }

        return $valor;
    }
}
