<?php

namespace App\Helpers;

use App\Models\KardexMovimiento;
use App\Models\LoteIngreso;
use App\Models\Producto;
use App\Models\ProductoPresentacion;

class KardexHelper
{
    public static function registrar(
        Producto $producto,
        string $tipoMovimiento,
        string $referenciaTipo,
        int $referenciaId,
        string $referenciaCodigo,
        string|int|float $cantidad,
        ?string $observacion = null,
        ?ProductoPresentacion $presentacion = null,
        string|int|float|null $cantidadPresentacion = null,
        ?LoteIngreso $capa = null
    ): KardexMovimiento {
        $stockAnterior = CantidadHelper::restar($producto->stock, $cantidad);
        $stockNuevo = $producto->stock;

        return KardexMovimiento::create([
            'producto_id' => $producto->id_producto,
            'usuario_id' => auth()->id(),
            'tipo_movimiento' => $tipoMovimiento,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'referencia_codigo' => $referenciaCodigo,
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'producto_presentacion_id' => $presentacion?->id_producto_presentacion,
            'cantidad_presentacion' => $cantidadPresentacion ?? abs((float) $cantidad),
            'unidad_presentacion' => $presentacion?->nombre_completo,
            'factor' => $presentacion?->factor,
            'producto_lote_id' => $capa?->producto_lote_id,
            'lote_codigo' => $capa?->lote?->codigo_lote,
            'fecha_vencimiento' => $capa?->lote?->fecha_vencimiento,
            'costo_unitario_base' => $capa?->costo_unitario_base,
            'observacion' => $observacion,
        ]);
    }
}
