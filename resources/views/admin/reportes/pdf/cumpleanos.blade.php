@php
    $columnas = [
        ['label' => 'Cliente'],
        ['label' => 'DNI'],
        ['label' => 'Teléfono'],
        ['label' => 'Email'],
        ['label' => 'Dirección'],
        ['label' => 'Nacimiento', 'align' => 'text-center'],
        ['label' => 'Próximo cumpleaños', 'align' => 'text-center'],
        ['label' => 'Días', 'align' => 'text-right'],
        ['label' => 'Edad que cumple', 'align' => 'text-right'],
    ];

    $filas = $clientes->map(fn ($cliente) => [
        $cliente->nombre,
        $cliente->dni ?? '-',
        $cliente->telefono ?? '-',
        $cliente->email ?? '-',
        $cliente->direccion ?? '-',
        $cliente->fecha_nacimiento?->format('d/m/Y'),
        $cliente->proximo_cumpleanos?->format('d/m/Y'),
        $cliente->dias_faltantes,
        $cliente->edad_cumple,
    ])->all();

    $resumen = [
        ['label' => 'Clientes', 'value' => $cantidad],
        ['label' => 'Próximos', 'value' => $dias.' días'],
        ['label' => 'Hasta', 'value' => $hasta],
    ];

    $subtitulo = 'Cumpleaños en los próximos '.$dias.' días';
    $nota = 'Solo clientes con fecha de nacimiento registrada.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
