<?php

namespace App\Services;

use App\Helpers\CantidadHelper;
use App\Models\Caja;
use App\Models\Ingreso;
use App\Models\ProductoPresentacion;
use Illuminate\Support\Facades\DB;

class CompraService
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly CajaService $cajas
    ) {}

    public function crear(array $data): Ingreso
    {
        return DB::transaction(function () use ($data) {
            $pagadoDesdeCaja = filter_var($data['pagado_desde_caja'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $ingreso = Ingreso::create([
                'correlativo' => null,
                'estado' => Ingreso::ESTADO_BORRADOR,
                'proveedor_id_proveedor' => $data['proveedor_id_proveedor'],
                'fecha' => $data['fecha'] ?? today()->toDateString(),
                'tipo_documento' => $data['tipo_documento'],
                'numero_documento' => mb_strtoupper(trim($data['numero_documento'])),
                'observaciones' => $data['observaciones'] ?? null,
                'pagado_desde_caja' => $pagadoDesdeCaja,
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'referencia_pago' => $data['referencia_pago'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $this->reemplazarDetalles($ingreso, $data['productos']);
            $this->actualizarTotales($ingreso);

            return $ingreso->fresh(['proveedor', 'user', 'detalles.presentacion', 'detalles.producto']);
        });
    }

    public function actualizar(Ingreso $ingreso, array $data): Ingreso
    {
        return DB::transaction(function () use ($ingreso, $data) {
            $ingreso = Ingreso::whereKey($ingreso->id_ingreso)->lockForUpdate()->firstOrFail();

            if (! $ingreso->estaEnBorrador()) {
                throw new \RuntimeException('Solo se puede editar una compra en estado borrador.');
            }

            $pagadoDesdeCaja = filter_var($data['pagado_desde_caja'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $ingreso->update([
                'proveedor_id_proveedor' => $data['proveedor_id_proveedor'],
                'fecha' => $data['fecha'] ?? today()->toDateString(),
                'tipo_documento' => $data['tipo_documento'],
                'numero_documento' => mb_strtoupper(trim($data['numero_documento'])),
                'observaciones' => $data['observaciones'] ?? null,
                'pagado_desde_caja' => $pagadoDesdeCaja,
                'metodo_pago' => $data['metodo_pago'] ?? null,
                'referencia_pago' => $data['referencia_pago'] ?? null,
            ]);

            $this->reemplazarDetalles($ingreso, $data['productos']);
            $this->actualizarTotales($ingreso);

            return $ingreso->fresh(['proveedor', 'user', 'detalles.presentacion', 'detalles.producto']);
        });
    }

    public function eliminar(Ingreso $ingreso): void
    {
        DB::transaction(function () use ($ingreso) {
            $ingreso = Ingreso::whereKey($ingreso->id_ingreso)->lockForUpdate()->firstOrFail();

            if (! $ingreso->estaEnBorrador()) {
                throw new \RuntimeException('Solo se puede eliminar una compra en estado borrador. Use la anulación para compras recibidas.');
            }

            $ingreso->detalles()->delete();
            $ingreso->delete();
        });
    }

    public function recibir(Ingreso $ingreso, array $datos = []): Ingreso
    {
        return DB::transaction(function () use ($ingreso, $datos) {
            $ingreso = Ingreso::whereKey($ingreso->id_ingreso)->lockForUpdate()->firstOrFail();

            if (! $ingreso->estaEnBorrador()) {
                throw new \RuntimeException('Solo se puede recibir una compra en estado borrador.');
            }

            $pagadoDesdeCaja = array_key_exists('pagado_desde_caja', $datos)
                ? filter_var($datos['pagado_desde_caja'], FILTER_VALIDATE_BOOLEAN)
                : $ingreso->pagado_desde_caja;

            $metodoPago = $datos['metodo_pago'] ?? $ingreso->metodo_pago;

            if ($pagadoDesdeCaja && blank($metodoPago)) {
                throw new \RuntimeException('Seleccione el método de pago para registrar el egreso de caja.');
            }

            $caja = null;

            if ($pagadoDesdeCaja) {
                $caja = Caja::abierta()->lockForUpdate()->first();

                if (! $caja) {
                    throw new \RuntimeException('No hay una caja abierta. Abra la caja o registre el pago como externo.');
                }
            }

            $ingreso->update([
                'pagado_desde_caja' => $pagadoDesdeCaja,
                'metodo_pago' => $metodoPago,
                'referencia_pago' => $datos['referencia_pago'] ?? $ingreso->referencia_pago,
            ]);

            $correlativo = 'CMP-'.str_pad((string) $ingreso->id_ingreso, 8, '0', STR_PAD_LEFT);

            foreach ($ingreso->detalles()->with('presentacion.producto')->get() as $detalle) {
                if (! $detalle->presentacion) {
                    throw new \RuntimeException('Una de las presentaciones de la compra ya no existe.');
                }

                $capa = $this->inventario->ingresar(
                    $detalle->presentacion,
                    $detalle->cantidad,
                    (string) $detalle->lote_codigo,
                    $detalle->fecha_vencimiento->toDateString(),
                    $detalle->costo_unitario,
                    'Compra',
                    $ingreso->id_ingreso,
                    $correlativo,
                    'Recepción de compra',
                    $detalle->id_detalle_ingreso
                );

                $detalle->update([
                    'producto_lote_id' => $capa->producto_lote_id,
                    'lote_codigo' => $capa->lote->codigo_lote,
                    'fecha_vencimiento' => $capa->lote->fecha_vencimiento,
                    'costo_unitario_base' => $capa->costo_unitario_base,
                    'costo_total' => $capa->costo_total,
                ]);
            }

            $ingreso->correlativo = $correlativo;
            $ingreso->estado = Ingreso::ESTADO_RECIBIDA;
            $ingreso->fecha_recepcion = today()->toDateString();
            $ingreso->recepcion_user_id = auth()->id();

            if ($pagadoDesdeCaja) {
                $movimiento = $this->cajas->registrarCompra($caja, $ingreso, auth()->id());

                $ingreso->caja_id = $caja->id_caja;
                $ingreso->egreso_caja_id = $movimiento->id_movimiento;
            }

            $ingreso->save();

            return $ingreso->fresh([
                'proveedor',
                'user',
                'recepcionUser',
                'detalles.presentacion',
                'detalles.producto',
            ]);
        });
    }

    public function anular(Ingreso $ingreso, string $motivo): Ingreso
    {
        return DB::transaction(function () use ($ingreso, $motivo) {
            $ingreso = Ingreso::whereKey($ingreso->id_ingreso)->lockForUpdate()->firstOrFail();

            if (! $ingreso->estaRecibida()) {
                throw new \RuntimeException('Solo se puede anular una compra recibida.');
            }

            $caja = null;

            if ($ingreso->pagado_desde_caja) {
                $caja = Caja::whereKey($ingreso->caja_id)->lockForUpdate()->first();

                if (! $caja || $caja->estado !== 'abierta') {
                    throw new \RuntimeException(
                        'La caja donde se pagó esta compra ya está cerrada. No se puede revertir el egreso automáticamente.'
                    );
                }
            }

            $this->inventario->revertirIngreso($ingreso);

            if ($ingreso->pagado_desde_caja && $caja) {
                $this->cajas->anularCompra($caja, $ingreso, auth()->id());
            }

            $ingreso->update([
                'estado' => Ingreso::ESTADO_ANULADA,
                'anulada_at' => now(),
                'motivo_anulacion' => $motivo,
            ]);

            return $ingreso->fresh(['proveedor', 'user', 'detalles.presentacion', 'detalles.producto']);
        });
    }

    private function reemplazarDetalles(Ingreso $ingreso, array $productos): void
    {
        $ingreso->detalles()->delete();

        foreach ($productos as $linea) {
            $presentacion = ProductoPresentacion::with('producto')
                ->findOrFail($linea['producto_presentacion_id']);

            $cantidad = (string) $linea['cantidad'];
            $factor = (string) $presentacion->factor;
            $cantidadBase = CantidadHelper::aBase($cantidad, $factor);

            $costoPresentacion = filled($linea['costo_unitario'] ?? null)
                ? (string) $linea['costo_unitario']
                : CantidadHelper::multiplicar($presentacion->producto->precio_compra ?? 0, $factor, 4);

            $costoBase = bcdiv($costoPresentacion, $factor, 4);
            $subtotal = CantidadHelper::multiplicar($cantidad, $costoPresentacion, 2);

            $ingreso->detalles()->create([
                'producto_id_producto' => $presentacion->producto_id_producto,
                'producto_presentacion_id' => $presentacion->id_producto_presentacion,
                'lote_codigo' => mb_strtoupper(trim($linea['lote'])),
                'fecha_vencimiento' => $linea['fecha_vencimiento'],
                'cantidad' => $cantidad,
                'factor' => $factor,
                'cantidad_base' => $cantidadBase,
                'costo_unitario' => $costoPresentacion,
                'costo_unitario_base' => $costoBase,
                'costo_total' => $subtotal,
            ]);
        }
    }

    private function actualizarTotales(Ingreso $ingreso): void
    {
        $total = (string) $ingreso->detalles()->sum('costo_total');
        $total = CantidadHelper::redondear($total, 2);

        $ingreso->update([
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
