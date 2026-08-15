<?php

namespace App\Services;

use App\Exceptions\Sunat\SunatNotFoundException;
use App\Exceptions\Sunat\SunatServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SunatService
{
    private const CACHE_TTL = 5;

    private const HTTP_TIMEOUT = 5;

    public function consultar(string $ruc): array
    {
        $this->validarFormato($ruc);

        return Cache::remember("sunat:{$ruc}", self::CACHE_TTL * 60, function () use ($ruc) {
            return $this->consultarApi($ruc);
        });
    }

    private function validarFormato(string $ruc): void
    {
        if (!preg_match('/^\d{11}$/', $ruc)) {
            throw new SunatServiceException('El RUC debe tener 11 dígitos numéricos.');
        }
    }

    private function consultarApi(string $ruc): array
    {
        $intentos = 0;
        $maxIntentos = 2;

        while ($intentos <= $maxIntentos) {
            try {
                $response = Http::withToken(config('services.sunat.token'))
                    ->timeout(self::HTTP_TIMEOUT)
                    ->get(config('services.sunat.url'), ['numero' => $ruc]);
                break;
            } catch (ConnectionException $e) {
                $intentos++;
                if ($intentos > $maxIntentos) {
                    throw new SunatServiceException(
                        'Error de conexión con el servicio SUNAT. Intente nuevamente.'
                    );
                }
                usleep(100_000);
            }
        }

        if ($response->status() === 422) {
            throw new SunatNotFoundException(
                "El RUC {$ruc} no fue encontrado en los registros SUNAT."
            );
        }

        if ($response->status() === 401) {
            throw new SunatServiceException(
                'Error de autenticación con el servicio SUNAT. Contacte al administrador.'
            );
        }

        if ($response->status() >= 500) {
            throw new SunatServiceException(
                'Error al consultar el servicio SUNAT. Intente nuevamente.'
            );
        }

        return $this->mapearRespuesta($response->json());
    }

    private function mapearRespuesta(array $data): array
    {
        return [
            'ruc' => $data['numero_documento'],
            'razon_social' => $this->truncar($data['razon_social'] ?? '', 150),
            'condicion' => $this->truncar($data['condicion'] ?? '', 15),
            'estado' => ($data['estado'] ?? '') === 'ACTIVO' ? 1 : 0,
            'direccion' => $this->truncar($data['direccion'] ?? '', 255),
            'ubigeo' => $data['ubigeo'] ?? '',
            'distrito' => $this->truncar($data['distrito'] ?? '', 100),
            'provincia' => $this->truncar($data['provincia'] ?? '', 100),
            'departamento' => $this->truncar($data['departamento'] ?? '', 100),
        ];
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
