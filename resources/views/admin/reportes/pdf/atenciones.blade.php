<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Atenciones' }}</title>
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
        @include('admin.reportes.pdf.partials.negocio')
        <p>Período: {{ $desde }} al {{ $hasta }} | Emitido: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Total Atenciones</div>
            <div class="value">S/ {{ number_format($total_general, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Cantidad</div>
            <div class="value">{{ $cantidad }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Promedio</div>
            <div class="value">S/ {{ $cantidad > 0 ? number_format($total_general / $cantidad, 2) : '0.00' }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Usuario</th>
                <th>Productos</th>
                <th>Servicios</th>
                <th>Observación</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($atenciones as $i => $a)
            <tr>
                <td>{{ $a->correlativo }}</td>
                <td>{{ $a->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $a->cliente?->nombre }} {{ $a->cliente?->apellido_paterno }}</td>
                <td>{{ $a->user?->name }}</td>
                <td>
                    @foreach ($a->productos as $p)
                        <div class="mb-1">{{ $p->nombre }} <span class="badge" style="background:#e0e7ff;color:#3730a3;">x{{ \App\Helpers\CantidadHelper::formatear($p->pivot->cantidad) }} {{ $p->unidadBase?->codigo }}</span></div>
                    @endforeach
                </td>
                <td>
                    @foreach ($a->servicios as $s)
                        <div class="mb-1">{{ $s->nombre }} <span class="badge" style="background:#fef3c7;color:#92400e;">S/ {{ number_format($s->pivot->subtotal, 2) }}</span></div>
                    @endforeach
                </td>
                <td>{{ Str::limit($a->observacio, 40) }}</td>
                <td class="text-right">S/ {{ number_format($a->total, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center" style="color:#999;">No hay atenciones en el período seleccionado.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="7" class="text-right">Total General</td>
                <td class="text-right">S/ {{ number_format($total_general, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
@include('admin.reportes.pdf.partials.paginacion')
</body>
</html>
