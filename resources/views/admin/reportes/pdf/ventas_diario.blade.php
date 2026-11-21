@php
    $columnas = [
        ['label' => 'Fecha'],
        ['label' => 'Tickets', 'align' => 'text-right'],
        ['label' => 'Unidades', 'align' => 'text-right'],
        ['label' => 'Ingresos', 'align' => 'text-right'],
        ['label' => 'Costo', 'align' => 'text-right'],
        ['label' => 'Margen', 'align' => 'text-right'],
        ['label' => 'Ticket promedio', 'align' => 'text-right'],
    ];

    $filas = $dias->map(fn ($dia) => [
        \Carbon\Carbon::parse($dia->fecha)->format('d/m/Y'),
        $dia->tickets,
        \App\Helpers\CantidadHelper::formatear($dia->unidades),
        'S/ '.number_format((float) $dia->ingresos, 2),
        'S/ '.number_format((float) $dia->costo, 2),
        'S/ '.number_format((float) $dia->margen, 2),
        'S/ '.number_format((float) $dia->ticket_promedio, 2),
    ])->all();

    $resumen = [
        ['label' => 'Días', 'value' => $dias->count()],
        ['label' => 'Tickets', 'value' => $total_tickets],
        ['label' => 'Ingresos', 'value' => 'S/ '.number_format((float) $total_ingresos, 2)],
        ['label' => 'Margen', 'value' => 'S/ '.number_format((float) $total_margen, 2)],
        ['label' => 'Ticket promedio', 'value' => 'S/ '.number_format((float) $ticket_promedio, 2)],
    ];

    $totales = [
        'Totales',
        $total_tickets,
        \App\Helpers\CantidadHelper::formatear($dias->sum('unidades')),
        'S/ '.number_format((float) $total_ingresos, 2),
        'S/ '.number_format((float) $total_costo, 2),
        'S/ '.number_format((float) $total_margen, 2),
        'S/ '.number_format((float) $ticket_promedio, 2),
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta;
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
