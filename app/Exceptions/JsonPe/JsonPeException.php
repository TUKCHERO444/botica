<?php

namespace App\Exceptions\JsonPe;

use RuntimeException;

/**
 * Error de configuración o transporte del proveedor json.pe.
 */
class JsonPeException extends RuntimeException
{
    protected $message = 'Error al comunicarse con el proveedor json.pe.';
}
