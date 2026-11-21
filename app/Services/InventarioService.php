<?php

namespace App\Services;

use App\Helpers\CantidadHelper;
use App\Helpers\KardexHelper;
use App\Models\Ingreso;
use App\Models\LoteConsumo;
use App\Models\LoteIngreso;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\ProductoPresentacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    public function ingresar(
        ProductoPresentacion $presentacion,
        string|int|float $cantidad,
        string $codigoLote,
        string $fechaVencimiento,
        string|int|float|null $costoUnitarioPresentacion,
        string $referenciaTipo,
        int $referenciaId,
        string $referenciaCodigo,
        ?string $observacion = null,
        ?int $detalleIngresoId = null
    ): LoteIngreso {
        $cantidadBase = CantidadHelper::aBase($cantidad, $presentacion->factor);

        if (CantidadHelper::comparar($cantidadBase, 0) <= 0) {
            throw new \RuntimeException('La cantidad debe ser mayor que cero.');
        }

        $codigoLote = mb_strtoupper(trim($codigoLote));

        if ($codigoLote === '') {
            throw new \RuntimeException('El código de lote es obligatorio.');
        }

        $vencimiento = Carbon::parse($fechaVencimiento)->startOfDay();

        if ($vencimiento->lt(today())) {
            throw new \RuntimeException('No se puede ingresar un lote con fecha de vencimiento pasada.');
        }

        $factor = (string) $presentacion->factor;

        $costoUnitarioBase = $costoUnitarioPresentacion !== null
            ? bcdiv((string) $costoUnitarioPresentacion, $factor, 4)
            : (string) ($presentacion->producto->precio_compra ?? 0);

        $costoTotal = $costoUnitarioPresentacion !== null
            ? CantidadHelper::multiplicar($cantidad, $costoUnitarioPresentacion, 2)
            : CantidadHelper::multiplicar($cantidadBase, $costoUnitarioBase, 2);

        return DB::transaction(function () use (
            $presentacion,
            $cantidad,
            $cantidadBase,
            $codigoLote,
            $vencimiento,
            $costoUnitarioBase,
            $costoTotal,
            $referenciaTipo,
            $referenciaId,
            $referenciaCodigo,
            $observacion,
            $detalleIngresoId
        ) {
            $producto = Producto::where('id_producto', $presentacion->producto_id_producto)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validarStockContraLotes($producto);

            $lote = ProductoLote::where('producto_id', $producto->id_producto)
                ->where('codigo_lote', $codigoLote)
                ->whereDate('fecha_vencimiento', $vencimiento->toDateString())
                ->lockForUpdate()
                ->first();

            if ($lote && in_array($lote->estado, ['baja', 'vencido'], true)) {
                throw new \RuntimeException("El lote {$codigoLote} está dado de baja o vencido.");
            }

            if (! $lote) {
                $lote = ProductoLote::create([
                    'producto_id' => $producto->id_producto,
                    'codigo_lote' => $codigoLote,
                    'fecha_vencimiento' => $vencimiento->toDateString(),
                    'estado' => 'disponible',
                    'cantidad_inicial_base' => 0,
                    'cantidad_disponible_base' => 0,
                    'es_inicial' => false,
                ]);
            }

            $capa = LoteIngreso::create([
                'producto_lote_id' => $lote->id,
                'ingreso_id' => in_array($referenciaTipo, ['Ingreso', 'Compra'], true) ? $referenciaId : null,
                'detalle_ingreso_id' => $detalleIngresoId,
                'producto_presentacion_id' => $presentacion->id_producto_presentacion,
                'cantidad_presentacion' => $cantidad,
                'factor' => $presentacion->factor,
                'cantidad_base' => $cantidadBase,
                'cantidad_disponible_base' => $cantidadBase,
                'costo_unitario_base' => $costoUnitarioBase,
                'costo_total' => $costoTotal,
            ]);

            $lote->cantidad_inicial_base = CantidadHelper::sumar($lote->cantidad_inicial_base, $cantidadBase);
            $lote->cantidad_disponible_base = CantidadHelper::sumar($lote->cantidad_disponible_base, $cantidadBase);
            $lote->estado = 'disponible';
            $lote->save();

            $producto->stock = CantidadHelper::sumar($producto->stock, $cantidadBase);
            $producto->inventario = $producto->stock;
            $producto->save();

            KardexHelper::registrar(
                $producto,
                'INGRESO',
                $referenciaTipo,
                $referenciaId,
                $referenciaCodigo,
                $cantidadBase,
                $observacion,
                $presentacion,
                $cantidad,
                $capa
            );

            return $capa;
        });
    }

    public function consumir(
        ProductoPresentacion $presentacion,
        string|int|float $cantidad,
        string $tipoMovimiento,
        string $referenciaTipo,
        int $referenciaId,
        string $referenciaCodigo,
        ?string $observacion = null
    ): array {
        $cantidadBase = CantidadHelper::aBase($cantidad, $presentacion->factor);

        if (CantidadHelper::comparar($cantidadBase, 0) <= 0) {
            throw new \RuntimeException('La cantidad debe ser mayor que cero.');
        }

        return DB::transaction(function () use (
            $presentacion,
            $cantidadBase,
            $tipoMovimiento,
            $referenciaTipo,
            $referenciaId,
            $referenciaCodigo,
            $observacion
        ) {
            $producto = Producto::where('id_producto', $presentacion->producto_id_producto)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validarStockContraLotes($producto);

            $capas = LoteIngreso::query()
                ->join('producto_lote as pl', 'pl.id', '=', 'lote_ingreso.producto_lote_id')
                ->where('pl.producto_id', $producto->id_producto)
                ->where('pl.estado', 'disponible')
                ->whereDate('pl.fecha_vencimiento', '>=', today()->toDateString())
                ->where('lote_ingreso.cantidad_disponible_base', '>', 0)
                ->orderBy('pl.fecha_vencimiento')
                ->orderBy('lote_ingreso.created_at')
                ->orderBy('lote_ingreso.id')
                ->select('lote_ingreso.*')
                ->lockForUpdate()
                ->get()
                ->load('lote');

            $disponible = '0.000';
            foreach ($capas as $capa) {
                $disponible = CantidadHelper::sumar($disponible, $capa->cantidad_disponible_base);
            }

            if (CantidadHelper::comparar($disponible, $cantidadBase) < 0) {
                throw new \RuntimeException(
                    "\"{$producto->nombre}\" — Stock por lotes insuficiente. Disponible: "
                    .CantidadHelper::formatear($disponible)
                    .', solicitado: '.CantidadHelper::formatear($cantidadBase).'.'
                );
            }

            $restante = $cantidadBase;
            $costoTotal = '0.00';
            $consumos = [];

            foreach ($capas as $capa) {
                if (CantidadHelper::comparar($restante, 0) <= 0) {
                    break;
                }

                $tomar = CantidadHelper::comparar($capa->cantidad_disponible_base, $restante) <= 0
                    ? (string) $capa->cantidad_disponible_base
                    : $restante;

                $capa->cantidad_disponible_base = CantidadHelper::restar($capa->cantidad_disponible_base, $tomar);
                $capa->save();

                $lote = ProductoLote::where('id', $capa->producto_lote_id)->lockForUpdate()->firstOrFail();
                $lote->cantidad_disponible_base = CantidadHelper::restar($lote->cantidad_disponible_base, $tomar);

                if (CantidadHelper::comparar($lote->cantidad_disponible_base, 0) <= 0) {
                    $lote->estado = 'agotado';
                }

                $lote->save();

                $producto->stock = CantidadHelper::restar($producto->stock, $tomar);
                $producto->save();

                $costoUnitarioBase = (string) ($capa->costo_unitario_base ?? 0);
                $costoConsumo = CantidadHelper::multiplicar($tomar, $costoUnitarioBase, 2);
                $costoTotal = CantidadHelper::sumar($costoTotal, $costoConsumo, 2);

                $consumo = LoteConsumo::create([
                    'lote_ingreso_id' => $capa->id,
                    'producto_lote_id' => $lote->id,
                    'referencia_tipo' => $referenciaTipo,
                    'referencia_id' => $referenciaId,
                    'producto_presentacion_id' => $presentacion->id_producto_presentacion,
                    'cantidad_base' => $tomar,
                    'costo_unitario_base' => $costoUnitarioBase,
                    'costo_total' => $costoConsumo,
                    'user_id' => auth()->id(),
                ]);

                KardexHelper::registrar(
                    $producto,
                    $tipoMovimiento,
                    $referenciaTipo,
                    $referenciaId,
                    $referenciaCodigo,
                    '-'.$tomar,
                    $observacion,
                    $presentacion,
                    bcdiv($tomar, (string) $presentacion->factor, 3),
                    $capa
                );

                $consumos[] = $consumo;
                $restante = CantidadHelper::restar($restante, $tomar);
            }

            return [
                'costo_total' => $costoTotal,
                'consumos' => $consumos,
                'cantidad_base' => $cantidadBase,
            ];
        });
    }

    public function consumirDeCapa(
        LoteIngreso $capa,
        ProductoPresentacion $presentacion,
        string|int|float $cantidad,
        string $tipoMovimiento,
        string $referenciaTipo,
        int $referenciaId,
        string $referenciaCodigo,
        ?string $observacion = null
    ): array {
        $cantidadBase = CantidadHelper::aBase($cantidad, $presentacion->factor);

        if (CantidadHelper::comparar($cantidadBase, 0) <= 0) {
            throw new \RuntimeException('La cantidad debe ser mayor que cero.');
        }

        return DB::transaction(function () use (
            $capa,
            $presentacion,
            $cantidadBase,
            $tipoMovimiento,
            $referenciaTipo,
            $referenciaId,
            $referenciaCodigo,
            $observacion
        ) {
            $capa = LoteIngreso::whereKey($capa->id)->lockForUpdate()->firstOrFail();
            $lote = ProductoLote::whereKey($capa->producto_lote_id)->lockForUpdate()->firstOrFail();

            if ((int) $lote->producto_id !== (int) $presentacion->producto_id_producto) {
                throw new \RuntimeException('El lote no pertenece al producto seleccionado.');
            }

            if (CantidadHelper::comparar($capa->cantidad_disponible_base, $cantidadBase) < 0) {
                throw new \RuntimeException(
                    'No hay suficiente cantidad disponible en el lote. Disponible: '
                    .CantidadHelper::formatear($capa->cantidad_disponible_base)
                    .', solicitado: '.CantidadHelper::formatear($cantidadBase).'.'
                );
            }

            $capa->cantidad_disponible_base = CantidadHelper::restar(
                $capa->cantidad_disponible_base,
                $cantidadBase
            );
            $capa->save();

            $lote->cantidad_disponible_base = CantidadHelper::restar(
                $lote->cantidad_disponible_base,
                $cantidadBase
            );

            if (CantidadHelper::comparar($lote->cantidad_disponible_base, 0) <= 0) {
                $lote->estado = 'agotado';
            }

            $lote->save();

            $producto = Producto::where('id_producto', $lote->producto_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (CantidadHelper::comparar($producto->stock, $cantidadBase) < 0) {
                throw new \RuntimeException('El stock del producto es menor que la cantidad a devolver.');
            }

            $producto->stock = CantidadHelper::restar($producto->stock, $cantidadBase);
            $producto->save();

            $costoUnitarioBase = (string) ($capa->costo_unitario_base ?? 0);
            $costoTotal = CantidadHelper::multiplicar($cantidadBase, $costoUnitarioBase, 2);

            LoteConsumo::create([
                'lote_ingreso_id' => $capa->id,
                'producto_lote_id' => $lote->id,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'producto_presentacion_id' => $presentacion->id_producto_presentacion,
                'cantidad_base' => $cantidadBase,
                'costo_unitario_base' => $costoUnitarioBase,
                'costo_total' => $costoTotal,
                'user_id' => auth()->id(),
            ]);

            KardexHelper::registrar(
                $producto,
                $tipoMovimiento,
                $referenciaTipo,
                $referenciaId,
                $referenciaCodigo,
                '-'.$cantidadBase,
                $observacion,
                $presentacion,
                bcdiv($cantidadBase, (string) $presentacion->factor, 3),
                $capa
            );

            return [
                'costo_total' => $costoTotal,
                'cantidad_base' => $cantidadBase,
                'costo_unitario_base' => $costoUnitarioBase,
            ];
        });
    }

    public function ajustarEntradaLote(
        ProductoLote $lote,
        string|int|float $cantidadBase,
        string $referenciaTipo,
        int $referenciaId,
        string $referenciaCodigo,
        ?string $observacion = null
    ): array {
        $cantidadBase = bcadd((string) $cantidadBase, '0', 3);

        if (CantidadHelper::comparar($cantidadBase, 0) <= 0) {
            throw new \RuntimeException('La cantidad a sumar debe ser mayor que cero.');
        }

        return DB::transaction(function () use (
            $lote,
            $cantidadBase,
            $referenciaTipo,
            $referenciaId,
            $referenciaCodigo,
            $observacion
        ) {
            $lote = ProductoLote::whereKey($lote->id)->lockForUpdate()->firstOrFail();

            if (in_array($lote->estado, ['baja', 'vencido'], true)) {
                throw new \RuntimeException('No se puede aumentar stock de un lote dado de baja o vencido.');
            }

            $producto = Producto::where('id_producto', $lote->producto_id)
                ->lockForUpdate()
                ->firstOrFail();

            $presentacion = $producto->presentacionBase
                ?? $producto->presentaciones()->where('activo', true)->first();

            if (! $presentacion) {
                throw new \RuntimeException("El producto \"{$producto->nombre}\" no tiene una presentación para ajustar.");
            }

            $ultimaCapa = LoteIngreso::where('producto_lote_id', $lote->id)->orderByDesc('id')->first();
            $costoUnitarioBase = (string) ($ultimaCapa?->costo_unitario_base ?? $producto->precio_compra ?? 0);
            $factor = (string) $presentacion->factor;
            $cantidadPresentacion = bcdiv($cantidadBase, $factor, 3);
            $costoTotal = CantidadHelper::multiplicar($cantidadBase, $costoUnitarioBase, 2);

            $capa = LoteIngreso::create([
                'producto_lote_id' => $lote->id,
                'ingreso_id' => null,
                'detalle_ingreso_id' => null,
                'producto_presentacion_id' => $presentacion->id_producto_presentacion,
                'cantidad_presentacion' => $cantidadPresentacion,
                'factor' => $factor,
                'cantidad_base' => $cantidadBase,
                'cantidad_disponible_base' => $cantidadBase,
                'costo_unitario_base' => $costoUnitarioBase,
                'costo_total' => $costoTotal,
            ]);

            $lote->cantidad_inicial_base = CantidadHelper::sumar($lote->cantidad_inicial_base, $cantidadBase);
            $lote->cantidad_disponible_base = CantidadHelper::sumar($lote->cantidad_disponible_base, $cantidadBase);
            $lote->estado = 'disponible';
            $lote->save();

            $producto->stock = CantidadHelper::sumar($producto->stock, $cantidadBase);
            $producto->inventario = $producto->stock;
            $producto->save();

            KardexHelper::registrar(
                $producto,
                'AJUSTE',
                $referenciaTipo,
                $referenciaId,
                $referenciaCodigo,
                $cantidadBase,
                $observacion,
                $presentacion,
                $cantidadPresentacion,
                $capa
            );

            return [
                'cantidad_base' => $cantidadBase,
                'costo_unitario_base' => $costoUnitarioBase,
                'costo_total' => $costoTotal,
                'producto_presentacion_id' => $presentacion->id_producto_presentacion,
                'lote_ingreso_id' => $capa->id,
            ];
        });
    }

    public function ajustarSalidaLote(
        ProductoLote $lote,
        string|int|float $cantidadBase,
        string $referenciaTipo,
        int $referenciaId,
        string $referenciaCodigo,
        ?string $observacion = null
    ): array {
        $cantidadBase = bcadd((string) $cantidadBase, '0', 3);

        if (CantidadHelper::comparar($cantidadBase, 0) <= 0) {
            throw new \RuntimeException('La cantidad a restar debe ser mayor que cero.');
        }

        return DB::transaction(function () use (
            $lote,
            $cantidadBase,
            $referenciaTipo,
            $referenciaId,
            $referenciaCodigo,
            $observacion
        ) {
            $lote = ProductoLote::whereKey($lote->id)->lockForUpdate()->firstOrFail();

            if ($lote->estado === 'baja') {
                throw new \RuntimeException('No se puede ajustar un lote dado de baja.');
            }

            $producto = Producto::where('id_producto', $lote->producto_id)
                ->lockForUpdate()
                ->firstOrFail();

            $capas = LoteIngreso::where('producto_lote_id', $lote->id)
                ->where('cantidad_disponible_base', '>', 0)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $disponible = '0.000';
            foreach ($capas as $capa) {
                $disponible = CantidadHelper::sumar($disponible, $capa->cantidad_disponible_base);
            }

            if (CantidadHelper::comparar($disponible, $cantidadBase) < 0) {
                throw new \RuntimeException(
                    'No hay suficiente cantidad en el lote. Disponible: '
                    .CantidadHelper::formatear($disponible)
                    .', solicitado: '.CantidadHelper::formatear($cantidadBase).'.'
                );
            }

            $restante = $cantidadBase;
            $costoTotal = '0.00';
            $costoUnitarioBase = '0.0000';
            $primeraCapa = null;

            foreach ($capas as $capa) {
                if (CantidadHelper::comparar($restante, 0) <= 0) {
                    break;
                }

                $tomar = CantidadHelper::comparar($capa->cantidad_disponible_base, $restante) <= 0
                    ? (string) $capa->cantidad_disponible_base
                    : $restante;

                $capa->cantidad_disponible_base = CantidadHelper::restar($capa->cantidad_disponible_base, $tomar);
                $capa->save();

                $lote->cantidad_disponible_base = CantidadHelper::restar($lote->cantidad_disponible_base, $tomar);
                $lote->save();

                $producto->stock = CantidadHelper::restar($producto->stock, $tomar);
                $producto->inventario = $producto->stock;
                $producto->save();

                $costoUnitarioBase = (string) ($capa->costo_unitario_base ?? 0);
                $costoTotal = CantidadHelper::sumar(
                    $costoTotal,
                    CantidadHelper::multiplicar($tomar, $costoUnitarioBase, 2),
                    2
                );

                LoteConsumo::create([
                    'lote_ingreso_id' => $capa->id,
                    'producto_lote_id' => $lote->id,
                    'referencia_tipo' => $referenciaTipo,
                    'referencia_id' => $referenciaId,
                    'producto_presentacion_id' => $capa->producto_presentacion_id,
                    'cantidad_base' => $tomar,
                    'costo_unitario_base' => $costoUnitarioBase,
                    'costo_total' => CantidadHelper::multiplicar($tomar, $costoUnitarioBase, 2),
                    'user_id' => auth()->id(),
                ]);

                KardexHelper::registrar(
                    $producto,
                    'AJUSTE',
                    $referenciaTipo,
                    $referenciaId,
                    $referenciaCodigo,
                    '-'.$tomar,
                    $observacion,
                    $capa->presentacion,
                    bcdiv($tomar, (string) $capa->factor, 3),
                    $capa
                );

                $primeraCapa ??= $capa;
                $restante = CantidadHelper::restar($restante, $tomar);
            }

            if (CantidadHelper::comparar($lote->cantidad_disponible_base, 0) <= 0) {
                $lote->estado = 'agotado';
                $lote->save();
            }

            return [
                'cantidad_base' => '-'.$cantidadBase,
                'costo_unitario_base' => $costoUnitarioBase,
                'costo_total' => '-'.$costoTotal,
                'producto_presentacion_id' => $primeraCapa?->producto_presentacion_id,
                'lote_ingreso_id' => null,
            ];
        });
    }

    public function revertirConsumos(
        string $referenciaTipo,
        int $referenciaId,
        ?string $referenciaCodigo = null
    ): void {
        DB::transaction(function () use ($referenciaTipo, $referenciaId, $referenciaCodigo) {
            $consumos = LoteConsumo::with(['capa.lote', 'presentacion'])
                ->where('referencia_tipo', $referenciaTipo)
                ->where('referencia_id', $referenciaId)
                ->lockForUpdate()
                ->get();

            foreach ($consumos as $consumo) {
                $capa = LoteIngreso::whereKey($consumo->lote_ingreso_id)->lockForUpdate()->firstOrFail();
                $lote = ProductoLote::whereKey($consumo->producto_lote_id)->lockForUpdate()->firstOrFail();

                $capa->cantidad_disponible_base = CantidadHelper::sumar(
                    $capa->cantidad_disponible_base,
                    $consumo->cantidad_base
                );
                $capa->save();

                $lote->cantidad_disponible_base = CantidadHelper::sumar(
                    $lote->cantidad_disponible_base,
                    $consumo->cantidad_base
                );

                if ($lote->estado === 'agotado' && CantidadHelper::comparar($lote->cantidad_disponible_base, 0) > 0) {
                    $lote->estado = 'disponible';
                }

                $lote->save();

                $producto = Producto::where('id_producto', $lote->producto_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $producto->stock = CantidadHelper::sumar($producto->stock, $consumo->cantidad_base);
                $producto->save();

                $presentacion = $consumo->presentacion;
                $factor = (string) ($presentacion?->factor ?? $capa->factor ?? 1);

                KardexHelper::registrar(
                    $producto,
                    'REVERSION',
                    $referenciaTipo,
                    $referenciaId,
                    $referenciaCodigo ?? 'REV-'.$referenciaId,
                    $consumo->cantidad_base,
                    'Reversión de '.$referenciaTipo,
                    $presentacion,
                    bcdiv((string) $consumo->cantidad_base, $factor, 3),
                    $capa
                );

                $consumo->delete();
            }
        });
    }

    public function revertirIngreso(Ingreso $ingreso): void
    {
        DB::transaction(function () use ($ingreso) {
            $capas = LoteIngreso::with('lote')
                ->where('ingreso_id', $ingreso->id_ingreso)
                ->lockForUpdate()
                ->get();

            if ($capas->isEmpty()) {
                return;
            }

            $ids = $capas->pluck('id');

            $tieneConsumo = LoteConsumo::whereIn('lote_ingreso_id', $ids)->exists()
                || $capas->contains(fn ($capa) => CantidadHelper::comparar(
                    $capa->cantidad_disponible_base,
                    $capa->cantidad_base
                ) !== 0);

            if ($tieneConsumo) {
                throw new \RuntimeException(
                    'No se puede editar ni eliminar este ingreso porque parte de sus lotes ya fue consumida. Registre un ajuste o una devolución.'
                );
            }

            foreach ($capas as $capa) {
                $lote = $capa->lote;
                $lote->cantidad_inicial_base = CantidadHelper::restar($lote->cantidad_inicial_base, $capa->cantidad_base);
                $lote->cantidad_disponible_base = CantidadHelper::restar($lote->cantidad_disponible_base, $capa->cantidad_base);
                $lote->save();

                $producto = Producto::where('id_producto', $lote->producto_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $producto->stock = CantidadHelper::restar($producto->stock, $capa->cantidad_base);
                $producto->inventario = $producto->stock;
                $producto->save();

                $factor = (string) ($capa->factor ?? 1);

                KardexHelper::registrar(
                    $producto,
                    'REVERSION',
                    'Compra',
                    $ingreso->id_ingreso,
                    $ingreso->correlativo ?? 'CMP-'.str_pad((string) $ingreso->id_ingreso, 8, '0', STR_PAD_LEFT),
                    '-'.$capa->cantidad_base,
                    'Anulación de compra',
                    $capa->presentacion,
                    bcdiv('-'.$capa->cantidad_base, $factor, 3),
                    $capa
                );

                $capa->delete();
            }
        });
    }

    public function darDeBaja(ProductoLote $lote, string $motivo = 'Baja por vencimiento'): void
    {
        DB::transaction(function () use ($lote, $motivo) {
            $lote = ProductoLote::where('id', $lote->id)->lockForUpdate()->firstOrFail();

            if (CantidadHelper::comparar($lote->cantidad_disponible_base, 0) <= 0) {
                return;
            }

            $capas = LoteIngreso::where('producto_lote_id', $lote->id)
                ->where('cantidad_disponible_base', '>', 0)
                ->lockForUpdate()
                ->get();

            foreach ($capas as $capa) {
                $cantidad = (string) $capa->cantidad_disponible_base;

                $capa->cantidad_disponible_base = 0;
                $capa->save();

                LoteConsumo::create([
                    'lote_ingreso_id' => $capa->id,
                    'producto_lote_id' => $lote->id,
                    'referencia_tipo' => 'Baja',
                    'referencia_id' => $lote->id,
                    'producto_presentacion_id' => $capa->producto_presentacion_id,
                    'cantidad_base' => $cantidad,
                    'costo_unitario_base' => $capa->costo_unitario_base,
                    'costo_total' => $capa->costo_total,
                    'user_id' => auth()->id(),
                ]);

                $producto = Producto::where('id_producto', $lote->producto_id)->lockForUpdate()->firstOrFail();
                $producto->stock = CantidadHelper::restar($producto->stock, $cantidad);
                $producto->save();

                KardexHelper::registrar(
                    $producto,
                    'BAJA',
                    'Baja',
                    $lote->id,
                    $lote->codigo_lote,
                    '-'.$cantidad,
                    $motivo,
                    $capa->presentacion,
                    $capa->cantidad_presentacion,
                    $capa
                );
            }

            $lote->cantidad_disponible_base = 0;
            $lote->estado = 'baja';
            $lote->save();
        });
    }

    public function validarStockContraLotes(Producto $producto): void
    {
        $sumaLotes = ProductoLote::where('producto_id', $producto->id_producto)
            ->where('estado', '!=', 'baja')
            ->sum('cantidad_disponible_base');

        if (CantidadHelper::comparar($producto->stock, (string) $sumaLotes, 3) !== 0) {
            throw new \RuntimeException(
                "Inventario inconsistente en \"{$producto->nombre}\": stock "
                .CantidadHelper::formatear($producto->stock)
                .', lotes '.CantidadHelper::formatear($sumaLotes)
                .'. Regularice con inventario inicial o un ajuste antes de operar.'
            );
        }
    }
}
