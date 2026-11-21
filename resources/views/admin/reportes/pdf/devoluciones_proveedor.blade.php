@php
    $columnas = [
        ['label' => 'Correlativo'],
        ['label' => 'Fecha'],
        ['label' => 'Proveedor'],
        ['label' => 'Motivo'],
        ['label' => 'Líneas', 'align' => 'text-right'],
        ['label' => 'Total', 'align' => 'text-right'],
        ['label' => 'Reembolso'],
        ['label' => 'Método'],
        ['label' => 'Usuario'],
    ];

    $filas = $devoluciones->map(fn ($devolucion) => [
        $devolucion->correlativo,
        $devolucion->fecha?->format('d/m/Y'),
        $devolucion->proveedor?->razon_social ?? '-',
        $devolucion->motivo,
        $devolucion->detalles->count(),
        'S/ '.number_format((float) $devolucion->total, 2),
        $devolucion->reembolsado_a_caja ? 'Caja' : 'Fuera de caja',
        $devolucion->metodo_pago_label ?? '-',
        $devolucion->user?->name ?? '-',
    ])->all();

    $resumen = [
        ['label' => 'Devoluciones', 'value' => $cantidad],
        ['label' => 'Total devuelto', 'value' => 'S/ '.number_format((float) $total, 2)],
    ];

    $totales = ['Totales', '', '', '', '', 'S/ '.number_format((float) $total, 2), '', '', ''];

    $subtitulo = 'Período: '.$desde.' al '.$hasta;
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
