<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos del negocio para el ticket
    |--------------------------------------------------------------------------
    |
    | Se imprimen en la cabecera del ticket de venta. Configure estos valores
    | en el .env cuando se conecte la impresora térmica definitiva.
    |
    */

    'negocio' => env('TICKET_NEGOCIO', 'Botica'),

    'ruc' => env('TICKET_RUC'),

    'direccion' => env('TICKET_DIRECCION'),

    'telefono' => env('TICKET_TELEFONO'),

    /*
    |--------------------------------------------------------------------------
    | Impresora térmica
    |--------------------------------------------------------------------------
    |
    | Ancho del papel en milímetros. Use 80 para la mayoría de impresoras
    | térmicas y 58 para las mini impresoras portátiles.
    |
    */

    'ancho_mm' => (int) env('TICKET_ANCHO_MM', 80),

    'moneda' => env('TICKET_MONEDA', 'S/'),

    'pie' => env('TICKET_PIE', '¡Gracias por su compra!'),

];
