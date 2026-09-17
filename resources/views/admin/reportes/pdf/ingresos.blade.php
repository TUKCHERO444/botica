<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Ingresos' }}</title>
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
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 7pt; }
        .mb-1 { margin-bottom: 4px; }
        .summary { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .summary-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 14px; text-align: center; flex: 1; margin: 0 4px; }
        .summary-card .label { font-size: 7pt; color: #666; text-transform: uppercase; }
        .summary-card .value { font-size: 12pt; font-weight: bold; color: #065f46; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        <p>Período: {{ $desde }} al {{ $hasta }} | Emitido: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Total Ingresos</div>
            <div class="value">{{ $cantidad }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Productos Recibidos</div>
            <div class="value">{{ number_format($total_productos) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Proveedor</th>
                <th>Usuario</th>
                <th>Productos recibidos</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ingresos as $i => $ingreso)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $ingreso->correlativo }}</td>
                <td>{{ $ingreso->fecha ? \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') : '-' }}</td>
                <td>{{ $ingreso->proveedor?->razon_social ?? '-' }}</td>
                <td>{{ $ingreso->user?->name ?? '-' }}</td>
                <td>
                    @foreach ($ingreso->productos as $p)
                        <div class="mb-1">
                            {{ $p->nombre }}
                            <span class="badge" style="background:#e0e7ff;color:#3730a3;">x{{ $p->pivot->cantidad }}</span>
                        </div>
                    @endforeach
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center" style="color:#999;">No hay ingresos en el período seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Página {PAGE_NUM} de {PAGE_COUNT} | Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
</body>
</html>
