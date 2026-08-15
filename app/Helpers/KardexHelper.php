<?php

namespace App\Helpers;

use App\Models\KardexMovimiento;
use App\Models\Producto;

class KardexHelper
{
    public static function registrar(
        Producto $producto,
        string $tipoMovimiento,
        string $referenciaTipo,
        int $referenciaId,
        string $referenciaCodigo,
        int $cantidad,
        ?string $observacion = null
    ): KardexMovimiento {
        $stockAnterior = $producto->stock - $cantidad;
        $stockNuevo = $producto->stock;

        return KardexMovimiento::create([
            'producto_id'       => $producto->id_producto,
            'usuario_id'        => auth()->id(),
            'tipo_movimiento'   => $tipoMovimiento,
            'referencia_tipo'   => $referenciaTipo,
            'referencia_id'     => $referenciaId,
            'referencia_codigo' => $referenciaCodigo,
            'cantidad'          => $cantidad,
            'stock_anterior'    => $stockAnterior,
            'stock_nuevo'       => $stockNuevo,
            'observacion'       => $observacion,
        ]);
    }

    public static function eliminarPorReferencia(string $referenciaTipo, int $referenciaId): void
    {
        KardexMovimiento::where('referencia_tipo', $referenciaTipo)
            ->where('referencia_id', $referenciaId)
            ->delete();
    }
}
