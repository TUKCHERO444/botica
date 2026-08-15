<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Clientes' }}</title>
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
            <div class="label">Top</div>
            <div class="value">{{ $limite }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Clientes</div>
            <div class="value">{{ $clientes->count() }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>DNI</th>
                <th>Nombre Completo</th>
                <th class="text-right">Transacciones</th>
                <th class="text-right">Total Gastado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clientes as $i => $c)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $c->dni ?? '-' }}</td>
                <td>{{ $c->nombre }} {{ $c->apellido_paterno }} {{ $c->apellido_materno }}</td>
                <td class="text-right">{{ $c->total_transacciones }}</td>
                <td class="text-right">S/ {{ number_format($c->total_gastado, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center" style="color:#999;">No hay clientes con transacciones en el período.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" class="text-right">Total General</td>
                <td class="text-right">{{ $clientes->sum('total_transacciones') }}</td>
                <td class="text-right">S/ {{ number_format($clientes->sum('total_gastado'), 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Página {PAGE_NUM} de {PAGE_COUNT} | Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
</body>
</html>
