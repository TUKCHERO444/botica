<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: sans-serif; font-size: 9pt; color: #1e1e1e; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #065f46; }
        .header h1 { color: #065f46; font-size: 16pt; margin: 0 0 4px; }
        .header p { color: #666; font-size: 8pt; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th { background: #065f46; color: white; padding: 6px 8px; text-align: left; font-size: 8pt; text-transform: uppercase; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; font-size: 8pt; }
        tr:nth-child(even) { background: #f9fafb; }
        .total-row td { font-weight: bold; border-top: 2px solid #065f46; background: #ecfdf5; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 7pt; color: #999; border-top: 1px solid #ddd; padding-top: 4px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary { display: flex; justify-content: space-between; margin-bottom: 15px; gap: 6px; }
        .summary-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 10px; text-align: center; flex: 1; }
        .summary-card .label { font-size: 7pt; color: #666; text-transform: uppercase; }
        .summary-card .value { font-size: 11pt; font-weight: bold; color: #065f46; }
        .nota { font-size: 7pt; color: #666; margin-top: -6px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        @include('admin.reportes.pdf.partials.negocio')
        <p>{{ $subtitulo ?? '' }} | Emitido: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @if (! empty($resumen))
    <div class="summary">
        @foreach ($resumen as $item)
        <div class="summary-card">
            <div class="label">{{ $item['label'] }}</div>
            <div class="value">{{ $item['value'] }}</div>
        </div>
        @endforeach
    </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                <th class="{{ $columna['align'] ?? '' }}">{{ $columna['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
            <tr>
                @foreach ($fila as $indice => $celda)
                <td class="{{ $columnas[$indice]['align'] ?? '' }}">{{ $celda }}</td>
                @endforeach
            </tr>
            @empty
            <tr>
                <td colspan="{{ count($columnas) }}" class="text-center" style="color:#999;">
                    No hay registros para los filtros seleccionados.
                </td>
            </tr>
            @endforelse
        </tbody>
        @if (! empty($totales))
        <tfoot>
            <tr class="total-row">
                @foreach ($totales as $indice => $total)
                <td class="{{ $columnas[$indice]['align'] ?? '' }}">{{ $total }}</td>
                @endforeach
            </tr>
        </tfoot>
        @endif
    </table>

    @if (! empty($nota))
    <p class="nota">{{ $nota }}</p>
    @endif

    <div class="footer">
        Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>

    @include('admin.reportes.pdf.partials.paginacion')
</body>
</html>
