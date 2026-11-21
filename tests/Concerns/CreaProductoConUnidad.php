<?php

namespace Tests\Concerns;

use App\Helpers\CantidadHelper;
use App\Models\LoteIngreso;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;

trait CreaProductoConUnidad
{
    protected function crearUnidadBase(string $codigo = 'UND'): UnidadMedida
    {
        $presentacion = Presentacion::firstOrCreate(
            ['nombre' => 'Unidad'],
            ['abreviatura' => 'UND', 'activo' => true]
        );

        return UnidadMedida::firstOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => 'Unidad',
                'permite_fraccion' => true,
                'presentacion_base_id' => $presentacion->id_presentacion,
                'activo' => true,
            ]
        );
    }

    protected function crearPresentacionBase(Producto $producto, UnidadMedida $unidad): ProductoPresentacion
    {
        return ProductoPresentacion::firstOrCreate(
            [
                'producto_id_producto' => $producto->id_producto,
                'es_base' => true,
            ],
            [
                'presentacion_id' => $unidad->presentacion_base_id,
                'factor' => 1,
                'precio_venta' => $producto->precio_venta,
                'paso' => 1,
                'activo' => true,
                'orden' => 0,
            ]
        );
    }

    protected function crearLoteInicial(
        Producto $producto,
        ProductoPresentacion $presentacion,
        string|int|float $cantidad,
        string $codigo = 'LOTE-INICIAL',
        ?string $vencimiento = null,
        string|int|float|null $costo = null
    ): ProductoLote {
        $cantidad = bcadd((string) $cantidad, '0', 3);
        $costo = (string) ($costo ?? $producto->precio_compra ?? 0);
        $factor = (string) $presentacion->factor;
        $cantidadPresentacion = bcdiv($cantidad, $factor, 3);

        $lote = ProductoLote::create([
            'producto_id' => $producto->id_producto,
            'codigo_lote' => $codigo,
            'fecha_vencimiento' => $vencimiento ?? now()->addYear()->toDateString(),
            'estado' => 'disponible',
            'cantidad_inicial_base' => $cantidad,
            'cantidad_disponible_base' => $cantidad,
            'es_inicial' => true,
        ]);

        LoteIngreso::create([
            'producto_lote_id' => $lote->id,
            'ingreso_id' => null,
            'detalle_ingreso_id' => null,
            'producto_presentacion_id' => $presentacion->id_producto_presentacion,
            'cantidad_presentacion' => $cantidadPresentacion,
            'factor' => $factor,
            'cantidad_base' => $cantidad,
            'cantidad_disponible_base' => $cantidad,
            'costo_unitario_base' => $costo,
            'costo_total' => CantidadHelper::multiplicar($cantidad, $costo, 2),
        ]);

        $sumaLotes = ProductoLote::where('producto_id', $producto->id_producto)
            ->where('estado', '!=', 'baja')
            ->sum('cantidad_disponible_base');

        $producto->stock = (string) $sumaLotes;
        $producto->inventario = $producto->stock;
        $producto->save();

        return $lote;
    }
}
