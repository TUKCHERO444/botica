@php
    $columnas = [
        ['label' => 'Producto'],
        ['label' => 'Presentación'],
        ['label' => 'Cantidad base', 'align' => 'text-right'],
        ['label' => 'Ingresos', 'align' => 'text-right'],
        ['label' => 'Costo', 'align' => 'text-right'],
        ['label' => 'Margen', 'align' => 'text-right'],
        ['label' => 'Margen %', 'align' => 'text-right'],
        ['label' => 'Participación', 'align' => 'text-right'],
    ];

    $filas = $productos->map(fn ($producto) => [
        $producto->producto,
        $producto->presentacion,
        \App\Helpers\CantidadHelper::formatear($producto->cantidad_base),
        'S/ '.number_format((float) $producto->ingresos, 2),
        'S/ '.number_format((float) $producto->costo, 2),
        'S/ '.number_format((float) $producto->margen, 2),
        number_format((float) $producto->margen_pct, 2).'%',
        number_format((float) $producto->participacion, 2).'%',
    ])->all();

    $resumen = [
        ['label' => 'Productos', 'value' => $cantidad],
        ['label' => 'Ingresos', 'value' => 'S/ '.number_format((float) $total_ingresos, 2)],
        ['label' => 'Costo', 'value' => 'S/ '.number_format((float) $total_costo, 2)],
        ['label' => 'Margen', 'value' => 'S/ '.number_format((float) $total_margen, 2)],
    ];

    $totales = [
        '',
        'Totales',
        \App\Helpers\CantidadHelper::formatear($productos->sum('cantidad_base')),
        'S/ '.number_format((float) $total_ingresos, 2),
        'S/ '.number_format((float) $total_costo, 2),
        'S/ '.number_format((float) $total_margen, 2),
        '',
        '',
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta;
    $nota = 'El margen es bruto: ingresos menos costo real de las capas consumidas.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
