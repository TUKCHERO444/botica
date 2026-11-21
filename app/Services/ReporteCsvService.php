<?php

namespace App\Services;

class ReporteCsvService
{
    /**
     * Devuelve [encabezados, filas] para el reporte solicitado.
     */
    public function generar(string $tipo, array $data): array
    {
        return match ($tipo) {
            'ventas' => $this->ventas($data),
            'atenciones' => $this->atenciones($data),
            'ingresos' => $this->ingresos($data),
            'caja' => $this->caja($data),
            'inventario' => $this->inventario($data),
            'lotes' => $this->lotes($data),
            'movimientos' => $this->movimientos($data),
            'rentabilidad' => $this->rentabilidad($data),
            'kardex' => $this->kardex($data),
            'clientes' => $this->clientes($data),
            'general' => $this->general($data),
            'auditoria' => $this->auditoria($data),
            'pagos' => $this->pagos($data),
            'ventas_producto' => $this->ventasProducto($data),
            'ventas_diario' => $this->ventasDiario($data),
            'ventas_vendedor' => $this->ventasVendedor($data),
            'anulaciones' => $this->anulaciones($data),
            'compras_proveedor' => $this->comprasProveedor($data),
            'costos_producto' => $this->costosProducto($data),
            'devoluciones_proveedor' => $this->devolucionesProveedor($data),
            'merma' => $this->merma($data),
            'rotacion' => $this->rotacion($data),
            'margen_agrupado' => $this->margenAgrupado($data),
            'servicios' => $this->servicios($data),
            'clientes_rfm' => $this->clientesRfm($data),
            'clientes_web' => $this->clientesWeb($data),
            'cumpleanos' => $this->cumpleanos($data),
            'sugerido_compra' => $this->sugeridoCompra($data),
            'caja_origen' => $this->cajaOrigen($data),
            'resumen_gerencial' => $this->resumenGerencial($data),
            default => [['Reporte'], [['Sin datos']]],
        };
    }

    private function ventas(array $data): array
    {
        $headers = ['Correlativo', 'Fecha', 'Cliente', 'Usuario', 'Total'];

        $rows = collect($data['ventas'])->map(fn ($venta) => [
            $venta->correlativo,
            $venta->created_at?->format('d/m/Y H:i'),
            trim(($venta->cliente?->nombre ?? '').' '.($venta->cliente?->apellido_paterno ?? '')),
            $venta->user?->name,
            (float) $venta->total,
        ])->all();

        return [$headers, $rows];
    }

    private function atenciones(array $data): array
    {
        $headers = ['Correlativo', 'Fecha', 'Cliente', 'Usuario', 'Total', 'Observación'];

        $rows = collect($data['atenciones'])->map(fn ($atencion) => [
            $atencion->correlativo,
            $atencion->created_at?->format('d/m/Y H:i'),
            trim(($atencion->cliente?->nombre ?? '').' '.($atencion->cliente?->apellido_paterno ?? '')),
            $atencion->user?->name,
            (float) $atencion->total,
            $atencion->observacio,
        ])->all();

        return [$headers, $rows];
    }

    private function ingresos(array $data): array
    {
        $headers = ['Correlativo', 'Fecha', 'Documento', 'Proveedor', 'Usuario', 'Total', 'Pago', 'Método'];

        $rows = collect($data['ingresos'])->map(fn ($ingreso) => [
            $ingreso->correlativo,
            $ingreso->fecha?->format('d/m/Y'),
            trim(($ingreso->tipo_documento_label ?? '').' '.($ingreso->numero_documento ?? '')),
            $ingreso->proveedor?->razon_social,
            $ingreso->user?->name,
            (float) $ingreso->total,
            $ingreso->pagado_desde_caja ? 'Caja' : 'Fuera de caja',
            $ingreso->metodo_pago_label,
        ])->all();

        return [$headers, $rows];
    }

