<?php

namespace App\Exceptions\Reniec;

use Exception;

class ReniecNotFoundException extends Exception
{
    protected $message = 'DNI no encontrado en RENIEC.';
}
