<?php

namespace App\Exceptions\Reniec;

use Exception;

class ReniecServiceException extends Exception
{
    protected $message = 'Error al consultar el servicio RENIEC.';
}
