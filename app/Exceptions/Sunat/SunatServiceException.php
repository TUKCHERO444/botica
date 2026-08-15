<?php

namespace App\Exceptions\Sunat;

use Exception;

class SunatServiceException extends Exception
{
    protected $message = 'Error al consultar el servicio SUNAT.';
}
