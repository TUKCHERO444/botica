<?php

namespace App\Services;

use App\Exceptions\Reniec\ReniecNotFoundException;
use App\Exceptions\Reniec\ReniecServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReniecService
{
    private const CACHE_TTL = 5;

    private const HTTP_TIMEOUT = 5;

    public function consultar(string $dni): array
    {
        $this->validarFormato($dni);

        return Cache::remember("reniec:{$dni}", self::CACHE_TTL * 60, function () use ($dni) {
            return $this->consultarApi($dni);
        });
    }

    private function validarFormato(string $dni): void
    {
        if (!preg_match('/^\d{8}$/', $dni)) {
            throw new ReniecServiceException('El DNI debe tener 8 dígitos numéricos.');
        }
    }

    private function consultarApi(string $dni): array
    {
        $intentos = 0;
        $maxIntentos = 2;

        while ($intentos <= $maxIntentos) {
            try {
                $response = Http::withToken(config('services.reniec.token'))
                    ->timeout(self::HTTP_TIMEOUT)
                    ->get(config('services.reniec.url'), ['numero' => $dni]);
                break;
            } catch (ConnectionException $e) {
                $intentos++;
                if ($intentos > $maxIntentos) {
                    throw new ReniecServiceException(
                        'Error de conexión con el servicio RENIEC. Intente nuevamente.'
                    );
                }
                usleep(100_000);
            }
        }

        if ($response->status() === 400) {
            throw new ReniecNotFoundException(
                "El DNI {$dni} no fue encontrado en los registros RENIEC."
            );
        }

        if ($response->status() === 401) {
            throw new ReniecServiceException(
                'Error de autenticación con el servicio RENIEC. Contacte al administrador.'
            );
        }

        if ($response->status() >= 500) {
            throw new ReniecServiceException(
                'Error al consultar el servicio RENIEC. Intente nuevamente.'
            );
        }

        return $this->mapearRespuesta($response->json());
    }

    private function mapearRespuesta(array $data): array
    {
        return [
            'dni' => $data['document_number'],
            'nombre' => $this->truncar($data['first_name'] ?? '', 50),
            'apellido_paterno' => $this->truncar($data['first_last_name'] ?? '', 25),
            'apellido_materno' => $this->truncar($data['second_last_name'] ?? '', 25),
            'nombre_completo' => $data['full_name'] ?? '',
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