    private function caja(array $data): array
    {
        $headers = [
            'Caja', 'Estado', 'Apertura', 'Cierre', 'Monto inicial',
            'Efectivo esperado', 'Efectivo contado', 'Diferencia',
            'Tipo', 'Categoría', 'Método', 'Monto', 'Descripción', 'Referencia', 'Usuario', 'Fecha',
        ];

        $rows = [];

        foreach ($data['cajas'] as $caja) {
            $base = [
                $caja->id_caja,
                $caja->estado,
                $caja->apertura_at?->format('d/m/Y H:i'),
                $caja->cierre_at?->format('d/m/Y H:i'),
                (float) $caja->monto_inicial,
                $caja->efectivo_esperado !== null ? (float) $caja->efectivo_esperado : null,
                $caja->efectivo_contado !== null ? (float) $caja->efectivo_contado : null,
                $caja->diferencia !== null ? (float) $caja->diferencia : null,
            ];

            if ($caja->movimientos->isEmpty()) {
                $rows[] = array_merge($base, [null, null, null, null, null, null, null, null]);

                continue;
            }

            foreach ($caja->movimientos as $movimiento) {
                $rows[] = array_merge($base, [
                    $movimiento->tipo,
                    $movimiento->categoria_label,
                    $movimiento->metodo_label,
                    (float) $movimiento->monto,
                    $movimiento->descripcion_visible,
                    $movimiento->referencia_tipo.' #'.$movimiento->referencia_id,
                    $movimiento->user?->name,
                    $movimiento->created_at?->format('d/m/Y H:i'),
                ]);
            }
        }

        return [$headers, $rows];
    }

    private function inventario(array $data): array
    {
        $headers = ['Producto', 'Categoría', 'Marca', 'Stock', 'Unidad', 'Costo real', 'Valor a costo', 'Valor a compra', 'Precio venta'];

        $rows = collect($data['productos'])->map(fn ($producto) => [
            $producto->nombre,
            $producto->categoria?->nombre,
            $producto->marca?->nombre,
            (float) $producto->stock,
            $producto->unidadBase?->codigo,
            (float) $producto->stock > 0 ? (float) $producto->valor_costo / (float) $producto->stock : 0,
            (float) $producto->valor_costo,
            (float) $producto->stock * (float) $producto->precio_compra,
            (float) $producto->precio_venta,
        ])->all();

        return [$headers, $rows];
    }

    private function lotes(array $data): array
    {
        $headers = ['Producto', 'Lote', 'Vence', 'Días', 'Estado', 'Disponible', 'Costo promedio', 'Valor'];

        $rows = collect($data['lotes'])->map(fn ($lote) => [
            $lote->producto?->nombre,
            $lote->codigo_lote,
            $lote->fecha_vencimiento?->format('d/m/Y'),
            $lote->dias_para_vencer,
            $lote->estado_visual,
            (float) $lote->cantidad_disponible_base,
            (float) $lote->costo_promedio,
            (float) $lote->valor_disponible,
        ])->all();

        return [$headers, $rows];
    }

    private function movimientos(array $data): array
    {
        $headers = ['Fecha', 'Producto', 'Tipo', 'Referencia', 'Lote', 'Cantidad', 'Costo unitario', 'Valor', 'Stock anterior', 'Stock nuevo', 'Usuario'];

        $rows = collect($data['movimientos'])->map(fn ($movimiento) => [
            $movimiento->created_at?->format('d/m/Y H:i'),
            $movimiento->producto?->nombre,
            $movimiento->tipo_movimiento,
            $movimiento->referencia_tipo.' #'.$movimiento->referencia_id,
            $movimiento->lote_codigo,
            (float) $movimiento->cantidad,
            (float) ($movimiento->costo_unitario_base ?? 0),
            abs((float) $movimiento->cantidad) * (float) ($movimiento->costo_unitario_base ?? 0),
            (float) $movimiento->stock_anterior,
            (float) $movimiento->stock_nuevo,
            $movimiento->usuario?->name,
        ])->all();

        return [$headers, $rows];
    }

