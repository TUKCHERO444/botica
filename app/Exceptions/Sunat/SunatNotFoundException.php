<?php

namespace App\Exceptions\Sunat;

use Exception;

class SunatNotFoundException extends Exception
{
    protected $message = 'RUC no encontrado en SUNAT.';
}
