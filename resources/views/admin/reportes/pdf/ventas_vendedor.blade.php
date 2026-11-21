@php
    $columnas = [
        ['label' => 'Usuario'],
        ['label' => 'Hora', 'align' => 'text-center'],
        ['label' => 'Tickets', 'align' => 'text-right'],
        ['label' => 'Unidades', 'align' => 'text-right'],
        ['label' => 'Ingresos', 'align' => 'text-right'],
        ['label' => 'Costo', 'align' => 'text-right'],
        ['label' => 'Margen', 'align' => 'text-right'],
        ['label' => 'Ticket promedio', 'align' => 'text-right'],
    ];

    $totalUnidades = $filas->sum('unidades');

    $filas = $filas->map(fn ($fila) => [
        $fila->usuario,
        str_pad($fila->hora, 2, '0', STR_PAD_LEFT).':00',
        $fila->tickets,
        \App\Helpers\CantidadHelper::formatear($fila->unidades),
        'S/ '.number_format((float) $fila->ingresos, 2),
        'S/ '.number_format((float) $fila->costo, 2),
        'S/ '.number_format((float) $fila->margen, 2),
        'S/ '.number_format((float) $fila->ticket_promedio, 2),
    ])->all();

    $resumen = [
        ['label' => 'Tickets', 'value' => $total_tickets],
        ['label' => 'Ingresos', 'value' => 'S/ '.number_format((float) $total_ingresos, 2)],
        ['label' => 'Margen', 'value' => 'S/ '.number_format((float) $total_margen, 2)],
    ];

    $totales = [
        'Totales',
        '',
        $total_tickets,
        \App\Helpers\CantidadHelper::formatear($totalUnidades),
        'S/ '.number_format((float) $total_ingresos, 2),
        'S/ '.number_format((float) $total_costo, 2),
        'S/ '.number_format((float) $total_margen, 2),
        '',
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta;
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
