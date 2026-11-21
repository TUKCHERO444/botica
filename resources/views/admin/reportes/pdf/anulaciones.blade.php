@php
    $columnas = [
        ['label' => 'Correlativo'],
        ['label' => 'Fecha venta'],
        ['label' => 'Fecha anulación'],
        ['label' => 'Cliente'],
        ['label' => 'Vendedor'],
        ['label' => 'Anulada por'],
        ['label' => 'Monto', 'align' => 'text-right'],
        ['label' => 'Motivo'],
    ];

    $filas = $anulaciones->map(fn ($venta) => [
        $venta->correlativo,
        $venta->created_at?->format('d/m/Y H:i'),
        $venta->anulada_at?->format('d/m/Y H:i'),
        trim(($venta->cliente?->nombre ?? '').' '.($venta->cliente?->apellido_paterno ?? '')),
        $venta->user?->name,
        $venta->anuladaPor?->name,
        'S/ '.number_format((float) $venta->total, 2),
        $venta->motivo_anulacion,
    ])->all();

    $resumen = [
        ['label' => 'Anulaciones', 'value' => $cantidad],
        ['label' => 'Monto anulado', 'value' => 'S/ '.number_format((float) $total, 2)],
    ];

    $totales = ['Totales', '', '', '', '', '', 'S/ '.number_format((float) $total, 2), ''];

    $subtitulo = 'Período de anulación: '.$desde.' al '.$hasta;
    $nota = 'Solo ventas con estado anulada. El monto es el total original de la venta.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
