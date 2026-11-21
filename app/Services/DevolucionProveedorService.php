<?php

namespace App\Services;

use App\Helpers\CantidadHelper;
use App\Models\Caja;
use App\Models\DetalleIngreso;
use App\Models\DevolucionProveedor;
use App\Models\Ingreso;
use App\Models\LoteIngreso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DevolucionProveedorService
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly CajaService $cajas
    ) {}

    public function crear(array $data): DevolucionProveedor
    {
        return DB::transaction(function () use ($data) {
            $ingreso = Ingreso::with('proveedor')
                ->whereKey($data['ingreso_id_ingreso'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $ingreso->estaRecibida()) {
                throw new \RuntimeException('Solo se puede devolver mercadería de una compra recibida.');
            }

            $lineas = collect($data['lineas'] ?? [])
                ->filter(fn ($linea) => (float) ($linea['cantidad'] ?? 0) > 0)
                ->values();

            if ($lineas->isEmpty()) {
                throw new \RuntimeException('Seleccione al menos una línea con cantidad mayor a cero.');
            }

            if ($lineas->pluck('detalle_ingreso_id')->duplicates()->isNotEmpty()) {
                throw new \RuntimeException('No se puede repetir la misma línea en una devolución.');
            }

            $reembolsado = filter_var($data['reembolsado_a_caja'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($reembolsado && blank($data['metodo_pago'] ?? null)) {
                throw new \RuntimeException('Seleccione el método de reembolso para registrar el ingreso en caja.');
            }

            $caja = null;

            if ($reembolsado) {
                $caja = Caja::abierta()->lockForUpdate()->first();

                if (! $caja) {
                    throw new \RuntimeException('No hay una caja abierta para registrar el reembolso.');
                }
            }

            $devolucion = DevolucionProveedor::create([
                'correlativo' => null,
                'ingreso_id_ingreso' => $ingreso->id_ingreso,
                'proveedor_id_proveedor' => $ingreso->proveedor_id_proveedor,
                'fecha' => $data['fecha'] ?? today()->toDateString(),
                'motivo' => $data['motivo'],
                'estado' => DevolucionProveedor::ESTADO_REGISTRADA,
                'total' => 0,
                'reembolsado_a_caja' => $reembolsado,
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'referencia_pago' => $data['referencia_pago'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $devolucion->update([
                'correlativo' => 'DVP-'.str_pad((string) $devolucion->id_devolucion_proveedor, 8, '0', STR_PAD_LEFT),
            ]);

            $total = '0.00';

            foreach ($this->resolverLineas($ingreso, $lineas) as $linea) {
                $resultado = $this->inventario->consumirDeCapa(
                    $linea['capa'],
                    $linea['presentacion'],
                    $linea['cantidad'],
                    'DEVOLUCION',
                    'DevolucionProveedor',
                    $devolucion->id_devolucion_proveedor,
                    $devolucion->correlativo,
                    'Devolución a proveedor'
                );

                $devolucion->detalles()->create([
                    'detalle_ingreso_id' => $linea['detalle']->id_detalle_ingreso,
                    'lote_ingreso_id' => $linea['capa']->id,
                    'producto_id_producto' => $linea['detalle']->producto_id_producto,
                    'producto_presentacion_id' => $linea['presentacion']->id_producto_presentacion,
                    'producto_lote_id' => $linea['capa']->producto_lote_id,
                    'cantidad' => $linea['cantidad'],
                    'factor' => $linea['presentacion']->factor,
                    'cantidad_base' => $resultado['cantidad_base'],
                    'costo_unitario_base' => $resultado['costo_unitario_base'],
                    'costo_total' => $resultado['costo_total'],
                ]);

                $total = CantidadHelper::sumar($total, $resultado['costo_total'], 2);
            }

            $total = CantidadHelper::redondear($total, 2);
            $devolucion->update(['total' => $total]);

            if ($reembolsado) {
                $movimiento = $this->cajas->registrarDevolucionProveedor($caja, $devolucion, auth()->id());

                $devolucion->update([
                    'caja_id' => $caja->id_caja,
                    'caja_movimiento_id' => $movimiento->id_movimiento,
                ]);
            }

            return $devolucion->fresh([
                'proveedor',
                'ingreso',
                'user',
                'detalles.producto',
                'detalles.presentacion',
                'detalles.lote',
            ]);
        });
    }

    public function anular(DevolucionProveedor $devolucion, string $motivo): DevolucionProveedor
    {
        return DB::transaction(function () use ($devolucion, $motivo) {
            $devolucion = DevolucionProveedor::whereKey($devolucion->id_devolucion_proveedor)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $devolucion->estaRegistrada()) {
                throw new \RuntimeException('La devolución ya fue anulada.');
            }

            $caja = null;

            if ($devolucion->reembolsado_a_caja) {
                $caja = Caja::whereKey($devolucion->caja_id)->lockForUpdate()->first();

                if (! $caja || $caja->estado !== 'abierta') {
                    throw new \RuntimeException(
                        'La caja donde se registró el reembolso ya está cerrada. No se puede revertir automáticamente.'
                    );
                }
            }

            $this->inventario->revertirConsumos(
                'DevolucionProveedor',
                $devolucion->id_devolucion_proveedor,
                $devolucion->correlativo
            );

            if ($devolucion->reembolsado_a_caja && $caja) {
                $this->cajas->anularDevolucionProveedor($caja, $devolucion, auth()->id());
            }

            $devolucion->update([
                'estado' => DevolucionProveedor::ESTADO_ANULADA,
                'anulada_at' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            return $devolucion->fresh([
                'proveedor',
                'ingreso',
                'user',
                'detalles.producto',
                'detalles.presentacion',
                'detalles.lote',
            ]);
        });
    }

    private function resolverLineas(Ingreso $ingreso, Collection $lineas): array
    {
        return $lineas->map(function ($linea) use ($ingreso) {
            $detalle = DetalleIngreso::with('presentacion')
                ->where('ingreso_id_ingreso', $ingreso->id_ingreso)
                ->whereKey($linea['detalle_ingreso_id'])
                ->first();

            if (! $detalle) {
                throw new \RuntimeException('Una de las líneas no pertenece a la compra seleccionada.');
            }

            $capa = LoteIngreso::where('detalle_ingreso_id', $detalle->id_detalle_ingreso)->first();

            if (! $capa) {
                throw new \RuntimeException('La línea seleccionada no tiene una capa de lote disponible.');
            }

            $presentacion = $detalle->presentacion;

            if (! $presentacion) {
                throw new \RuntimeException('La presentación de la línea ya no existe.');
            }

            if (! CantidadHelper::esMultiplo((string) $linea['cantidad'], $presentacion->paso)) {
                throw new \RuntimeException(
                    "La cantidad debe ser múltiplo de {$presentacion->paso} para esta presentación."
                );
            }

            return [
                'detalle' => $detalle,
                'capa' => $capa,
                'presentacion' => $presentacion,
                'cantidad' => (string) $linea['cantidad'],
            ];
        })->all();
    }
}
