@php
    $columnas = [
        ['label' => 'Cliente'],
        ['label' => 'DNI'],
        ['label' => 'Email'],
        ['label' => 'Teléfono'],
        ['label' => 'Dirección'],
        ['label' => 'Origen'],
        ['label' => 'Registro', 'align' => 'text-center'],
        ['label' => 'Último login', 'align' => 'text-center'],
    ];

    $filas = $clientes->map(fn ($cliente) => [
        $cliente->nombre,
        $cliente->dni ?? '-',
        $cliente->email ?? '-',
        $cliente->telefono ?? '-',
        $cliente->direccion ?? '-',
        $cliente->registro_web ? 'Web' : 'Presencial',
        $cliente->created_at?->format('d/m/Y H:i'),
        $cliente->ultimo_login?->format('d/m/Y H:i') ?? '-',
    ])->all();

    $resumen = [
        ['label' => 'Nuevos', 'value' => $total],
        ['label' => 'Web', 'value' => $web],
        ['label' => 'Presencial', 'value' => $presencial],
        ['label' => 'Web con login', 'value' => $activos_web],
    ];

    $subtitulo = 'Altas del período: '.$desde.' al '.$hasta;
    $nota = 'Web: cliente con cuenta creada desde el sitio público. Presencial: creado desde ventas, atenciones o el panel.';
@endphp

@include('admin.reportes.pdf.partials.reporte-tabla')
