@php
    $columnas = [
        ['label' => 'Cliente'],
        ['label' => 'DNI'],
        ['label' => 'Última compra', 'align' => 'text-center'],
        ['label' => 'Días sin comprar', 'align' => 'text-right'],
        ['label' => 'Frecuencia', 'align' => 'text-right'],
        ['label' => 'Monto', 'align' => 'text-right'],
        ['label' => 'Ticket promedio', 'align' => 'text-right'],
        ['label' => 'Segmento'],
    ];

    $filas = $clientes->map(fn ($cliente) => [
        $cliente->nombre,
        $cliente->dni ?? '-',
        $cliente->ultima_compra ? \Carbon\Carbon::parse($cliente->ultima_compra)->format('d/m/Y') : '-',
        $cliente->dias_sin_comprar ?? '-',
        $cliente->frecuencia,
        'S/ '.number_format((float) $cliente->monto, 2),
        'S/ '.number_format((float) $cliente->ticket_promedio, 2),
        $cliente->segmento,
    ])->all();

    $resumen = [
        ['label' => 'Clientes', 'value' => $cantidad],
        ['label' => 'Monto', 'value' => 'S/ '.number_format((float) $total_monto, 2)],
        ['label' => 'Transacciones', 'value' => $total_frecuencia],
        ['label' => 'Segmento', 'value' => $segmento ?: 'Todos'],
    ];

    $subtitulo = 'Período: '.$desde.' al '.$hasta.' | Top '.$limite;
    $nota = 'Nuevo: primera compra en los últimos 30 días. Activo: compra en 30 días. En riesgo: 31-60. Inactivo: 61-90. Perdido: más de 90.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
