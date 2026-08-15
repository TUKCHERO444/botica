<?php

namespace App\Helpers;

use App\Helpers\KardexHelper;
use App\Models\LoteProducto;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class StockHelper
{
    public static function consumir(Producto $producto, int $cantidad, $referencia, string $tipo): array
    {
        $lotes = LoteProducto::where('producto_id_producto', $producto->id_producto)
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->orderBy('id_lote')
            ->get();

        if ($lotes->isEmpty()) {
            throw new \RuntimeException(
                "StockHelper::consumir — El producto \"{$producto->nombre}\" (ID: {$producto->id_producto}) "
                . "tiene stock={$producto->stock} pero no tiene lotes disponibles con cantidad > 0. "
                . "Realice un ingreso para reponer el inventario."
            );
        }

        $porConsumir = $cantidad;
        $consumos = [];

        DB::transaction(function () use ($lotes, &$porConsumir, &$consumos, $referencia, $tipo, $producto, $cantidad) {
            foreach ($lotes as $lote) {
                if ($porConsumir <= 0) {
                    break;
                }

                $aConsumir = min($lote->cantidad, $porConsumir);
                $lote->decrement('cantidad', $aConsumir);
                $porConsumir -= $aConsumir;

                if ($tipo === 'venta') {
                    $referencia->lotesConsumidos()->create([
                        'venta_id_venta' => $referencia->id_venta,
                        'producto_id_producto' => $producto->id_producto,
                        'lote_id_lote' => $lote->id_lote,
                        'cantidad' => $aConsumir,
                    ]);
                } elseif ($tipo === 'atencion') {
                    $referencia->lotesConsumidos()->create([
                        'atencion_id_atencion' => $referencia->id_atencion,
                        'producto_id_producto' => $producto->id_producto,
                        'lote_id_lote' => $lote->id_lote,
                        'cantidad' => $aConsumir,
                    ]);
                }

                $consumos[] = [
                    'lote' => $lote,
                    'cantidad' => $aConsumir,
                ];
            }

            $efectivamenteConsumido = $cantidad - $porConsumir;

            if ($efectivamenteConsumido > 0) {
                $producto->decrement('stock', $efectivamenteConsumido);

                $refCodigo = $tipo === 'venta'
                    ? $referencia->correlativo
                    : '#' . $referencia->id_atencion;

                KardexHelper::registrar(
                    $producto,
                    strtoupper($tipo),
                    ucfirst($tipo),
                    $referencia->getKey(),
                    $refCodigo,
                    -$efectivamenteConsumido,
                    $tipo === 'venta' ? 'Venta registrada' : 'Atención registrada'
                );
            }
        });

        return $consumos;
    }

    public static function revertir($referencia, string $tipo): void
    {
        DB::transaction(function () use ($referencia, $tipo) {
            $lotesConsumidos = $referencia->lotesConsumidos;

            $productosAjustados = [];

            foreach ($lotesConsumidos as $consumo) {
                LoteProducto::where('id_lote', $consumo->lote_id_lote)
                    ->increment('cantidad', $consumo->cantidad);

                $productosAjustados[$consumo->producto_id_producto] =
                    ($productosAjustados[$consumo->producto_id_producto] ?? 0) + $consumo->cantidad;
            }

            foreach ($productosAjustados as $productoId => $cantidad) {
                Producto::where('id_producto', $productoId)
                    ->increment('stock', $cantidad);
            }

            $lotesConsumidos->each->delete();

            KardexHelper::eliminarPorReferencia(ucfirst($tipo), $referencia->getKey());
        });
    }
}
