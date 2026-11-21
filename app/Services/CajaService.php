<?php

namespace App\Services;

use App\Helpers\CantidadHelper;
use App\Models\Atencion;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\DevolucionProveedor;
use App\Models\Ingreso;
use App\Models\MetodoPago;
use App\Models\Venta;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CajaService
{
    /**
     * Denominaciones usadas en el conteo físico (billetes y monedas).
     */
    public const DENOMINACIONES = ['200', '100', '50', '20', '10', '5', '2', '1', '0.5', '0.2', '0.1', '0.05'];

    public function abierta(bool $bloquear = false): ?Caja
    {
        $query = Caja::abierta();

        if ($bloquear) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Devuelve la caja abierta bloqueada. Debe llamarse dentro de una transacción.
     */
    public function abiertaParaOperar(): Caja
    {
        $caja = $this->abierta(true);

        if (! $caja) {
            throw new \RuntimeException('No hay una caja abierta.');
        }

        return $caja;
    }

    public function abrir(array $data, ?int $userId = null): Caja
    {
        return DB::transaction(function () use ($data, $userId) {
            if ($this->abierta(true)) {
                throw new \RuntimeException('Ya hay una caja abierta. Ciérrela antes de abrir una nueva.');
            }

            try {
                return Caja::create([
                    'monto_inicial' => CantidadHelper::redondear($data['monto_inicial'], 2),
                    'total_ingresos' => 0,
                    'total_ingresos_efectivo' => 0,
                    'total_ingresos_digital' => 0,
                    'total_egresos' => 0,
                    'total_egresos_efectivo' => 0,
                    'total_egresos_digital' => 0,
                    'observacion_apertura' => $data['observacion_apertura'] ?? null,
                    'estado' => 'abierta',
                    'apertura_user_id' => $userId ?? auth()->id(),
                    'apertura_at' => now(),
                ]);
            } catch (QueryException $e) {
                if ($this->esConflictoDeCajaUnica($e)) {
                    throw new \RuntimeException('Ya hay una caja abierta. Ciérrela antes de abrir una nueva.');
                }

                throw $e;
            }
        });
    }

    public function cerrar(Caja $caja, array $data, ?int $userId = null): Caja
    {
        return DB::transaction(function () use ($caja, $data, $userId) {
            $caja = Caja::whereKey($caja->id_caja)->lockForUpdate()->firstOrFail();

            if (! $caja->estaAbierta()) {
                throw new \RuntimeException('La caja ya está cerrada.');
            }

            $this->recalcularTotales($caja);

            $esperado = $this->efectivoEsperado($caja);
            $denominaciones = $this->normalizarDenominaciones($data['denominaciones'] ?? []);
            $contado = $denominaciones === []
                ? CantidadHelper::redondear($data['efectivo_contado'], 2)
                : $this->contarDenominaciones($denominaciones);

            if ($denominaciones !== []
                && CantidadHelper::comparar($contado, $data['efectivo_contado'], 2) !== 0) {
                throw new \RuntimeException(
                    'El total de las denominaciones no coincide con el efectivo contado. Revise el conteo.'
                );
            }

            $diferencia = CantidadHelper::restar($contado, $esperado, 2);

            if (CantidadHelper::comparar($diferencia, 0, 2) === 0) {
                $diferencia = '0.00';
            }

            $motivo = $data['motivo_diferencia'] ?? null;
            $confirmoCero = filter_var($data['confirmo_cero'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (CantidadHelper::comparar($contado, 0, 2) === 0 && ! $confirmoCero) {
                throw new \RuntimeException(
                    'El efectivo contado es S/ 0.00. Marque la confirmación de caja en cero para cerrar.'
                );
            }

            if (CantidadHelper::comparar($diferencia, 0, 2) !== 0) {
                if (blank($motivo) || ! array_key_exists($motivo, Caja::MOTIVOS_DIFERENCIA)) {
                    throw new \RuntimeException(
                        'El arqueo tiene una diferencia de S/ '.number_format((float) $diferencia, 2)
                        .'. Seleccione el motivo de la diferencia.'
                    );
                }

                if (blank($data['observacion_cierre'] ?? null)) {
                    throw new \RuntimeException(
                        'El arqueo tiene una diferencia de S/ '.number_format((float) $diferencia, 2)
                        .'. Debe registrar una observación que la justifique.'
                    );
                }
            } else {
                $motivo = null;
            }

            $caja->update([
                'estado' => 'cerrada',
                'efectivo_esperado' => $esperado,
                'efectivo_contado' => $contado,
                'diferencia' => $diferencia,
                'motivo_diferencia' => $motivo,
                'monto_final' => $contado,
                'arqueo_detalle' => $denominaciones === [] ? null : ['denominaciones' => $denominaciones],
                'observacion_cierre' => $data['observacion_cierre'] ?? null,
                'cierre_user_id' => $userId ?? auth()->id(),
                'arqueo_user_id' => $userId ?? auth()->id(),
                'cierre_at' => now(),
                'arqueo_at' => now(),
            ]);

            return $caja->fresh();
        });
    }

    public function registrarIngresoManual(Caja $caja, array $data, ?int $userId = null): CajaMovimiento
    {
        $metodo = $this->metodoPago($data['metodo_pago_id'] ?? null);

        return $this->registrarIngreso($caja, [
            'monto' => $data['monto'],
            'descripcion' => $data['descripcion'] ?? null,
            'referencia_tipo' => 'egreso_manual',
            'metodo_pago_id' => $metodo?->id_metodo_pago,
            'afecta_efectivo' => $metodo?->afecta_efectivo ?? true,
            'categoria' => $data['categoria'] ?? CajaMovimiento::CATEGORIA_OTRO_INGRESO,
            'naturaleza' => CajaMovimiento::naturalezaParaCategoria($data['categoria'] ?? null),
            'comprobante' => $data['comprobante'] ?? null,
        ], $userId);
    }

    public function registrarEgresoManual(Caja $caja, array $data, ?int $userId = null): CajaMovimiento
    {
        $metodo = $this->metodoPago($data['metodo_pago_id'] ?? null);

        return $this->registrarEgreso($caja, [
            'monto' => $data['monto'],
            'descripcion' => $data['descripcion'] ?? null,
            'referencia_tipo' => 'egreso_manual',
            'metodo_pago_id' => $metodo?->id_metodo_pago,
            'afecta_efectivo' => $metodo?->afecta_efectivo ?? true,
            'categoria' => $data['categoria'] ?? CajaMovimiento::CATEGORIA_OTRO_EGRESO,
            'naturaleza' => CajaMovimiento::naturalezaParaCategoria($data['categoria'] ?? null),
            'comprobante' => $data['comprobante'] ?? null,
        ], $userId);
    }

    /**
     * Registra un ingreso por cada método de pago de una venta o atención.
     * Una venta mixta genera varias filas, una por método.
     */
    public function registrarPagos(
        string $referenciaTipo,
        int $referenciaId,
        array $pagos,
        ?string $descripcion = null,
        ?int $userId = null
    ): void {
        $caja = $this->abiertaParaOperar();

        $this->registrarPagosEnCaja($caja, $referenciaTipo, $referenciaId, $pagos, $descripcion, $userId);
    }

    public function registrarPagosEnCaja(
        Caja $caja,
        string $referenciaTipo,
        int $referenciaId,
        array $pagos,
        ?string $descripcion = null,
        ?int $userId = null
    ): void {
        $caja = $this->bloquearCajaAbierta($caja);

        if ($pagos === []) {
            throw new \RuntimeException('Debe registrar al menos un método de pago.');
        }

        foreach ($pagos as $pago) {
            $metodo = $this->metodoPago($pago['metodo_pago_id'] ?? null);

            if (! $metodo || ! $metodo->estado) {
                throw new \RuntimeException('El método de pago seleccionado no está activo.');
            }

            $this->crearMovimiento($caja, [
                'tipo' => CajaMovimiento::TIPO_INGRESO,
                'monto' => $pago['monto'],
                'descripcion' => $descripcion,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'metodo_pago_id' => $metodo->id_metodo_pago,
                'afecta_efectivo' => $metodo->afecta_efectivo,
                'categoria' => $referenciaTipo === 'venta'
                    ? CajaMovimiento::CATEGORIA_VENTA
                    : CajaMovimiento::CATEGORIA_ATENCION,
                'naturaleza' => CajaMovimiento::NATURALEZA_OPERATIVO,
            ], $userId);
        }

        $this->recalcularTotales($caja);
    }

    /**
     * Anula el ingreso de una venta. Si la caja original ya está cerrada,
     * el reembolso se registra en la caja abierta actual, dejando intacta la histórica.
     */
    public function reembolsarVenta(Venta $venta, string $descripcion, ?int $userId = null): void
    {
        $this->reembolsarReferencia(
            'venta',
            $venta->id_venta,
            $descripcion,
            $userId,
            CajaMovimiento::CATEGORIA_ANULACION_VENTA
        );
    }

    /**
     * Anula el ingreso de una atención. Si la caja original ya está cerrada,
     * el reembolso se registra en la caja abierta actual.
     */
    public function reembolsarAtencion(Atencion $atencion, string $descripcion, ?int $userId = null): void
    {
        $this->reembolsarReferencia(
            'atencion',
            $atencion->id_atencion,
            $descripcion,
            $userId,
            CajaMovimiento::CATEGORIA_ANULACION_ATENCION
        );
    }

    private function reembolsarReferencia(
        string $referenciaTipo,
        int $referenciaId,
        string $descripcion,
        ?int $userId,
        string $categoria
    ): void {
        $movimientos = CajaMovimiento::with('caja')
            ->where('referencia_tipo', $referenciaTipo)
            ->where('referencia_id', $referenciaId)
            ->lockForUpdate()
            ->get();

        if ($movimientos->isEmpty()) {
            return;
        }

        $cajaOriginal = $movimientos->first()->caja;
        $destino = $cajaOriginal && $cajaOriginal->estaAbierta()
            ? $this->bloquearCajaAbierta($cajaOriginal)
            : $this->abiertaParaOperar();

        $this->revertirNeto($destino, $movimientos, $descripcion, $userId, $categoria);
    }

    public function registrarCompra(Caja $caja, Ingreso $ingreso, ?int $userId = null): CajaMovimiento
    {
        $metodo = $this->metodoPagoPorNombre($ingreso->metodo_pago);

        return $this->registrarEgreso($caja, [
            'monto' => $ingreso->total,
            'descripcion' => "Compra {$ingreso->correlativo} — {$ingreso->proveedor?->razon_social}",
            'referencia_tipo' => 'compra',
            'referencia_id' => $ingreso->id_ingreso,
            'metodo_pago_id' => $metodo?->id_metodo_pago,
            'afecta_efectivo' => $metodo?->afecta_efectivo ?? $this->afectaEfectivoPorNombre($ingreso->metodo_pago),
            'categoria' => CajaMovimiento::CATEGORIA_COMPRA,
            'naturaleza' => CajaMovimiento::NATURALEZA_OPERATIVO,
        ], $userId);
    }

    public function anularCompra(Caja $caja, Ingreso $ingreso, ?int $userId = null): CajaMovimiento
    {
        $metodo = $this->metodoPagoPorNombre($ingreso->metodo_pago);

        return $this->registrarIngreso($caja, [
            'monto' => $ingreso->total,
            'descripcion' => "Anulación de compra {$ingreso->correlativo}",
            'referencia_tipo' => 'compra',
            'referencia_id' => $ingreso->id_ingreso,
            'metodo_pago_id' => $metodo?->id_metodo_pago,
            'afecta_efectivo' => $metodo?->afecta_efectivo ?? $this->afectaEfectivoPorNombre($ingreso->metodo_pago),
            'categoria' => CajaMovimiento::CATEGORIA_COMPRA,
            'naturaleza' => CajaMovimiento::NATURALEZA_REVERSION,
            'movimiento_origen_id' => $ingreso->egreso_caja_id,
            'caja_origen_id' => $ingreso->caja_id,
        ], $userId);
    }

    public function registrarDevolucionProveedor(
        Caja $caja,
        DevolucionProveedor $devolucion,
        ?int $userId = null
    ): CajaMovimiento {
        $metodo = $this->metodoPagoPorNombre($devolucion->metodo_pago);

        return $this->registrarIngreso($caja, [
            'monto' => $devolucion->total,
            'descripcion' => "Devolución {$devolucion->correlativo} — {$devolucion->proveedor?->razon_social}",
            'referencia_tipo' => 'devolucion_compra',
            'referencia_id' => $devolucion->id_devolucion_proveedor,
            'metodo_pago_id' => $metodo?->id_metodo_pago,
            'afecta_efectivo' => $metodo?->afecta_efectivo ?? $this->afectaEfectivoPorNombre($devolucion->metodo_pago),
            'categoria' => CajaMovimiento::CATEGORIA_DEVOLUCION_COMPRA,
            'naturaleza' => CajaMovimiento::NATURALEZA_OPERATIVO,
        ], $userId);
    }

    public function anularDevolucionProveedor(
        Caja $caja,
        DevolucionProveedor $devolucion,
        ?int $userId = null
    ): CajaMovimiento {
        $metodo = $this->metodoPagoPorNombre($devolucion->metodo_pago);

        return $this->registrarEgreso($caja, [
            'monto' => $devolucion->total,
            'descripcion' => "Anulación de devolución {$devolucion->correlativo}",
            'referencia_tipo' => 'devolucion_compra',
            'referencia_id' => $devolucion->id_devolucion_proveedor,
            'metodo_pago_id' => $metodo?->id_metodo_pago,
            'afecta_efectivo' => $metodo?->afecta_efectivo ?? $this->afectaEfectivoPorNombre($devolucion->metodo_pago),
            'categoria' => CajaMovimiento::CATEGORIA_DEVOLUCION_COMPRA,
            'naturaleza' => CajaMovimiento::NATURALEZA_REVERSION,
            'movimiento_origen_id' => $devolucion->caja_movimiento_id,
            'caja_origen_id' => $devolucion->caja_id,
        ], $userId, false);
    }

    public function registrarIngreso(Caja $caja, array $data, ?int $userId = null): CajaMovimiento
    {
        $caja = $this->bloquearCajaAbierta($caja);
        $movimiento = $this->crearMovimiento($caja, array_merge($data, [
            'tipo' => CajaMovimiento::TIPO_INGRESO,
        ]), $userId);

        $this->recalcularTotales($caja);

        return $movimiento;
    }

    public function registrarEgreso(
        Caja $caja,
        array $data,
        ?int $userId = null,
        bool $validarSaldo = true
    ): CajaMovimiento {
        $caja = $this->bloquearCajaAbierta($caja);

        $afectaEfectivo = (bool) ($data['afecta_efectivo'] ?? true);
        $monto = CantidadHelper::redondear($data['monto'], 2);

        if ($validarSaldo && $afectaEfectivo) {
            $disponible = $this->efectivoDisponible($caja);

            if (CantidadHelper::comparar($monto, $disponible, 2) > 0) {
                throw new \RuntimeException(
                    'El egreso de S/ '.number_format((float) $monto, 2)
                    .' supera el efectivo disponible en caja (S/ '.number_format((float) $disponible, 2).').'
                );
            }
        }

        $movimiento = $this->crearMovimiento($caja, array_merge($data, [
            'tipo' => CajaMovimiento::TIPO_EGRESO,
            'monto' => $monto,
            'afecta_efectivo' => $afectaEfectivo,
        ]), $userId);

        $this->recalcularTotales($caja);

        return $movimiento;
    }

    public function totales(Caja $caja): array
    {
        $row = DB::table('caja_movimiento')
            ->where('caja_id_caja', $caja->id_caja)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END), 0) as ingresos")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END), 0) as egresos")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' AND afecta_efectivo = 1 THEN monto ELSE 0 END), 0) as ingresos_efectivo")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'egreso' AND afecta_efectivo = 1 THEN monto ELSE 0 END), 0) as egresos_efectivo")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' AND afecta_efectivo = 0 THEN monto ELSE 0 END), 0) as ingresos_digital")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'egreso' AND afecta_efectivo = 0 THEN monto ELSE 0 END), 0) as egresos_digital")
            ->first();

        $data = (array) $row;

        return [
            'ingresos' => CantidadHelper::redondear($data['ingresos'] ?? 0, 2),
            'egresos' => CantidadHelper::redondear($data['egresos'] ?? 0, 2),
            'ingresos_efectivo' => CantidadHelper::redondear($data['ingresos_efectivo'] ?? 0, 2),
            'egresos_efectivo' => CantidadHelper::redondear($data['egresos_efectivo'] ?? 0, 2),
            'ingresos_digital' => CantidadHelper::redondear($data['ingresos_digital'] ?? 0, 2),
            'egresos_digital' => CantidadHelper::redondear($data['egresos_digital'] ?? 0, 2),
        ];
    }

    public function recalcularTotales(Caja $caja): Caja
    {
        $totales = $this->totales($caja);

        $caja->forceFill([
            'total_ingresos' => $totales['ingresos'],
            'total_ingresos_efectivo' => $totales['ingresos_efectivo'],
            'total_ingresos_digital' => $totales['ingresos_digital'],
            'total_egresos' => $totales['egresos'],
            'total_egresos_efectivo' => $totales['egresos_efectivo'],
            'total_egresos_digital' => $totales['egresos_digital'],
        ])->save();

        return $caja;
    }

    public function efectivoEsperado(Caja $caja): string
    {
        return CantidadHelper::restar(
            CantidadHelper::sumar($caja->monto_inicial, $caja->total_ingresos_efectivo, 2),
            $caja->total_egresos_efectivo,
            2
        );
    }

    public function efectivoDisponible(Caja $caja): string
    {
        $totales = $this->totales($caja);

        return CantidadHelper::restar(
            CantidadHelper::sumar($caja->monto_inicial, $totales['ingresos_efectivo'], 2),
            $totales['egresos_efectivo'],
            2
        );
    }

    public function resumenPorMetodo(Caja $caja): Collection
    {
        return DB::table('caja_movimiento as cm')
            ->leftJoin('metodo_pago as mp', 'mp.id_metodo_pago', '=', 'cm.metodo_pago_id')
            ->where('cm.caja_id_caja', $caja->id_caja)
            ->selectRaw("COALESCE(mp.nombre, CASE WHEN cm.afecta_efectivo = 1 THEN 'Efectivo' ELSE 'Sin método' END) as metodo")
            ->selectRaw('MAX(cm.afecta_efectivo) as afecta_efectivo')
            ->selectRaw("SUM(CASE WHEN cm.tipo = 'ingreso' THEN cm.monto ELSE 0 END) as ingresos")
            ->selectRaw("SUM(CASE WHEN cm.tipo = 'egreso' THEN cm.monto ELSE 0 END) as egresos")
            ->groupByRaw("COALESCE(mp.nombre, CASE WHEN cm.afecta_efectivo = 1 THEN 'Efectivo' ELSE 'Sin método' END)")
            ->orderBy('metodo')
            ->get();
    }

    public function contarDenominaciones(array $denominaciones): string
    {
        $mapa = [];

        foreach ($denominaciones as $valor => $cantidad) {
            $mapa[(string) (float) $valor] = (int) $cantidad;
        }

        $total = '0.00';

        foreach (self::DENOMINACIONES as $valor) {
            $cantidad = $mapa[(string) (float) $valor] ?? 0;

            if ($cantidad <= 0) {
                continue;
            }

            $subtotal = CantidadHelper::multiplicar($cantidad, $valor, 2);
            $total = CantidadHelper::sumar($total, $subtotal, 2);
        }

        return $total;
    }

    public function metodoPagoPorNombre(?string $nombre): ?MetodoPago
    {
        if (blank($nombre)) {
            return null;
        }

        return MetodoPago::whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($nombre))])->first();
    }

    public function afectaEfectivoPorNombre(?string $nombre): bool
    {
        if (blank($nombre)) {
            return true;
        }

        return mb_strtolower(trim($nombre)) === 'efectivo';
    }

    private function bloquearCajaAbierta(Caja $caja): Caja
    {
        $bloqueada = Caja::whereKey($caja->id_caja)->lockForUpdate()->first();

        if (! $bloqueada || ! $bloqueada->estaAbierta()) {
            throw new \RuntimeException('La caja ya está cerrada.');
        }

        return $bloqueada;
    }

    private function metodoPago(int|string|null $id): ?MetodoPago
    {
        if (blank($id)) {
            return null;
        }

        return MetodoPago::find($id);
    }

    private function revertirNeto(
        Caja $caja,
        Collection $movimientos,
        string $descripcion,
        ?int $userId,
        string $categoria
    ): void {
        $netos = [];

        foreach ($movimientos as $movimiento) {
            $llave = ($movimiento->metodo_pago_id ?? 'null').'|'.($movimiento->afecta_efectivo ? '1' : '0');

            if (! isset($netos[$llave])) {
                $netos[$llave] = [
                    'metodo_pago_id' => $movimiento->metodo_pago_id,
                    'afecta_efectivo' => $movimiento->afecta_efectivo,
                    'monto' => '0.00',
                    'origen_id' => $movimiento->id_movimiento,
                    'origen_caja_id' => $movimiento->caja_id_caja,
                ];
            }

            $netos[$llave]['monto'] = $movimiento->tipo === CajaMovimiento::TIPO_INGRESO
                ? CantidadHelper::sumar($netos[$llave]['monto'], $movimiento->monto, 2)
                : CantidadHelper::restar($netos[$llave]['monto'], $movimiento->monto, 2);
        }

        $egresosEfectivo = '0.00';

        foreach ($netos as $neto) {
            if ($neto['afecta_efectivo'] && CantidadHelper::comparar($neto['monto'], 0, 2) > 0) {
                $egresosEfectivo = CantidadHelper::sumar($egresosEfectivo, $neto['monto'], 2);
            }
        }

        $disponible = $this->efectivoDisponible($caja);

        if (CantidadHelper::comparar($egresosEfectivo, $disponible, 2) > 0) {
            throw new \RuntimeException(
                'La reversión en efectivo (S/ '.number_format((float) $egresosEfectivo, 2)
                .') supera el efectivo disponible en caja (S/ '.number_format((float) $disponible, 2).').'
            );
        }

        foreach ($netos as $neto) {
            $comparacion = CantidadHelper::comparar($neto['monto'], 0, 2);

            if ($comparacion === 0) {
                continue;
            }

            $this->crearMovimiento($caja, [
                'tipo' => $comparacion > 0 ? CajaMovimiento::TIPO_EGRESO : CajaMovimiento::TIPO_INGRESO,
                'monto' => ltrim($neto['monto'], '-'),
                'descripcion' => $descripcion,
                'referencia_tipo' => $movimientos->first()->referencia_tipo,
                'referencia_id' => $movimientos->first()->referencia_id,
                'metodo_pago_id' => $neto['metodo_pago_id'],
                'afecta_efectivo' => $neto['afecta_efectivo'],
                'categoria' => $categoria,
                'naturaleza' => CajaMovimiento::NATURALEZA_REVERSION,
                'movimiento_origen_id' => $neto['origen_id'],
                'caja_origen_id' => $neto['origen_caja_id'],
            ], $userId);
        }

        $this->recalcularTotales($caja);
    }

    private function crearMovimiento(Caja $caja, array $data, ?int $userId = null): CajaMovimiento
    {
        return CajaMovimiento::create([
            'caja_id_caja' => $caja->id_caja,
            'tipo' => $data['tipo'],
            'monto' => CantidadHelper::redondear($data['monto'], 2),
            'descripcion' => $data['descripcion'] ?? null,
            'referencia_tipo' => $data['referencia_tipo'] ?? 'egreso_manual',
            'referencia_id' => $data['referencia_id'] ?? null,
            'metodo_pago_id' => $data['metodo_pago_id'] ?? null,
            'afecta_efectivo' => $data['afecta_efectivo'] ?? true,
            'categoria' => $data['categoria'] ?? CajaMovimiento::CATEGORIA_OTRO_EGRESO,
            'naturaleza' => $data['naturaleza'] ?? CajaMovimiento::NATURALEZA_OPERATIVO,
            'movimiento_origen_id' => $data['movimiento_origen_id'] ?? null,
            'caja_origen_id' => $data['caja_origen_id'] ?? null,
            'comprobante' => $data['comprobante'] ?? null,
            'user_id' => $userId ?? auth()->id(),
            'created_at' => $data['created_at'] ?? now(),
        ]);
    }

    private function normalizarDenominaciones(array $denominaciones): array
    {
        $limpias = [];

        foreach ($denominaciones as $valor => $cantidad) {
            $cantidad = (int) $cantidad;

            if ($cantidad > 0) {
                $limpias[(string) $valor] = $cantidad;
            }
        }

        return $limpias;
    }

    private function esConflictoDeCajaUnica(QueryException $e): bool
    {
        $mensaje = mb_strtolower($e->getMessage());

        return str_contains($mensaje, 'unique')
            || str_contains($mensaje, 'duplicate')
            || str_contains($mensaje, 'abierta_unica');
    }
}
