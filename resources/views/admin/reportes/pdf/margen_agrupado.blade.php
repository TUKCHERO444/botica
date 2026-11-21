@php
    $etiqueta = match ($agrupar) {
        'marca' => 'Marca',
        'vendedor' => 'Vendedor',
        default => 'Categoría',
    };

    $columnas = [
        ['label' => $etiqueta],
        ['label' => 'Ingresos', 'align' => 'text-right'],
        ['label' => 'Costo', 'align' => 'text-right'],
        ['label' => 'Margen', 'align' => 'text-right'],
        ['label' => 'Margen %', 'align' => 'text-right'],
        ['label' => 'Participación', 'align' => 'text-right'],
        ['label' => 'Operaciones', 'align' => 'text-right'],
    ];

    $filas = $filas->map(fn ($fila) => [
        $fila->nombre,
        'S/ '.number_format((float) $fila->ingresos, 2),
        'S/ '.number_format((float) $fila->costo, 2),
        'S/ '.number_format((float) $fila->margen, 2),
        number_format((float) $fila->margen_pct, 2).'%',
        number_format((float) $fila->participacion, 2).'%',
        $fila->operaciones,
    ])->all();

    $resumen = [
        ['label' => 'Ingresos', 'value' => 'S/ '.number_format((float) $total_ingresos, 2)],
        ['label' => 'Costo', 'value' => 'S/ '.number_format((float) $total_costo, 2)],
        ['label' => 'Margen', 'value' => 'S/ '.number_format((float) $total_margen, 2)],
    ];

    $totales = [
        'Totales',
        'S/ '.number_format((float) $total_ingresos, 2),
        'S/ '.number_format((float) $total_costo, 2),
        'S/ '.number_format((float) $total_margen, 2),
        '',
        '100.00%',
        '',
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta.' | Agrupado por: '.strtolower($etiqueta);
    $nota = 'Incluye productos vendidos en ventas y atenciones. El margen es bruto, antes de gastos operativos.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
