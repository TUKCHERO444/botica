<?php

namespace App\Helpers;

use App\Models\ProductoPresentacion;

class TotalesHelper
{
    /**
     * Calcula el total de una venta usando el precio vigente de cada presentación.
     */
    public static function venta(array $productos): string
    {
        $total = '0.00';

        foreach ($productos as $producto) {
            $presentacion = ProductoPresentacion::find($producto['producto_presentacion_id'] ?? null);

            if (! $presentacion) {
                continue;
            }

            $cantidad = (string) ($producto['cantidad'] ?? 0);
            $subTotal = CantidadHelper::redondear(
                CantidadHelper::multiplicar($cantidad, $presentacion->precio_venta, CantidadHelper::ESCALA_PRECIO),
                2
            );

            $total = CantidadHelper::sumar($total, $subTotal, 2);
        }

        return $total;
    }

    /**
     * Calcula el total de una atención: productos + servicios declarados.
     */
    public static function atencion(array $productos, array $servicios): string
    {
        $total = self::venta($productos);

        foreach ($servicios as $servicio) {
            $total = CantidadHelper::sumar($total, $servicio['precio'] ?? 0, 2);
        }

        return $total;
    }
}
