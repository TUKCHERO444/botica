<?php

namespace App\Helpers;

use App\Models\Producto;
use App\Models\ProductoPresentacion;

class PresentacionStockHelper
{
    /**
     * Desglosa un stock en unidad base a presentaciones equivalentes.
     * No modifica stock: solo calcula textos para la interfaz.
     */
    public static function desglosar(Producto $producto, ?string $stockBase = null): array
    {
        $stock = (string) ($stockBase ?? $producto->stockVendible());
        $unidad = $producto->unidadBase?->codigo ?? '';
        $stockFormateado = CantidadHelper::formatear($stock).($unidad ? ' '.$unidad : '');

        $presentaciones = $producto->relationLoaded('presentaciones')
            ? $producto->presentaciones
            : $producto->presentaciones()->with('presentacion')->get();

        $activas = $presentaciones
            ->filter(fn (ProductoPresentacion $pp) => $pp->activo)
            ->sortByDesc(fn (ProductoPresentacion $pp) => (float) $pp->factor)
            ->values();

        $restante = $stock;
        $partes = [];

        foreach ($activas as $pp) {
            $factor = (string) $pp->factor;

            if (CantidadHelper::comparar($factor, 1, 3) <= 0) {
                continue;
            }

            $entero = self::parteEntera(bcdiv($restante, $factor, 6));

            if (CantidadHelper::comparar($entero, 0, 3) <= 0) {
                continue;
            }

            $consumido = bcmul($entero, $factor, 3);
            $restante = CantidadHelper::restar($restante, $consumido, 3);

            $partes[] = [
                'presentacion_id' => $pp->id_producto_presentacion,
                'cantidad' => $entero,
                'factor' => $factor,
                'texto' => self::textoParte($pp, $entero),
            ];
        }

        $base = $activas->first(
            fn (ProductoPresentacion $pp) => CantidadHelper::comparar($pp->factor, 1, 3) === 0
        );

        if ($base && CantidadHelper::comparar($restante, 0, 3) > 0) {
            $partes[] = [
                'presentacion_id' => $base->id_producto_presentacion,
                'cantidad' => $restante,
                'factor' => '1',
                'texto' => self::textoParte($base, $restante),
            ];
        }

        $desglose = collect($partes)
            ->pluck('texto')
            ->filter()
            ->implode(' + ');

        if ($desglose === '') {
            $desglose = 'Sin stock vendible';
        }

        $porPresentacion = [];

        foreach ($activas as $pp) {
            $porPresentacion[$pp->id_producto_presentacion] = self::maximoPorPresentacion($pp, $stock, $unidad);
        }

        return [
            'stock_base' => $stock,
            'stock_base_formateado' => $stockFormateado,
            'desglose' => $desglose,
            'partes' => $partes,
            'por_presentacion' => $porPresentacion,
        ];
    }

    /**
     * Presentaciones activas listas para calcular equivalencias en el navegador.
     *
     * @return array<int, array{nombre: string, factor: float}>
     */
    public static function presentacionesParaJs(Producto $producto): array
    {
        $presentaciones = $producto->relationLoaded('presentaciones')
            ? $producto->presentaciones
            : $producto->presentaciones()->with('presentacion')->get();

        return $presentaciones
            ->filter(fn (ProductoPresentacion $pp) => $pp->activo)
            ->sortByDesc(fn (ProductoPresentacion $pp) => (float) $pp->factor)
            ->map(fn (ProductoPresentacion $pp) => [
                'nombre' => $pp->presentacion?->nombre ?? $pp->nombre_completo,
                'factor' => (float) $pp->factor,
            ])
            ->values()
            ->all();
    }

    /**
     * Máximo disponible en una presentación concreta, respetando su paso.
     *
     * @return array{maximo: string, maximo_formateado: string, sobrante: string, sobrante_formateado: string, texto: string}
     */
    public static function maximoPorPresentacion(
        ProductoPresentacion $pp,
        string|int|float $stockBase,
        ?string $unidad = null
    ): array {
        $stock = (string) $stockBase;
        $factor = (string) $pp->factor;
        $paso = (string) ($pp->paso ?: 1);
        $cociente = bcdiv(bcdiv($stock, $factor, 6), $paso, 6);
        $entero = self::parteEntera($cociente);
        $maximo = bcmul($entero, $paso, 3);
        $sobrante = CantidadHelper::restar(
            $stock,
            bcmul($maximo, $factor, 3),
            3
        );

        $texto = self::textoParte($pp, $maximo);

        if (CantidadHelper::comparar($sobrante, 0, 3) > 0) {
            $texto .= ' (sobran '.CantidadHelper::formatear($sobrante)
                .($unidad ? ' '.$unidad : '').')';
        }

        return [
            'maximo' => $maximo,
            'maximo_formateado' => CantidadHelper::formatear($maximo),
            'sobrante' => $sobrante,
            'sobrante_formateado' => CantidadHelper::formatear($sobrante),
            'texto' => $texto,
        ];
    }

    private static function textoParte(ProductoPresentacion $pp, string|int|float $cantidad): string
    {
        $factor = (string) $pp->factor;
        $nombreBase = $pp->presentacion?->nombre ?? $pp->nombre_completo;
        $cantidadTexto = CantidadHelper::formatear($cantidad);
        $plural = CantidadHelper::comparar($cantidad, 1, 3) === 0 ? '' : 's';
        $nombre = $plural === '' ? $nombreBase : self::pluralizar($nombreBase);

        if (CantidadHelper::comparar($factor, 1, 3) > 0) {
            $nombre .= ' x'.CantidadHelper::formatear($factor);
        }

        return trim($cantidadTexto.' '.$nombre);
    }

    private static function parteEntera(string $valor): string
    {
        $parte = explode('.', $valor, 2)[0];
        $parte = ltrim($parte, '0');

        return $parte === '' ? '0' : $parte;
    }

    /**
     * Plural mínimo en español para nombres de presentación.
     * Evita textos como "Unidads" o "Lápizs".
     */
    private static function pluralizar(string $palabra): string
    {
        if ($palabra === '') {
            return $palabra;
        }

        $ultima = mb_strtolower(mb_substr($palabra, -1));

        if ($ultima === 'z') {
            return mb_substr($palabra, 0, -1).'ces';
        }

        if ($ultima === 'd') {
            return $palabra.'es';
        }

        return $palabra.'s';
    }
}
