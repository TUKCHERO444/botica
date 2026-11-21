@php
    $columnas = [
        ['label' => 'Categoría'],
        ['label' => 'Ingresos', 'align' => 'text-right'],
        ['label' => 'Egresos', 'align' => 'text-right'],
        ['label' => 'Neto', 'align' => 'text-right'],
        ['label' => 'Movimientos', 'align' => 'text-right'],
    ];

    $filas = $filas->map(fn ($fila) => [
        $fila->etiqueta,
        'S/ '.number_format((float) $fila->ingresos, 2),
        'S/ '.number_format((float) $fila->egresos, 2),
        'S/ '.number_format((float) $fila->neto, 2),
        $fila->movimientos,
    ])->all();

    $resumen = [
        ['label' => 'Ingresos', 'value' => 'S/ '.number_format((float) $total_ingresos, 2)],
        ['label' => 'Egresos', 'value' => 'S/ '.number_format((float) $total_egresos, 2)],
        ['label' => 'Neto', 'value' => 'S/ '.number_format((float) $total_neto, 2)],
    ];

    $totales = [
        'Totales',
        'S/ '.number_format((float) $total_ingresos, 2),
        'S/ '.number_format((float) $total_egresos, 2),
        'S/ '.number_format((float) $total_neto, 2),
        '',
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta;
    $nota = 'Agrupa los movimientos de caja por categoría (venta, atención, compra, gastos, retiros, etc.).';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
