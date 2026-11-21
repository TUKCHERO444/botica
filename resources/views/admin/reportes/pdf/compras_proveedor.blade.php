@php
    $columnas = [
        ['label' => 'Proveedor'],
        ['label' => 'Compras', 'align' => 'text-right'],
        ['label' => 'Total', 'align' => 'text-right'],
        ['label' => 'Promedio', 'align' => 'text-right'],
        ['label' => 'Desde caja', 'align' => 'text-right'],
        ['label' => 'Fuera de caja', 'align' => 'text-right'],
        ['label' => 'Participación', 'align' => 'text-right'],
        ['label' => 'Última compra', 'align' => 'text-center'],
    ];

    $filas = $proveedores->map(fn ($proveedor) => [
        $proveedor->razon_social,
        $proveedor->compras,
        'S/ '.number_format((float) $proveedor->total, 2),
        'S/ '.number_format((float) $proveedor->promedio, 2),
        'S/ '.number_format((float) $proveedor->total_caja, 2),
        'S/ '.number_format((float) $proveedor->total_externo, 2),
        number_format((float) $proveedor->participacion, 2).'%',
        $proveedor->ultima_compra ? \Carbon\Carbon::parse($proveedor->ultima_compra)->format('d/m/Y') : '-',
    ])->all();

    $resumen = [
        ['label' => 'Proveedores', 'value' => $cantidad],
        ['label' => 'Compra total', 'value' => 'S/ '.number_format((float) $total, 2)],
    ];

    $totales = [
        'Totales',
        $proveedores->sum('compras'),
        'S/ '.number_format((float) $total, 2),
        '',
        'S/ '.number_format((float) $proveedores->sum('total_caja'), 2),
        'S/ '.number_format((float) $proveedores->sum('total_externo'), 2),
        '100.00%',
        '',
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta;
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
