<?php

namespace App\Services;

use App\Exceptions\JsonPe\JsonPeException;
use App\Exceptions\Sunat\SunatNotFoundException;
use App\Exceptions\Sunat\SunatServiceException;
use App\Services\JsonPe\JsonPeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

class SunatService
{
    private const CACHE_MINUTOS_DEFECTO = 60;

    private const CACHE_NEGATIVO_MINUTOS_DEFECTO = 10;

    public function __construct(
        private readonly JsonPeClient $jsonPe
    ) {}

    public function consultar(string $ruc): array
    {
        $this->validarFormato($ruc);

        $clave = "sunat:{$ruc}";
        $claveNegativa = "sunat:no-encontrado:{$ruc}";

        if (Cache::has($clave)) {
            return Cache::get($clave);
        }

        if (Cache::has($claveNegativa)) {
            throw new SunatNotFoundException(
                "El RUC {$ruc} no fue encontrado en los registros SUNAT."
            );
        }

        try {
            $resultado = $this->consultarApi($ruc);
        } catch (SunatNotFoundException $e) {
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

    private function validarFormato(string $ruc): void
    {
        if (! preg_match('/^\d{11}$/', $ruc)) {
            throw new SunatServiceException('El RUC debe tener 11 dígitos numéricos.');
        }
    }

    private function consultarApi(string $ruc): array
    {
        try {
            $response = $this->jsonPe->post('ruc', ['ruc' => $ruc]);
        } catch (ConnectionException $e) {
            throw new SunatServiceException(
                'Error de conexión con el servicio SUNAT. Intente nuevamente.',
                0,
                $e
            );
        } catch (JsonPeException $e) {
            logger()->error('json.pe: configuración inválida', [
                'error' => $e->getMessage(),
            ]);

            throw new SunatServiceException(
                'El servicio SUNAT no está configurado. Contacte al administrador.',
                0,
                $e
            );
        }

        return $this->mapearRespuesta($response, $ruc);
    }

    private function mapearRespuesta(Response $response, string $ruc): array
    {
        $status = $response->status();
        $payload = (array) ($response->json() ?? []);

        if (in_array($status, [401, 403], true)) {
            throw new SunatServiceException(
                'Error de autenticación con el servicio SUNAT. Contacte al administrador.'
            );
        }

        if (in_array($status, [404, 422], true) || ($status === 200 && ($payload['success'] ?? false) !== true)) {
            throw new SunatNotFoundException(
                "El RUC {$ruc} no fue encontrado en los registros SUNAT."
            );
        }

        if ($status >= 500 || $response->failed()) {
            throw new SunatServiceException(
                'Error al consultar el servicio SUNAT. Intente nuevamente.'
            );
        }

        $data = (array) ($payload['data'] ?? []);

        if (($data['ruc'] ?? '') === '' && ($data['nombre_o_razon_social'] ?? '') === '') {
            throw new SunatNotFoundException(
                "El RUC {$ruc} no fue encontrado en los registros SUNAT."
            );
        }

        return $this->mapearDatos($data, $ruc);
    }

    private function mapearDatos(array $data, string $ruc): array
    {
        $direccion = (string) ($data['direccion'] ?? '');

        if ($direccion === '') {
            $direccion = (string) ($data['direccion_completa'] ?? '');
        }

        $ubigeo = (string) ($data['ubigeo_sunat'] ?? '');

        if ($ubigeo === '' && is_array($data['ubigeo'] ?? null)) {
            $ubigeo = (string) (end($data['ubigeo']) ?: '');
        }

        return [
            'ruc' => (string) ($data['ruc'] ?? $ruc),
            'razon_social' => $this->truncar((string) ($data['nombre_o_razon_social'] ?? ''), 150),
            'condicion' => $this->truncar((string) ($data['condicion'] ?? ''), 15),
            'estado' => $this->mapearEstado((string) ($data['estado'] ?? '')),
            'direccion' => $this->truncar($direccion, 255),
            'ubigeo' => $this->truncar($ubigeo, 6),
            'distrito' => $this->truncar((string) ($data['distrito'] ?? ''), 100),
            'provincia' => $this->truncar((string) ($data['provincia'] ?? ''), 100),
            'departamento' => $this->truncar((string) ($data['departamento'] ?? ''), 100),
        ];
    }

    /**
     * La tabla `proveedor.estado` es smallInteger (0/1); json.pe devuelve texto.
     */
    private function mapearEstado(string $estado): int
    {
        return mb_strtoupper(trim($estado)) === 'ACTIVO' ? 1 : 0;
    }

    private function truncar(string $valor, int $maximo): string
    {
        if (mb_strlen($valor) > $maximo) {
            logger()->warning('SUNAT: valor truncado', [
                'original' => $valor,
                'maximo' => $maximo,
                'truncado' => mb_substr($valor, 0, $maximo),
            ]);

            return mb_substr($valor, 0, $maximo);
        }

        return $valor;
    }
}
