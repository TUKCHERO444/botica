@php
    $columnas = [
        ['label' => 'Producto'],
        ['label' => 'Stock', 'align' => 'text-right'],
        ['label' => 'Valor stock', 'align' => 'text-right'],
        ['label' => 'Vendidos ('.$dias.'d)', 'align' => 'text-right'],
        ['label' => 'Promedio diario', 'align' => 'text-right'],
        ['label' => 'Días cobertura', 'align' => 'text-right'],
        ['label' => 'Última venta', 'align' => 'text-center'],
        ['label' => 'Clasificación'],
    ];

    $filas = $productos->map(fn ($producto) => [
        $producto->nombre,
        \App\Helpers\CantidadHelper::formatear($producto->stock),
        'S/ '.number_format((float) $producto->valor_costo, 2),
        \App\Helpers\CantidadHelper::formatear($producto->vendidos),
        number_format((float) $producto->promedio_diario, 3),
        $producto->dias_cobertura !== null ? number_format((float) $producto->dias_cobertura, 1) : '-',
        $producto->ultima_venta ? \Carbon\Carbon::parse($producto->ultima_venta)->format('d/m/Y') : '-',
        $producto->clasificacion,
    ])->all();

    $resumen = [
        ['label' => 'SKU', 'value' => $total_sku],
        ['label' => 'Valor stock', 'value' => 'S/ '.number_format((float) $total_valor, 2)],
        ['label' => 'Sin movimiento', 'value' => $sin_movimiento],
        ['label' => 'Ventana', 'value' => $dias.' días'],
    ];

    $subtitulo = 'Ventana de ventas: últimos '.$dias.' días | Corte: '.$hasta;
    $nota = 'Días de cobertura = stock actual / promedio diario vendido. Los productos sin ventas no tienen cobertura calculable.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
