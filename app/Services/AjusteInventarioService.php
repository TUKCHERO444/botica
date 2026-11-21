<?php

namespace App\Services;

use App\Helpers\CantidadHelper;
use App\Models\AjusteInventario;
use App\Models\ProductoLote;
use Illuminate\Support\Facades\DB;

class AjusteInventarioService
{
    public function __construct(
        private readonly InventarioService $inventario
    ) {}

    public function crear(array $data): AjusteInventario
    {
        $tipo = $data['tipo'];

        if (! array_key_exists($tipo, AjusteInventario::TIPOS)) {
            throw new \RuntimeException('El tipo de ajuste no es válido.');
        }

        $lineas = collect($data['lineas'] ?? [])
            ->filter(fn ($linea) => filled($linea['producto_lote_id'] ?? null))
            ->values();

        if ($lineas->isEmpty()) {
            throw new \RuntimeException('Debe agregar al menos un lote al ajuste.');
        }

        if ($lineas->pluck('producto_lote_id')->duplicates()->isNotEmpty()) {
            throw new \RuntimeException('No se puede repetir el mismo lote en un ajuste.');
        }

        return DB::transaction(function () use ($data, $tipo, $lineas) {
            $ajuste = AjusteInventario::create([
                'correlativo' => null,
                'tipo' => $tipo,
                'fecha' => $data['fecha'] ?? today()->toDateString(),
                'motivo' => $data['motivo'],
                'observaciones' => $data['observaciones'] ?? null,
                'total' => 0,
                'user_id' => auth()->id(),
            ]);

            $ajuste->update([
                'correlativo' => 'AJU-'.str_pad((string) $ajuste->id_ajuste_inventario, 8, '0', STR_PAD_LEFT),
            ]);

            $total = '0.00';
            $creadas = 0;

            foreach ($lineas as $linea) {
                $lote = ProductoLote::with('producto')
                    ->whereKey($linea['producto_lote_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $lote) {
                    throw new \RuntimeException('Uno de los lotes seleccionados ya no existe.');
                }

                $cantidad = (string) $linea['cantidad'];
                $direccion = null;
                $cantidadAjuste = $cantidad;

                if ($tipo === 'conteo_fisico') {
                    $diferencia = CantidadHelper::restar($cantidad, $lote->cantidad_disponible_base, 3);

                    if (CantidadHelper::comparar($diferencia, 0) === 0) {
                        continue;
                    }

                    $direccion = CantidadHelper::comparar($diferencia, 0) > 0 ? 'entrada' : 'salida';
                    $cantidadAjuste = $direccion === 'entrada' ? $diferencia : bcmul($diferencia, '-1', 3);
                } elseif ($tipo === 'ajuste_positivo') {
                    if (CantidadHelper::comparar($cantidad, 0) <= 0) {
                        continue;
                    }

                    $direccion = 'entrada';
                } else {
                    if (CantidadHelper::comparar($cantidad, 0) <= 0) {
                        continue;
                    }

                    $direccion = 'salida';
                }

                $resultado = $direccion === 'entrada'
                    ? $this->inventario->ajustarEntradaLote(
                        $lote,
                        $cantidadAjuste,
                        'Ajuste',
                        $ajuste->id_ajuste_inventario,
                        $ajuste->correlativo,
                        $ajuste->motivo
                    )
                    : $this->inventario->ajustarSalidaLote(
                        $lote,
                        $cantidadAjuste,
                        'Ajuste',
                        $ajuste->id_ajuste_inventario,
                        $ajuste->correlativo,
                        $ajuste->motivo
                    );

                $ajuste->detalles()->create([
                    'producto_id_producto' => $lote->producto_id,
                    'producto_lote_id' => $lote->id,
                    'producto_presentacion_id' => $resultado['producto_presentacion_id'],
                    'lote_ingreso_id' => $resultado['lote_ingreso_id'],
                    'cantidad_base' => $resultado['cantidad_base'],
                    'costo_unitario_base' => $resultado['costo_unitario_base'],
                    'costo_total' => $resultado['costo_total'],
                ]);

                $total = CantidadHelper::sumar(
                    $total,
                    ltrim($resultado['costo_total'], '-'),
                    2
                );
                $creadas++;
            }

            if ($creadas === 0) {
                throw new \RuntimeException('El ajuste no genera ninguna diferencia o movimiento.');
            }

            $ajuste->update(['total' => CantidadHelper::redondear($total, 2)]);

            return $ajuste->fresh([
                'user',
                'detalles.producto',
                'detalles.lote',
                'detalles.presentacion',
            ]);
        });
    }
}
