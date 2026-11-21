@php
    $columnas = [
        ['label' => 'Producto'],
        ['label' => 'Proveedor'],
        ['label' => 'Fecha'],
        ['label' => 'Documento'],
        ['label' => 'Costo unitario base', 'align' => 'text-right'],
        ['label' => 'Costo anterior', 'align' => 'text-right'],
        ['label' => 'Variación', 'align' => 'text-right'],
        ['label' => 'Variación %', 'align' => 'text-right'],
    ];

    $filas = $filas->map(fn ($fila) => [
        $fila->producto,
        $fila->proveedor ?? '-',
        $fila->fecha ? \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y') : '-',
        $fila->correlativo ?? '-',
        'S/ '.number_format((float) $fila->costo_unitario_base, 4),
        $fila->costo_anterior !== null ? 'S/ '.number_format((float) $fila->costo_anterior, 4) : '-',
        $fila->variacion !== null ? 'S/ '.number_format((float) $fila->variacion, 4) : '-',
        $fila->variacion_pct !== null ? number_format((float) $fila->variacion_pct, 2).'%' : '-',
    ])->all();

    $resumen = [
        ['label' => 'Registros', 'value' => $cantidad],
        ['label' => 'Productos', 'value' => collect($filas)->pluck(0)->unique()->count()],
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta;
    $nota = 'La variación compara el costo de la línea con el último costo registrado del mismo producto.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
