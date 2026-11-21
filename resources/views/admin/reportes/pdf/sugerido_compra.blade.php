@php
    $columnas = [
        ['label' => 'Producto'],
        ['label' => 'Proveedor'],
        ['label' => 'Lead time', 'align' => 'text-right'],
        ['label' => 'Stock', 'align' => 'text-right'],
        ['label' => 'Mínimo', 'align' => 'text-right'],
        ['label' => 'Máximo', 'align' => 'text-right'],
        ['label' => 'Vendidos ('.$dias.'d)', 'align' => 'text-right'],
        ['label' => 'Promedio diario', 'align' => 'text-right'],
        ['label' => 'Días cobertura', 'align' => 'text-right'],
        ['label' => 'Sugerido', 'align' => 'text-right'],
    ];

    $filas = $productos->map(fn ($producto) => [
        $producto->nombre,
        $producto->proveedor ?? '-',
        $producto->lead_time.' días',
        \App\Helpers\CantidadHelper::formatear($producto->stock),
        \App\Helpers\CantidadHelper::formatear($producto->stock_minimo),
        $producto->stock_maximo !== null ? \App\Helpers\CantidadHelper::formatear($producto->stock_maximo) : '-',
        \App\Helpers\CantidadHelper::formatear($producto->vendidos),
        number_format((float) $producto->promedio_diario, 3),
        $producto->dias_cobertura !== null ? number_format((float) $producto->dias_cobertura, 1) : '-',
        \App\Helpers\CantidadHelper::formatear($producto->sugerido),
    ])->all();

    $resumen = [
        ['label' => 'Productos con sugerido', 'value' => $con_sugerido],
        ['label' => 'Total sugerido', 'value' => \App\Helpers\CantidadHelper::formatear($total_sugerido)],
        ['label' => 'Ventana', 'value' => $dias.' días'],
    ];

    $subtitulo = 'Consumo: últimos '.$dias.' días | Corte: '.$hasta;
    $nota = 'Sugerido = promedio diario × (lead time + 15 días de cobertura) - stock, acotado por stock máximo. Si no hay ventas, se usa stock mínimo - stock.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
