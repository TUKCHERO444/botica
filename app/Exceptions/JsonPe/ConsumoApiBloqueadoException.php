<?php

namespace App\Exceptions\JsonPe;

use RuntimeException;

/**
 * Se lanza cuando un comando de base de datos intenta consumir la API.
 */
class ConsumoApiBloqueadoException extends RuntimeException
{
    protected $message = 'El consumo de APIs externas está bloqueado durante migraciones y seeders.';
}
