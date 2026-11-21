@php
    $columnas = [
        ['label' => 'Tipo de ajuste'],
        ['label' => 'Documentos', 'align' => 'text-right'],
        ['label' => 'Líneas', 'align' => 'text-right'],
        ['label' => 'Productos afectados', 'align' => 'text-right'],
        ['label' => 'Costo', 'align' => 'text-right'],
    ];

    $filas = $tipos->map(fn ($tipo) => [
        $tipo->etiqueta,
        $tipo->documentos,
        $tipo->lineas,
        $tipo->productos,
        'S/ '.number_format((float) $tipo->costo, 2),
    ])->all();

    $resumen = [
        ['label' => 'Documentos', 'value' => $cantidad],
        ['label' => 'Costo total', 'value' => 'S/ '.number_format((float) $total, 2)],
    ];

    $totales = [
        'Totales',
        $cantidad,
        $tipos->sum('lineas'),
        $tipos->sum('productos'),
        'S/ '.number_format((float) $total, 2),
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta.($tipo ? ' | Tipo: '.(App\Models\AjusteInventario::TIPOS[$tipo] ?? $tipo) : '');
    $nota = 'Incluye todos los ajustes de inventario. El costo es el valor de las capas afectadas.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
