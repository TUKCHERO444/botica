<?php

namespace App\Helpers;

class CantidadHelper
{
    public const ESCALA_CANTIDAD = 3;

    public const ESCALA_PRECIO = 4;

    public static function aBase(string|int|float $cantidad, string|int|float $factor): string
    {
        return bcmul((string) $cantidad, (string) $factor, self::ESCALA_CANTIDAD);
    }

    public static function comparar(string|int|float $a, string|int|float $b, int $escala = self::ESCALA_CANTIDAD): int
    {
        return bccomp((string) $a, (string) $b, $escala);
    }

    public static function sumar(string|int|float $a, string|int|float $b, int $escala = self::ESCALA_CANTIDAD): string
    {
        return bcadd((string) $a, (string) $b, $escala);
    }

    public static function restar(string|int|float $a, string|int|float $b, int $escala = self::ESCALA_CANTIDAD): string
    {
        return bcsub((string) $a, (string) $b, $escala);
    }

    public static function multiplicar(
        string|int|float $a,
        string|int|float $b,
        int $escala = self::ESCALA_PRECIO
    ): string {
        return bcmul((string) $a, (string) $b, $escala);
    }

    public static function redondear(string|int|float $valor, int $decimales = 2): string
    {
        $numero = (float) $valor;

        return number_format(round($numero, $decimales), $decimales, '.', '');
    }

    public static function esMultiplo(
        string|int|float $cantidad,
        string|int|float $paso,
        int $escala = self::ESCALA_CANTIDAD
    ): bool {
        if (self::comparar($paso, 0, $escala) <= 0) {
            return false;
        }

        $residuo = bcmod((string) $cantidad, (string) $paso, $escala);

        return self::comparar($residuo, 0, $escala) === 0;
    }

    public static function formatear(string|int|float|null $valor, int $decimales = self::ESCALA_CANTIDAD): string
    {
        if ($valor === null || $valor === '') {
            return '0';
        }

        $texto = number_format((float) $valor, $decimales, '.', '');
        $texto = rtrim(rtrim($texto, '0'), '.');

        return $texto === '' ? '0' : $texto;
    }
}