    private function rentabilidad(array $data): array
    {
        $headers = ['Producto', 'Cantidad base', 'Ingresos', 'Costo', 'Margen', 'Margen %'];

        $rows = collect($data['productos'])->map(fn ($producto) => [
            $producto->nombre,
            (float) $producto->cantidad_base,
            (float) $producto->ingresos,
            (float) $producto->costo,
            (float) $producto->margen,
            (float) $producto->margen_pct,
        ])->all();

        return [$headers, $rows];
    }

    private function kardex(array $data): array
    {
        $headers = ['Fecha', 'Tipo', 'Referencia', 'Lote', 'Cantidad', 'Costo unitario', 'Valor', 'Stock anterior', 'Stock nuevo', 'Observación', 'Usuario'];

        $rows = collect($data['movimientos'])->map(fn ($movimiento) => [
            $movimiento->created_at?->format('d/m/Y H:i'),
            $movimiento->tipo_movimiento,
            $movimiento->referencia_tipo.' #'.$movimiento->referencia_id,
            $movimiento->lote_codigo,
            (float) $movimiento->cantidad,
            (float) ($movimiento->costo_unitario_base ?? 0),
            abs((float) $movimiento->cantidad) * (float) ($movimiento->costo_unitario_base ?? 0),
            (float) $movimiento->stock_anterior,
            (float) $movimiento->stock_nuevo,
            $movimiento->observacion,
            $movimiento->usuario?->name,
        ])->all();

        return [$headers, $rows];
    }

    private function clientes(array $data): array
    {
        $headers = ['DNI', 'Cliente', 'Transacciones', 'Total gastado'];

        $rows = collect($data['clientes'])->map(fn ($cliente) => [
            $cliente->dni,
            trim(($cliente->nombre ?? '').' '.($cliente->apellido_paterno ?? '').' '.($cliente->apellido_materno ?? '')),
            (int) $cliente->total_transacciones,
            (float) $cliente->total_gastado,
        ])->all();

        return [$headers, $rows];
    }

    private function general(array $data): array
    {
        $headers = ['Indicador', 'Valor'];

        $rows = [
            ['Mes', $data['nombre_mes'].' '.$data['anio']],
            ['Total ventas', (float) $data['total_ventas']],
            ['Total atenciones', (float) $data['total_atenciones']],
            ['Ingreso por ventas + atenciones', (float) $data['total_ingresos']],
            ['Compras recibidas', (float) $data['total_compras']],
            ['Cantidad de ventas', (int) $data['cant_ventas']],
            ['Cantidad de atenciones', (int) $data['cant_atenciones']],
            ['Cantidad de compras', (int) $data['cant_ingresos']],
            ['Stock crítico', (int) $data['stock_critico']],
            ['Movimientos de caja (ingresos)', (float) $data['total_ingresos_caja']],
            ['Movimientos de caja (egresos)', (float) $data['total_egresos_caja']],
        ];

        foreach ($data['top_productos'] as $producto) {
            $rows[] = ['Top: '.$producto->nombre, (float) $producto->total_vendido];
        }

        return [$headers, $rows];
    }

    private function auditoria(array $data): array
    {
        $headers = ['Fecha', 'Usuario', 'Módulo', 'Acción', 'Registro', 'Descripción', 'IP'];

        $rows = collect($data['registros'])->map(fn ($registro) => [
            $registro->created_at?->format('d/m/Y H:i'),
            $registro->user?->name,
            $registro->modulo,
            $registro->accion,
            $registro->registro_id,
            $registro->descripcion,
            $registro->ip_address,
        ])->all();

        return [$headers, $rows];
    }

