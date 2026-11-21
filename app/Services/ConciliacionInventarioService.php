<?php

namespace App\Services;

use App\Helpers\CantidadHelper;
use App\Models\ConciliacionInventario;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class ConciliacionInventarioService
{
    public function ejecutar(
        ?int $userId = null,
        string $origen = ConciliacionInventario::ORIGEN_COMANDO,
        bool $persistir = true
    ): array {
        if (! in_array($origen, [
            ConciliacionInventario::ORIGEN_COMANDO,
            ConciliacionInventario::ORIGEN_MANUAL,
            ConciliacionInventario::ORIGEN_PROGRAMADA,
        ], true)) {
            throw new \RuntimeException('El origen de la conciliación no es válido.');
        }

        $productos = Producto::with(['lotes' => function ($query) {
            $query->withSum('capas', 'cantidad_disponible_base');
        }])->orderBy('nombre')->get();

        $detalles = [];
        $productosConDiferencia = 0;
        $lotesInconsistentes = 0;
        $diferenciaTotal = '0.000';

        foreach ($productos as $producto) {
            $stockCache = (string) $producto->stock;
            $stockLotes = '0.000';

            foreach ($producto->lotes as $lote) {
                $stockLotes = CantidadHelper::sumar(
                    $stockLotes,
                    (string) ($lote->capas_sum_cantidad_disponible_base ?? 0),
                    3
                );
            }

            $diferenciaProducto = CantidadHelper::restar($stockCache, $stockLotes, 3);
            $lotesDetalle = [];

            foreach ($producto->lotes as $lote) {
                $cantidadLote = (string) $lote->cantidad_disponible_base;
                $sumaCapas = (string) ($lote->capas_sum_cantidad_disponible_base ?? 0);
                $diferenciaLote = CantidadHelper::restar($cantidadLote, $sumaCapas, 3);

                if (CantidadHelper::comparar($diferenciaLote, 0, 3) === 0) {
                    continue;
                }

                $lotesDetalle[] = [
                    'producto_lote_id' => $lote->id,
                    'codigo_lote' => $lote->codigo_lote,
                    'cantidad_lote' => $cantidadLote,
                    'suma_capas' => $sumaCapas,
                    'diferencia' => $diferenciaLote,
                ];
            }

            $tieneDiferencia = CantidadHelper::comparar($diferenciaProducto, 0, 3) !== 0
                || $lotesDetalle !== [];

            if (! $tieneDiferencia) {
                continue;
            }

            $productosConDiferencia++;
            $lotesInconsistentes += count($lotesDetalle);

            $diferenciaLotes = '0.000';
            foreach ($lotesDetalle as $lote) {
                $diferenciaLotes = CantidadHelper::sumar(
                    $diferenciaLotes,
                    ltrim($lote['diferencia'], '-'),
                    3
                );
            }

            $diferenciaLinea = $lotesDetalle !== []
                ? $diferenciaLotes
                : ltrim($diferenciaProducto, '-');

            $diferenciaTotal = CantidadHelper::sumar($diferenciaTotal, $diferenciaLinea, 3);

            $detalles[] = [
                'producto_id' => $producto->id_producto,
                'producto_nombre' => $producto->nombre,
                'stock_cache' => $stockCache,
                'stock_lotes' => $stockLotes,
                'diferencia' => $diferenciaProducto,
                'lotes_inconsistentes' => count($lotesDetalle),
                'lotes' => $lotesDetalle,
            ];
        }

        $estado = $productosConDiferencia === 0
            ? ConciliacionInventario::ESTADO_OK
            : ConciliacionInventario::ESTADO_CON_DIFERENCIAS;

        $resultado = [
            'conciliacion' => null,
            'total_productos' => $productos->count(),
            'productos_con_diferencia' => $productosConDiferencia,
            'lotes_inconsistentes' => $lotesInconsistentes,
            'diferencia_total' => CantidadHelper::redondear($diferenciaTotal, 3),
            'estado' => $estado,
            'detalles' => $detalles,
        ];

        if (! $persistir) {
            return $resultado;
        }

        $conciliacion = DB::transaction(function () use (
            $userId,
            $origen,
            $resultado
        ) {
            $conciliacion = ConciliacionInventario::create([
                'fecha' => now(),
                'user_id' => $userId,
                'origen' => $origen,
                'total_productos' => $resultado['total_productos'],
                'productos_con_diferencia' => $resultado['productos_con_diferencia'],
                'lotes_inconsistentes' => $resultado['lotes_inconsistentes'],
                'diferencia_total' => $resultado['diferencia_total'],
                'estado' => $resultado['estado'],
            ]);

            foreach ($resultado['detalles'] as $detalle) {
                $linea = $conciliacion->detalles()->create([
                    'producto_id' => $detalle['producto_id'],
                    'stock_cache' => $detalle['stock_cache'],
                    'stock_lotes' => $detalle['stock_lotes'],
                    'diferencia' => $detalle['diferencia'],
                    'lotes_inconsistentes' => $detalle['lotes_inconsistentes'],
                ]);

                foreach ($detalle['lotes'] as $lote) {
                    $linea->detallesLote()->create([
                        'producto_lote_id' => $lote['producto_lote_id'],
                        'cantidad_lote' => $lote['cantidad_lote'],
                        'suma_capas' => $lote['suma_capas'],
                        'diferencia' => $lote['diferencia'],
                    ]);
                }
            }

            return $conciliacion->fresh([
                'user',
                'detalles.producto',
                'detalles.detallesLote.lote',
            ]);
        });

        $resultado['conciliacion'] = $conciliacion;

        return $resultado;
    }
}
