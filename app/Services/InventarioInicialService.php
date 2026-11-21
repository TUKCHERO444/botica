<?php

namespace App\Services;

use App\Helpers\CantidadHelper;
use App\Helpers\KardexHelper;
use App\Models\Categoria;
use App\Models\DetalleInventarioInicial;
use App\Models\InventarioInicial;
use App\Models\LoteIngreso;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class InventarioInicialService
{
    private const COLUMNAS_CSV = [
        'nombre',
        'unidad_base',
        'lote',
        'vencimiento',
        'cantidad_base',
    ];

    public function crearBorrador(array $data, ?int $userId = null): InventarioInicial
    {
        return DB::transaction(fn () => $this->crearBorradorInterno($data, $userId));
    }

    public function actualizarBorrador(InventarioInicial $inventario, array $data): InventarioInicial
    {
        return DB::transaction(function () use ($inventario, $data) {
            $inventario = InventarioInicial::whereKey($inventario->id_inventario_inicial)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inventario->estaConfirmado()) {
                throw new \RuntimeException('No se puede editar un inventario inicial confirmado.');
            }

            $lineas = $data['lineas'] ?? [];

            if (empty($lineas)) {
                throw new \RuntimeException('Debe agregar al menos una línea al inventario inicial.');
            }

            $inventario->detalles()->delete();

            $totalCosto = '0.00';

            foreach ($lineas as $linea) {
                $detalle = $this->crearDetalle($inventario, $linea);
                $totalCosto = CantidadHelper::sumar($totalCosto, $detalle->costo_total, 2);
            }

            $inventario->update([
                'fecha' => $data['fecha'] ?? $inventario->fecha,
                'observaciones' => $data['observaciones'] ?? null,
                'total_lineas' => count($lineas),
                'total_costo' => $totalCosto,
            ]);

            return $inventario->fresh([
                'user',
                'detalles.producto.unidadBase',
                'detalles.presentacion.presentacion',
            ]);
        });
    }

    public function confirmar(InventarioInicial $inventario, ?int $userId = null): InventarioInicial
    {
        return DB::transaction(function () use ($inventario, $userId) {
            $inventario = InventarioInicial::whereKey($inventario->id_inventario_inicial)
                ->lockForUpdate()
                ->firstOrFail();

            if ($inventario->estaConfirmado()) {
                throw new \RuntimeException('Este inventario inicial ya fue confirmado.');
            }

            $detalles = $inventario->detalles()
                ->with(['producto', 'presentacion.presentacion'])
                ->get();

            if ($detalles->isEmpty()) {
                throw new \RuntimeException('El inventario inicial no tiene líneas para confirmar.');
            }

            $productos = $detalles->pluck('producto_id')->unique();

            foreach ($productos as $productoId) {
                $producto = Producto::whereKey($productoId)->lockForUpdate()->firstOrFail();
                $this->validarProductoSinStock($producto);
            }

            foreach ($detalles as $detalle) {
                $producto = Producto::whereKey($detalle->producto_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $presentacion = ProductoPresentacion::with('presentacion')
                    ->whereKey($detalle->producto_presentacion_id)
                    ->firstOrFail();

                $codigoLote = mb_strtoupper(trim($detalle->codigo_lote));
                $fechaVencimiento = Carbon::parse($detalle->fecha_vencimiento)->startOfDay();

                if ($fechaVencimiento->lt(today())) {
                    throw new \RuntimeException(
                        "El lote {$codigoLote} del producto {$producto->nombre} está vencido."
                    );
                }

                $lote = ProductoLote::where('producto_id', $producto->id_producto)
                    ->where('codigo_lote', $codigoLote)
                    ->whereDate('fecha_vencimiento', $fechaVencimiento->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($lote && ! $lote->es_inicial) {
                    throw new \RuntimeException(
                        "Ya existe un lote {$codigoLote} con ese vencimiento para {$producto->nombre}."
                    );
                }

                if ($lote && in_array($lote->estado, ['baja', 'vencido'], true)) {
                    throw new \RuntimeException(
                        "El lote {$codigoLote} de {$producto->nombre} está dado de baja o vencido."
                    );
                }

                if ($lote && CantidadHelper::comparar($lote->cantidad_disponible_base, 0) > 0) {
                    throw new \RuntimeException(
                        "El lote {$codigoLote} de {$producto->nombre} ya tiene stock."
                    );
                }

                if (! $lote) {
                    $lote = ProductoLote::create([
                        'producto_id' => $producto->id_producto,
                        'codigo_lote' => $codigoLote,
                        'fecha_vencimiento' => $fechaVencimiento->toDateString(),
                        'estado' => 'disponible',
                        'cantidad_inicial_base' => 0,
                        'cantidad_disponible_base' => 0,
                        'es_inicial' => true,
                    ]);
                }

                $cantidad = (string) $detalle->cantidad_base;
                $factor = CantidadHelper::comparar($detalle->factor, 0, 3) > 0
                    ? (string) $detalle->factor
                    : (string) $presentacion->factor;
                $cantidadPresentacion = CantidadHelper::comparar($detalle->cantidad_presentacion, 0, 3) > 0
                    ? (string) $detalle->cantidad_presentacion
                    : bcdiv($cantidad, $factor, 3);

                $capa = LoteIngreso::create([
                    'producto_lote_id' => $lote->id,
                    'ingreso_id' => null,
                    'detalle_ingreso_id' => null,
                    'producto_presentacion_id' => $presentacion->id_producto_presentacion,
                    'cantidad_presentacion' => $cantidadPresentacion,
                    'factor' => $factor,
                    'cantidad_base' => $cantidad,
                    'cantidad_disponible_base' => $cantidad,
                    'costo_unitario_base' => $detalle->costo_unitario_base,
                    'costo_total' => $detalle->costo_total,
                ]);

                $lote->cantidad_inicial_base = CantidadHelper::sumar(
                    $lote->cantidad_inicial_base,
                    $cantidad
                );
                $lote->cantidad_disponible_base = CantidadHelper::sumar(
                    $lote->cantidad_disponible_base,
                    $cantidad
                );
                $lote->estado = 'disponible';
                $lote->save();

                $producto->stock = CantidadHelper::sumar($producto->stock, $cantidad);
                $producto->inventario = $producto->stock;
                $producto->save();

                KardexHelper::registrar(
                    $producto,
                    'INGRESO',
                    'InventarioInicial',
                    $inventario->id_inventario_inicial,
                    $inventario->correlativo,
                    $cantidad,
                    'Inventario inicial '.$inventario->correlativo,
                    $presentacion,
                    $cantidadPresentacion,
                    $capa
                );

                $detalle->update([
                    'producto_lote_id' => $lote->id,
                    'lote_ingreso_id' => $capa->id,
                ]);
            }

            $inventario->update([
                'estado' => InventarioInicial::ESTADO_CONFIRMADO,
                'confirmado_at' => now(),
                'user_id' => $inventario->user_id ?? $userId,
            ]);

            return $inventario->fresh([
                'user',
                'detalles.producto.unidadBase',
                'detalles.presentacion.presentacion',
                'detalles.lote',
            ]);
        });
    }

    public function previsualizarCsv(UploadedFile $archivo): array
    {
        [$filas, $errores] = $this->parsearCsv($archivo);

        if ($errores !== []) {
            return [
                'filas' => [],
                'errores' => $errores,
                'advertencias' => [],
                'resumen' => $this->resumen([]),
            ];
        }

        $normalizadas = [];
        $advertencias = [];
        $vistas = [];
        $productosNuevos = [];

        foreach ($filas as $fila) {
            $numeroFila = $fila['_fila'];
            $nombre = trim((string) ($fila['nombre'] ?? ''));

            if ($nombre === '') {
                $errores[] = $this->error($numeroFila, 'nombre', 'El nombre del producto es obligatorio.');

                continue;
            }

            if (mb_strlen($nombre) > 50) {
                $errores[] = $this->error($numeroFila, 'nombre', 'El nombre no puede superar 50 caracteres.');

                continue;
            }

            $descripcion = trim((string) ($fila['descripcion'] ?? ''));

            if (mb_strlen($descripcion) > 50) {
                $errores[] = $this->error($numeroFila, 'descripcion', 'La descripción no puede superar 50 caracteres.');

                continue;
            }

            $codigoUnidad = mb_strtoupper(trim((string) ($fila['unidad_base'] ?? '')));
            $unidad = UnidadMedida::where('codigo', $codigoUnidad)->where('activo', true)->first();

            if (! $unidad) {
                $errores[] = $this->error($numeroFila, 'unidad_base', "No existe la unidad base {$codigoUnidad}.");

                continue;
            }

            if (! $unidad->presentacion_base_id) {
                $errores[] = $this->error(
                    $numeroFila,
                    'unidad_base',
                    "La unidad {$codigoUnidad} no tiene presentación base configurada."
                );

                continue;
            }

            $codigoLote = mb_strtoupper(trim((string) ($fila['lote'] ?? '')));

            if ($codigoLote === '' || mb_strlen($codigoLote) > 50) {
                $errores[] = $this->error($numeroFila, 'lote', 'El lote es obligatorio y no puede superar 50 caracteres.');

                continue;
            }

            $vencimientoTexto = trim((string) ($fila['vencimiento'] ?? ''));

            if ($vencimientoTexto === '') {
                $errores[] = $this->error($numeroFila, 'vencimiento', 'La fecha de vencimiento es obligatoria.');

                continue;
            }

            try {
                $vencimiento = Carbon::parse($vencimientoTexto)->startOfDay();
            } catch (\Throwable) {
                $errores[] = $this->error($numeroFila, 'vencimiento', 'La fecha de vencimiento no es válida.');

                continue;
            }

            if ($vencimiento->lt(today())) {
                $errores[] = $this->error($numeroFila, 'vencimiento', 'La fecha de vencimiento no puede ser pasada.');

                continue;
            }

            $cantidad = $this->numero($fila['cantidad_base'] ?? null);

            if ($cantidad === null || CantidadHelper::comparar($cantidad, 0, 3) <= 0) {
                $errores[] = $this->error($numeroFila, 'cantidad_base', 'La cantidad base debe ser mayor que cero.');

                continue;
            }

            $precioCompraTexto = trim((string) ($fila['precio_compra'] ?? ''));
            $precioVentaTexto = trim((string) ($fila['precio_venta'] ?? ''));
            $stockMinimoTexto = trim((string) ($fila['stock_minimo'] ?? ''));
            $costoTexto = trim((string) ($fila['costo_unitario_base'] ?? ''));

            $precioCompra = $this->numero($precioCompraTexto);
            $precioVenta = $this->numero($precioVentaTexto);
            $stockMinimo = $this->numero($stockMinimoTexto);
            $costoBase = $this->numero($costoTexto);

            if ($precioCompraTexto !== '' && $precioCompra === null) {
                $errores[] = $this->error($numeroFila, 'precio_compra', 'El precio de compra no es numérico.');

                continue;
            }

            if ($precioCompraTexto !== '' && ! $this->maximoDecimales($precioCompraTexto, 2)) {
                $advertencias[] = $this->advertencia(
                    $numeroFila,
                    'precio_compra',
                    'Se redondeó el precio de compra a 2 decimales.'
                );
                $precioCompra = $this->redondearDecimal($precioCompra, 2);
            }

            if ($precioVentaTexto !== '' && $precioVenta === null) {
                $errores[] = $this->error($numeroFila, 'precio_venta', 'El precio de venta no es numérico.');

                continue;
            }

            if ($precioVentaTexto !== '' && ! $this->maximoDecimales($precioVentaTexto, 2)) {
                $advertencias[] = $this->advertencia(
                    $numeroFila,
                    'precio_venta',
                    'Se redondeó el precio de venta a 2 decimales.'
                );
                $precioVenta = $this->redondearDecimal($precioVenta, 2);
            }

            if ($stockMinimoTexto !== '' && $stockMinimo === null) {
                $errores[] = $this->error($numeroFila, 'stock_minimo', 'El stock mínimo no es numérico.');

                continue;
            }

            if ($stockMinimoTexto !== '' && ! $this->maximoDecimales($stockMinimoTexto, 3)) {
                $advertencias[] = $this->advertencia(
                    $numeroFila,
                    'stock_minimo',
                    'Se redondeó el stock mínimo a 3 decimales.'
                );
                $stockMinimo = $this->redondearDecimal($stockMinimo, 3);
            }

            if ($costoTexto !== '' && $costoBase === null) {
                $errores[] = $this->error($numeroFila, 'costo_unitario_base', 'El costo no es numérico.');

                continue;
            }

            if ($costoTexto !== '' && ! $this->maximoDecimales($costoTexto, 2)) {
                $advertencias[] = $this->advertencia(
                    $numeroFila,
                    'costo_unitario_base',
                    'Se redondeó el costo a 2 decimales.'
                );
                $costoBase = $this->redondearDecimal($costoBase, 2);
            }

            if (! $this->maximoDecimales($cantidad, 3)) {
                $advertencias[] = $this->advertencia(
                    $numeroFila,
                    'cantidad_base',
                    'Se redondeó la cantidad a 3 decimales.'
                );
                $cantidad = $this->redondearDecimal($cantidad, 3);
            }

            $precioCompra ??= '0';
            $precioVenta ??= '0';
            $stockMinimo ??= '0';
            $costoBase ??= $precioCompra;

            if (CantidadHelper::comparar($costoBase, 0, 4) < 0) {
                $errores[] = $this->error($numeroFila, 'costo_unitario_base', 'El costo no puede ser negativo.');

                continue;
            }

            if (CantidadHelper::comparar($costoBase, 0, 4) === 0) {
                $advertencias[] = $this->advertencia(
                    $numeroFila,
                    'costo_unitario_base',
                    'El costo es 0; el margen de este producto no será confiable.'
                );
            }

            $categoria = trim((string) ($fila['categoria'] ?? '')) ?: 'General';
            $marca = trim((string) ($fila['marca'] ?? '')) ?: 'General';

            if (mb_strlen($categoria) > 50 || mb_strlen($marca) > 50) {
                $errores[] = $this->error($numeroFila, 'categoria', 'Categoría o marca supera 50 caracteres.');

                continue;
            }

            $producto = $this->buscarProducto($nombre, $unidad->id_unidad_medida);

            if ($producto) {
                try {
                    $this->validarProductoSinStock($producto);
                } catch (\RuntimeException $e) {
                    $errores[] = $this->error($numeroFila, 'nombre', $e->getMessage());

                    continue;
                }
            }

            $nuevo = ! $producto;
            $productoKey = $producto
                ? 'id:'.$producto->id_producto
                : 'nuevo:'.mb_strtolower($nombre).'|'.$unidad->id_unidad_medida;

            if ($nuevo) {
                $productosNuevos[$productoKey] = true;
            }

            $claveLote = $productoKey.'|'.$codigoLote.'|'.$vencimiento->toDateString();

            if (isset($vistas[$claveLote])) {
                $errores[] = $this->error(
                    $numeroFila,
                    'lote',
                    "Lote duplicado en el archivo: {$nombre} / {$codigoLote} / {$vencimiento->toDateString()}."
                );

                continue;
            }

            $vistas[$claveLote] = true;

            $presentacionNombre = trim((string) ($fila['presentacion'] ?? ''));
            $factorPresentacion = null;
            $precioPresentacion = null;
            $presentacionId = null;

            if ($presentacionNombre !== '') {
                $presentacion = Presentacion::where('nombre', $presentacionNombre)
                    ->orWhere('abreviatura', mb_strtoupper($presentacionNombre))
                    ->first();

                if (! $presentacion) {
                    $errores[] = $this->error(
                        $numeroFila,
                        'presentacion',
                        "No existe la presentación {$presentacionNombre}."
                    );

                    continue;
                }

                $factorTexto = trim((string) ($fila['factor'] ?? ''));
                $factorPresentacion = $this->numero($factorTexto);

                if ($factorPresentacion === null || CantidadHelper::comparar($factorPresentacion, 1, 3) <= 0) {
                    $errores[] = $this->error($numeroFila, 'factor', 'El factor de la presentación debe ser mayor que 1.');

                    continue;
                }

                if (! $this->maximoDecimales($factorTexto, 3)) {
                    $advertencias[] = $this->advertencia(
                        $numeroFila,
                        'factor',
                        'Se redondeó el factor a 3 decimales.'
                    );
                    $factorPresentacion = $this->redondearDecimal($factorPresentacion, 3);
                }

                $precioPresentacionTexto = trim((string) ($fila['precio_presentacion'] ?? ''));

                if ($precioPresentacionTexto !== '' && ! $this->maximoDecimales($precioPresentacionTexto, 2)) {
                    $advertencias[] = $this->advertencia(
                        $numeroFila,
                        'precio_presentacion',
                        'Se redondeó el precio de presentación a 2 decimales.'
                    );
                }

                $precioPresentacion = $this->numero($precioPresentacionTexto) ?? $precioVenta;
                $precioPresentacion = $this->redondearDecimal($precioPresentacion, 2);
                $presentacionId = $presentacion->id_presentacion;
            }

            $normalizadas[] = [
                'fila' => $numeroFila,
                'producto_key' => $productoKey,
                'producto_id' => $producto?->id_producto,
                'nuevo_producto' => $nuevo,
                'nombre' => $nombre,
                'descripcion' => $descripcion ?: null,
                'categoria' => $categoria,
                'marca' => $marca,
                'unidad_base_id' => $unidad->id_unidad_medida,
                'unidad_codigo' => $codigoUnidad,
                'presentacion_base_id' => $unidad->presentacion_base_id,
                'precio_venta' => $precioVenta,
                'precio_compra' => $precioCompra,
                'stock_minimo' => $stockMinimo,
                'presentacion_id' => $presentacionId,
                'presentacion_nombre' => $presentacionNombre ?: null,
                'factor_presentacion' => $factorPresentacion,
                'precio_presentacion' => $precioPresentacion,
                'codigo_lote' => $codigoLote,
                'fecha_vencimiento' => $vencimiento->toDateString(),
                'cantidad_base' => $cantidad,
                'costo_unitario_base' => $costoBase,
            ];
        }

        return [
            'filas' => $normalizadas,
            'errores' => $errores,
            'advertencias' => $advertencias,
            'resumen' => $this->resumen($normalizadas, $productosNuevos),
        ];
    }

    public function importarCsv(UploadedFile $archivo, bool $dryRun = false, ?int $userId = null): array
    {
        $preview = $this->previsualizarCsv($archivo);

        if ($preview['errores'] !== []) {
            return $preview + ['inventario' => null];
        }

        if ($dryRun) {
            return $preview + ['inventario' => null];
        }

        $hash = hash_file('sha256', $archivo->getRealPath());

        if (InventarioInicial::where('archivo_hash', $hash)->exists()) {
            $preview['errores'][] = $this->error(0, 'archivo', 'Este archivo ya fue importado.');

            return $preview + ['inventario' => null];
        }

        $inventario = DB::transaction(function () use ($preview, $archivo, $hash, $userId) {
            $lineas = [];

            foreach ($preview['filas'] as $fila) {
                $producto = $this->resolverProducto($fila);
                $base = $producto->presentacionBase
                    ?? $producto->presentaciones()->where('es_base', true)->first();

                if (! $base) {
                    throw new \RuntimeException(
                        "El producto {$producto->nombre} no tiene una presentación base."
                    );
                }

                $lineas[] = [
                    'producto_presentacion_id' => $base->id_producto_presentacion,
                    'codigo_lote' => $fila['codigo_lote'],
                    'fecha_vencimiento' => $fila['fecha_vencimiento'],
                    'cantidad_base' => $fila['cantidad_base'],
                    'costo_unitario_base' => $fila['costo_unitario_base'],
                ];
            }

            return $this->crearBorradorInterno([
                'fecha' => today()->toDateString(),
                'observaciones' => 'Importación CSV: '.$archivo->getClientOriginalName(),
                'archivo_nombre' => $archivo->getClientOriginalName(),
                'archivo_hash' => $hash,
                'lineas' => $lineas,
            ], $userId);
        });

        return [
            'filas' => $preview['filas'],
            'errores' => [],
            'advertencias' => $preview['advertencias'],
            'resumen' => $preview['resumen'],
            'inventario' => $inventario,
        ];
    }

    public function plantillaCsv(): string
    {
        $filas = [
            [
                'nombre',
                'unidad_base',
                'categoria',
                'marca',
                'descripcion',
                'precio_venta',
                'precio_compra',
                'stock_minimo',
                'lote',
                'vencimiento',
                'cantidad_base',
                'costo_unitario_base',
                'presentacion',
                'factor',
                'precio_presentacion',
            ],
            [
                'Paracetamol 500 mg',
                'TAB',
                'Analgésicos',
                'Genérico',
                'Tableta',
                '0.50',
                '0.20',
                '20',
                'L2026-04',
                '2027-04-30',
                '500',
                '0.20',
                'Caja',
                '100',
                '45.00',
            ],
        ];

        $handle = fopen('php://temp', 'r+');
        foreach ($filas as $fila) {
            fputcsv($handle, $fila);
        }
        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        return $contenido;
    }

    private function crearBorradorInterno(array $data, ?int $userId): InventarioInicial
    {
        $lineas = $data['lineas'] ?? [];

        if (empty($lineas)) {
            throw new \RuntimeException('Debe agregar al menos una línea al inventario inicial.');
        }

        $inventario = InventarioInicial::create([
            'correlativo' => 'TEMP',
            'fecha' => $data['fecha'] ?? today()->toDateString(),
            'user_id' => $userId,
            'estado' => InventarioInicial::ESTADO_BORRADOR,
            'observaciones' => $data['observaciones'] ?? null,
            'archivo_nombre' => $data['archivo_nombre'] ?? null,
            'archivo_hash' => $data['archivo_hash'] ?? null,
        ]);

        $inventario->update([
            'correlativo' => 'INI-'.str_pad(
                (string) $inventario->id_inventario_inicial,
                8,
                '0',
                STR_PAD_LEFT
            ),
        ]);

        $totalCosto = '0.00';

        foreach ($lineas as $linea) {
            $detalle = $this->crearDetalle($inventario, $linea);
            $totalCosto = CantidadHelper::sumar($totalCosto, $detalle->costo_total, 2);
        }

        $inventario->update([
            'total_lineas' => count($lineas),
            'total_costo' => $totalCosto,
        ]);

        return $inventario->fresh([
            'user',
            'detalles.producto.unidadBase',
            'detalles.presentacion.presentacion',
        ]);
    }

    private function crearDetalle(InventarioInicial $inventario, array $linea): DetalleInventarioInicial
    {
        $presentacion = ProductoPresentacion::with('producto')
            ->whereKey($linea['producto_presentacion_id'])
            ->firstOrFail();

        $producto = $presentacion->producto;
        $this->validarProductoSinStock($producto);

        $codigoLote = mb_strtoupper(trim((string) $linea['codigo_lote']));

        if ($codigoLote === '') {
            throw new \RuntimeException('El código de lote es obligatorio.');
        }

        $vencimiento = Carbon::parse($linea['fecha_vencimiento'])->startOfDay();

        if ($vencimiento->lt(today())) {
            throw new \RuntimeException(
                "El lote {$codigoLote} de {$producto->nombre} tiene fecha de vencimiento pasada."
            );
        }

        $factor = (string) $presentacion->factor;
        $cantidadPresentacionInput = $linea['cantidad_presentacion'] ?? null;
        $cantidadBaseInput = $linea['cantidad_base'] ?? null;

        if ($cantidadPresentacionInput !== null && $cantidadPresentacionInput !== '') {
            $cantidadPresentacion = bcadd((string) $cantidadPresentacionInput, '0', 3);
            $cantidadBase = CantidadHelper::aBase($cantidadPresentacion, $factor);
        } elseif ($cantidadBaseInput !== null && $cantidadBaseInput !== '') {
            $cantidadBase = bcadd((string) $cantidadBaseInput, '0', 3);
            $cantidadPresentacion = bcdiv($cantidadBase, $factor, 3);
        } else {
            throw new \RuntimeException('La cantidad es obligatoria.');
        }

        if (CantidadHelper::comparar($cantidadBase, 0, 3) <= 0) {
            throw new \RuntimeException('La cantidad debe ser mayor que cero.');
        }

        $costoPresentacionInput = $linea['costo_unitario_presentacion'] ?? null;
        $costoBaseInput = $linea['costo_unitario_base'] ?? null;

        if ($costoPresentacionInput !== null && $costoPresentacionInput !== '') {
            $costoUnitarioPresentacion = (string) $costoPresentacionInput;
            $costoUnitarioBase = bcdiv($costoUnitarioPresentacion, $factor, 4);
        } elseif ($costoBaseInput !== null && $costoBaseInput !== '') {
            $costoUnitarioBase = (string) $costoBaseInput;
            $costoUnitarioPresentacion = CantidadHelper::redondear(
                CantidadHelper::multiplicar($costoUnitarioBase, $factor, 4),
                2
            );
        } else {
            $costoUnitarioBase = (string) ($producto->precio_compra ?? 0);
            $costoUnitarioPresentacion = CantidadHelper::redondear(
                CantidadHelper::multiplicar($costoUnitarioBase, $factor, 4),
                2
            );
        }

        if (CantidadHelper::comparar($costoUnitarioBase, 0, 4) < 0) {
            throw new \RuntimeException('El costo unitario no puede ser negativo.');
        }

        $duplicado = $inventario->detalles()
            ->where('producto_id', $producto->id_producto)
            ->where('codigo_lote', $codigoLote)
            ->whereDate('fecha_vencimiento', $vencimiento->toDateString())
            ->exists();

        if ($duplicado) {
            throw new \RuntimeException(
                "El lote {$codigoLote} de {$producto->nombre} está repetido en el inventario inicial."
            );
        }

        return $inventario->detalles()->create([
            'producto_id' => $producto->id_producto,
            'producto_presentacion_id' => $presentacion->id_producto_presentacion,
            'cantidad_presentacion' => $cantidadPresentacion,
            'factor' => $factor,
            'codigo_lote' => $codigoLote,
            'fecha_vencimiento' => $vencimiento->toDateString(),
            'cantidad_base' => $cantidadBase,
            'costo_unitario_base' => $costoUnitarioBase,
            'costo_unitario_presentacion' => $costoUnitarioPresentacion,
            'costo_total' => CantidadHelper::multiplicar(
                $cantidadPresentacion,
                $costoUnitarioPresentacion,
                2
            ),
        ]);
    }

    private function validarProductoSinStock(Producto $producto): void
    {
        if (CantidadHelper::comparar($producto->stock, 0, 3) !== 0) {
            throw new \RuntimeException(
                "El producto {$producto->nombre} ya tiene stock. Use un ajuste o regularice antes de cargar inventario inicial."
            );
        }

        $sumaLotes = ProductoLote::where('producto_id', $producto->id_producto)
            ->where('cantidad_disponible_base', '>', 0)
            ->sum('cantidad_disponible_base');

        if (CantidadHelper::comparar((string) $sumaLotes, 0, 3) !== 0) {
            throw new \RuntimeException(
                "El producto {$producto->nombre} ya tiene lotes con stock. No se puede cargar inventario inicial."
            );
        }
    }

    private function parsearCsv(UploadedFile $archivo): array
    {
        $handle = fopen($archivo->getRealPath(), 'r');

        if (! $handle) {
            return [[], [$this->error(0, 'archivo', 'No se pudo leer el archivo.')]];
        }

        $primeraLinea = fgets($handle) ?: '';
        $delimitador = substr_count($primeraLinea, ';') >= substr_count($primeraLinea, ',') ? ';' : ',';
        rewind($handle);

        $encabezados = fgetcsv($handle, 0, $delimitador) ?: [];
        $encabezados = array_map(fn ($encabezado) => $this->normalizarEncabezado((string) $encabezado), $encabezados);

        $faltantes = array_diff(self::COLUMNAS_CSV, $encabezados);

        if ($faltantes !== []) {
            fclose($handle);

            return [[], [$this->error(
                1,
                'archivo',
                'Faltan columnas obligatorias: '.implode(', ', $faltantes).'.'
            )]];
        }

        $filas = [];
        $numeroFila = 1;

        while (($valores = fgetcsv($handle, 0, $delimitador)) !== false) {
            $numeroFila++;

            if (count(array_filter($valores, fn ($valor) => trim((string) $valor) !== '')) === 0) {
                continue;
            }

            $fila = [];

            foreach ($encabezados as $indice => $encabezado) {
                $fila[$encabezado] = $valores[$indice] ?? null;
            }

            $fila['_fila'] = $numeroFila;
            $filas[] = $fila;
        }

        fclose($handle);

        if ($filas === []) {
            return [[], [$this->error(1, 'archivo', 'El archivo no tiene filas de datos.')]];
        }

        return [$filas, []];
    }

    private function normalizarEncabezado(string $encabezado): string
    {
        $encabezado = preg_replace('/^\xEF\xBB\xBF/', '', $encabezado) ?? $encabezado;
        $encabezado = mb_strtolower(trim($encabezado));

        return str_replace([' ', '-'], '_', $encabezado);
    }

    private function numero(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        $texto = str_replace(['S/', 's/', ' '], '', $texto);

        if (str_contains($texto, ',') && ! str_contains($texto, '.')) {
            $texto = str_replace(',', '.', $texto);
        }

        return is_numeric($texto) ? $texto : null;
    }

    private function maximoDecimales(mixed $valor, int $maximo): bool
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return true;
        }

        $texto = str_replace(['S/', 's/', ' '], '', $texto);

        if (! str_contains($texto, '.')) {
            return true;
        }

        $decimales = rtrim(explode('.', $texto, 2)[1] ?? '', '0');

        return strlen($decimales) <= $maximo;
    }

    private function redondearDecimal(mixed $valor, int $decimales): string
    {
        return number_format(
            round((float) $valor, $decimales),
            $decimales,
            '.',
            ''
        );
    }

    private function buscarProducto(string $nombre, int $unidadId): ?Producto
    {
        return Producto::where('unidad_base_id', $unidadId)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();
    }

    private function resolverProducto(array $fila): Producto
    {
        if ($fila['producto_id']) {
            return Producto::findOrFail($fila['producto_id']);
        }

        $producto = $this->buscarProducto(
            $fila['nombre'],
            $fila['unidad_base_id']
        );

        if ($producto) {
            $this->completarPresentacion($producto, $fila);

            return $producto;
        }

        $categoria = Categoria::firstOrCreate(
            ['nombre' => $fila['categoria']],
            ['cantidad' => 0, 'estado' => 1]
        );
        $marca = Marca::firstOrCreate(
            ['nombre' => $fila['marca']],
            ['cantidad' => 0, 'estado' => 1]
        );

        $producto = Producto::create([
            'nombre' => $fila['nombre'],
            'descripcion' => $fila['descripcion'],
            'precio_compra' => $fila['precio_compra'],
            'precio_venta' => $fila['precio_venta'],
            'estado' => 1,
            'inventario' => 0,
            'stock' => 0,
            'stock_minimo' => $fila['stock_minimo'],
            'unidad_base_id' => $fila['unidad_base_id'],
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $categoria->increment('cantidad');
        $marca->increment('cantidad');

        ProductoPresentacion::create([
            'producto_id_producto' => $producto->id_producto,
            'presentacion_id' => $fila['presentacion_base_id'],
            'factor' => 1,
            'precio_venta' => $fila['precio_venta'],
            'paso' => 1,
            'es_base' => true,
            'activo' => true,
            'orden' => 0,
        ]);

        $this->completarPresentacion($producto->fresh(), $fila);

        return $producto->fresh();
    }

    private function completarPresentacion(Producto $producto, array $fila): void
    {
        if ($fila['presentacion_id'] && $fila['factor_presentacion']) {
            $existente = ProductoPresentacion::where('producto_id_producto', $producto->id_producto)
                ->where('presentacion_id', $fila['presentacion_id'])
                ->first();

            if (! $existente) {
                ProductoPresentacion::create([
                    'producto_id_producto' => $producto->id_producto,
                    'presentacion_id' => $fila['presentacion_id'],
                    'factor' => $fila['factor_presentacion'],
                    'precio_venta' => $fila['precio_presentacion'],
                    'paso' => 1,
                    'es_base' => false,
                    'activo' => true,
                    'orden' => 1,
                ]);
            }
        }
    }

    private function resumen(array $filas, array $productosNuevos = []): array
    {
        $costo = '0.00';

        foreach ($filas as $fila) {
            $costo = CantidadHelper::sumar(
                $costo,
                CantidadHelper::multiplicar($fila['cantidad_base'], $fila['costo_unitario_base'], 2),
                2
            );
        }

        return [
            'lineas' => count($filas),
            'productos_nuevos' => count($productosNuevos),
            'productos_existentes' => collect($filas)
                ->reject(fn ($fila) => $fila['nuevo_producto'])
                ->pluck('producto_key')
                ->unique()
                ->count(),
            'costo_total' => CantidadHelper::redondear($costo, 2),
        ];
    }

    private function error(int $fila, string $campo, string $mensaje): array
    {
        return compact('fila', 'campo', 'mensaje');
    }

    private function advertencia(int $fila, string $campo, string $mensaje): array
    {
        return compact('fila', 'campo', 'mensaje');
    }
}
