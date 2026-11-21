@php
    $columnas = [
        ['label' => 'Servicio'],
        ['label' => 'Cantidad', 'align' => 'text-right'],
        ['label' => 'Atenciones', 'align' => 'text-right'],
        ['label' => 'Ingresos', 'align' => 'text-right'],
        ['label' => 'Costo', 'align' => 'text-right'],
        ['label' => 'Margen', 'align' => 'text-right'],
        ['label' => 'Margen %', 'align' => 'text-right'],
        ['label' => 'Ticket promedio', 'align' => 'text-right'],
        ['label' => 'Participación', 'align' => 'text-right'],
    ];

    $filas = $servicios->map(fn ($servicio) => [
        $servicio->nombre,
        $servicio->cantidad,
        $servicio->atenciones,
        'S/ '.number_format((float) $servicio->ingresos, 2),
        'S/ '.number_format((float) $servicio->costo, 2),
        'S/ '.number_format((float) $servicio->margen, 2),
        number_format((float) $servicio->margen_pct, 2).'%',
        'S/ '.number_format((float) $servicio->ticket_promedio, 2),
        number_format((float) $servicio->participacion, 2).'%',
    ])->all();

    $resumen = [
        ['label' => 'Servicios', 'value' => $cantidad],
        ['label' => 'Ingresos', 'value' => 'S/ '.number_format((float) $total, 2)],
        ['label' => 'Costo', 'value' => 'S/ '.number_format((float) $total_costo, 2)],
        ['label' => 'Margen', 'value' => 'S/ '.number_format((float) $total_margen, 2)],
    ];

    $totales = [
        'Totales',
        $servicios->sum('cantidad'),
        $servicios->sum('atenciones'),
        'S/ '.number_format((float) $total, 2),
        'S/ '.number_format((float) $total_costo, 2),
        'S/ '.number_format((float) $total_margen, 2),
        '',
        '',
        '100.00%',
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta;
    $nota = 'El margen usa el costo registrado del servicio. Si el costo está vacío, el margen aparece al 100%.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