    private function pagos(array $data): array
    {
        $headers = ['Método de pago', 'Ventas', 'Atenciones', 'Total', '% del total', 'Pagos'];

        $total = (float) $data['total'];

        $rows = collect($data['metodos'])->map(fn ($metodo) => [
            $metodo->nombre,
            (float) $metodo->total_ventas,
            (float) $metodo->total_atenciones,
            (float) $metodo->total,
            $total > 0 ? round(((float) $metodo->total / $total) * 100, 2) : 0,
            (int) $metodo->pagos,
        ])->all();

        return [$headers, $rows];
    }

    private function ventasProducto(array $data): array
    {
        $headers = ['Producto', 'Presentación', 'Cantidad base', 'Ingresos', 'Costo', 'Margen', 'Margen %', 'Participación %'];

        $rows = collect($data['productos'])->map(fn ($producto) => [
            $producto->producto,
            $producto->presentacion,
            (float) $producto->cantidad_base,
            (float) $producto->ingresos,
            (float) $producto->costo,
            (float) $producto->margen,
            (float) $producto->margen_pct,
            (float) $producto->participacion,
        ])->all();

        return [$headers, $rows];
    }

    private function ventasDiario(array $data): array
    {
        $headers = ['Fecha', 'Tickets', 'Unidades', 'Ingresos', 'Costo', 'Margen', 'Ticket promedio'];

        $rows = collect($data['dias'])->map(fn ($dia) => [
            $dia->fecha,
            $dia->tickets,
            (float) $dia->unidades,
            (float) $dia->ingresos,
            (float) $dia->costo,
            (float) $dia->margen,
            (float) $dia->ticket_promedio,
        ])->all();

        return [$headers, $rows];
    }

    private function ventasVendedor(array $data): array
    {
        $headers = ['Usuario', 'Hora', 'Tickets', 'Unidades', 'Ingresos', 'Costo', 'Margen', 'Ticket promedio'];

        $rows = collect($data['filas'])->map(fn ($fila) => [
            $fila->usuario,
            $fila->hora,
            $fila->tickets,
            (float) $fila->unidades,
            (float) $fila->ingresos,
            (float) $fila->costo,
            (float) $fila->margen,
            (float) $fila->ticket_promedio,
        ])->all();

        return [$headers, $rows];
    }

    private function anulaciones(array $data): array
    {
        $headers = ['Correlativo', 'Fecha venta', 'Fecha anulación', 'Cliente', 'Vendedor', 'Anulada por', 'Monto', 'Motivo'];

        $rows = collect($data['anulaciones'])->map(fn ($venta) => [
            $venta->correlativo,
            $venta->created_at?->format('d/m/Y H:i'),
            $venta->anulada_at?->format('d/m/Y H:i'),
            trim(($venta->cliente?->nombre ?? '').' '.($venta->cliente?->apellido_paterno ?? '')),
            $venta->user?->name,
            $venta->anuladaPor?->name,
            (float) $venta->total,
            $venta->motivo_anulacion,
        ])->all();

        return [$headers, $rows];
    }

    private function comprasProveedor(array $data): array
    {
        $headers = ['Proveedor', 'Compras', 'Total', 'Promedio', 'Desde caja', 'Fuera de caja', 'Participación %', 'Última compra'];

        $rows = collect($data['proveedores'])->map(fn ($proveedor) => [
            $proveedor->razon_social,
            $proveedor->compras,
            (float) $proveedor->total,
            (float) $proveedor->promedio,
            (float) $proveedor->total_caja,
            (float) $proveedor->total_externo,
            (float) $proveedor->participacion,
            $proveedor->ultima_compra,
        ])->all();

        return [$headers, $rows];
    }

    private function costosProducto(array $data): array
    {
        $headers = ['Producto', 'Proveedor', 'Fecha', 'Documento', 'Costo unitario base', 'Costo anterior', 'Variación', 'Variación %'];

        $rows = collect($data['filas'])->map(fn ($fila) => [
            $fila->producto,
            $fila->proveedor,
            $fila->fecha,
            $fila->correlativo,
            (float) $fila->costo_unitario_base,
            $fila->costo_anterior !== null ? (float) $fila->costo_anterior : null,
            $fila->variacion !== null ? (float) $fila->variacion : null,
            $fila->variacion_pct !== null ? (float) $fila->variacion_pct : null,
        ])->all();

        return [$headers, $rows];
    }

    private function devolucionesProveedor(array $data): array
    {
        $headers = ['Correlativo', 'Fecha', 'Proveedor', 'Motivo', 'Líneas', 'Total', 'Reembolso', 'Método', 'Usuario'];

        $rows = collect($data['devoluciones'])->map(fn ($devolucion) => [
            $devolucion->correlativo,
            $devolucion->fecha?->format('d/m/Y'),
            $devolucion->proveedor?->razon_social,
            $devolucion->motivo,
            $devolucion->detalles->count(),
            (float) $devolucion->total,
            $devolucion->reembolsado_a_caja ? 'Caja' : 'Fuera de caja',
            $devolucion->metodo_pago_label,
            $devolucion->user?->name,
        ])->all();

        return [$headers, $rows];
    }

    private function merma(array $data): array
    {
        $headers = ['Tipo de ajuste', 'Documentos', 'Líneas', 'Productos afectados', 'Costo'];

        $rows = collect($data['tipos'])->map(fn ($tipo) => [
            $tipo->etiqueta,
            $tipo->documentos,
            $tipo->lineas,
            $tipo->productos,
            (float) $tipo->costo,
        ])->all();

        return [$headers, $rows];
    }

    private function rotacion(array $data): array
    {
        $headers = ['Producto', 'Stock', 'Valor stock', 'Vendidos', 'Promedio diario', 'Días cobertura', 'Última venta', 'Clasificación'];

        $rows = collect($data['productos'])->map(fn ($producto) => [
            $producto->nombre,
            (float) $producto->stock,
            (float) $producto->valor_costo,
            (float) $producto->vendidos,
            (float) $producto->promedio_diario,
            $producto->dias_cobertura !== null ? (float) $producto->dias_cobertura : null,
            $producto->ultima_venta,
            $producto->clasificacion,
        ])->all();

        return [$headers, $rows];
    }

    private function margenAgrupado(array $data): array
    {
        $headers = ['Agrupación', 'Ingresos', 'Costo', 'Margen', 'Margen %', 'Participación %', 'Operaciones'];

        $rows = collect($data['filas'])->map(fn ($fila) => [
            $fila->nombre,
            (float) $fila->ingresos,
            (float) $fila->costo,
            (float) $fila->margen,
            (float) $fila->margen_pct,
            (float) $fila->participacion,
            $fila->operaciones,
        ])->all();

        return [$headers, $rows];
    }

    private function servicios(array $data): array
    {
        $headers = ['Servicio', 'Cantidad', 'Atenciones', 'Ingresos', 'Costo', 'Margen', 'Margen %', 'Ticket promedio', 'Participación %'];

        $rows = collect($data['servicios'])->map(fn ($servicio) => [
            $servicio->nombre,
            $servicio->cantidad,
            $servicio->atenciones,
            (float) $servicio->ingresos,
            (float) $servicio->costo,
            (float) $servicio->margen,
            (float) $servicio->margen_pct,
            (float) $servicio->ticket_promedio,
            (float) $servicio->participacion,
        ])->all();

        return [$headers, $rows];
    }

    private function clientesRfm(array $data): array
    {
        $headers = ['Cliente', 'DNI', 'Última compra', 'Días sin comprar', 'Frecuencia', 'Monto', 'Ticket promedio', 'Segmento'];

        $rows = collect($data['clientes'])->map(fn ($cliente) => [
            $cliente->nombre,
            $cliente->dni,
            $cliente->ultima_compra,
            $cliente->dias_sin_comprar,
            $cliente->frecuencia,
            (float) $cliente->monto,
            (float) $cliente->ticket_promedio,
            $cliente->segmento,
        ])->all();

        return [$headers, $rows];
    }

    private function clientesWeb(array $data): array
    {
        $headers = ['Cliente', 'DNI', 'Email', 'Teléfono', 'Dirección', 'Origen', 'Registro', 'Último login'];

        $rows = collect($data['clientes'])->map(fn ($cliente) => [
            $cliente->nombre,
            $cliente->dni,
            $cliente->email,
            $cliente->telefono,
            $cliente->direccion,
            $cliente->registro_web ? 'Web' : 'Presencial',
            $cliente->created_at?->format('d/m/Y H:i'),
            $cliente->ultimo_login?->format('d/m/Y H:i'),
        ])->all();

        return [$headers, $rows];
    }

    private function sugeridoCompra(array $data): array
    {
        $headers = ['Producto', 'Proveedor', 'Lead time', 'Stock', 'Mínimo', 'Máximo', 'Vendidos', 'Promedio diario', 'Días cobertura', 'Sugerido'];

        $rows = collect($data['productos'])->map(fn ($producto) => [
            $producto->nombre,
            $producto->proveedor,
            $producto->lead_time,
            (float) $producto->stock,
            (float) $producto->stock_minimo,
            $producto->stock_maximo !== null ? (float) $producto->stock_maximo : null,
            (float) $producto->vendidos,
            (float) $producto->promedio_diario,
            $producto->dias_cobertura !== null ? (float) $producto->dias_cobertura : null,
            (float) $producto->sugerido,
        ])->all();

        return [$headers, $rows];
    }

    private function cumpleanos(array $data): array
    {
        $headers = ['Cliente', 'DNI', 'Teléfono', 'Email', 'Dirección', 'Nacimiento', 'Próximo cumpleaños', 'Días', 'Edad que cumple'];

        $rows = collect($data['clientes'])->map(fn ($cliente) => [
            $cliente->nombre,
            $cliente->dni,
            $cliente->telefono,
            $cliente->email,
            $cliente->direccion,
            $cliente->fecha_nacimiento?->format('Y-m-d'),
            $cliente->proximo_cumpleanos?->format('Y-m-d'),
            $cliente->dias_faltantes,
            $cliente->edad_cumple,
        ])->all();

        return [$headers, $rows];
    }

    private function cajaOrigen(array $data): array
    {
        $headers = ['Categoría', 'Ingresos', 'Egresos', 'Neto', 'Movimientos'];

        $rows = collect($data['filas'])->map(fn ($fila) => [
            $fila->etiqueta,
            (float) $fila->ingresos,
            (float) $fila->egresos,
            (float) $fila->neto,
            $fila->movimientos,
        ])->all();

        return [$headers, $rows];
    }

    private function resumenGerencial(array $data): array
    {
        $headers = ['Indicador', 'Valor'];

        $rows = [
            ['Ingreso total', (float) $data['ingreso_total']],
            ['Ventas', (float) $data['ventas']['total_general']],
            ['Atenciones', (float) $data['atenciones']['total_general']],
            ['Margen bruto', (float) $data['margen_total']],
            ['Compras', (float) $data['compras']['total_compras']],
            ['Merma', (float) $data['merma']['total']],
            ['Caja neta', (float) $data['caja']['total_neto']],
            ['Ticket promedio', (float) $data['ticket_promedio']],
            ['Transacciones', (int) $data['transacciones']],
            ['Clientes únicos', (int) $data['clientes_unicos']],
            ['Stock crítico', (int) $data['stock_critico']],
            ['Lotes vencidos', (int) $data['lotes_vencidos']['total_lotes']],
            ['Valor vencido', (float) $data['lotes_vencidos']['total_valor_vencido']],
            ['Anulaciones', (int) $data['anulaciones']['cantidad']],
            ['Monto anulado', (float) $data['anulaciones']['total']],
        ];

        foreach ($data['top_productos'] as $producto) {
            $rows[] = ['Top: '.$producto->producto, (float) $producto->ingresos];
        }

        return [$headers, $rows];
    }
}
