<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Resumen Gerencial' }}</title>
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
        .section-title { font-size: 11pt; font-weight: bold; color: #065f46; margin: 15px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #bbf7d0; }
        .summary-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
        .summary-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 10px; text-align: center; flex: 1; min-width: 120px; }
        .summary-card .label { font-size: 7pt; color: #666; text-transform: uppercase; }
        .summary-card .value { font-size: 11pt; font-weight: bold; color: #065f46; }
        .alerta { background: #fef2f2; border-color: #fca5a5; }
        .alerta .value { color: #dc2626; }
        .ok { background: #f0fdf4; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        @include('admin.reportes.pdf.partials.negocio')
        <p>Período: {{ $desde }} al {{ $hasta }} | Emitido: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="section-title">Resumen del período</div>
    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Ingreso total</div>
            <div class="value">S/ {{ number_format((float) $ingreso_total, 2) }}</div>
            <div style="font-size:7pt;">{{ $transacciones }} transacciones</div>
        </div>
        <div class="summary-card">
            <div class="label">Ventas</div>
            <div class="value">S/ {{ number_format((float) $ventas['total_general'], 2) }}</div>
            <div style="font-size:7pt;">{{ $ventas['cantidad'] }} tickets</div>
        </div>
        <div class="summary-card">
            <div class="label">Atenciones</div>
            <div class="value">S/ {{ number_format((float) $atenciones['total_general'], 2) }}</div>
            <div style="font-size:7pt;">{{ $atenciones['cantidad'] }} atenciones</div>
        </div>
        <div class="summary-card">
            <div class="label">Margen bruto</div>
            <div class="value">S/ {{ number_format((float) $margen_total, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Compras</div>
            <div class="value">S/ {{ number_format((float) $compras['total_compras'], 2) }}</div>
            <div style="font-size:7pt;">{{ $compras['cantidad'] }} compras</div>
        </div>
        <div class="summary-card">
            <div class="label">Merma</div>
            <div class="value">S/ {{ number_format((float) $merma['total'], 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Caja neta</div>
            <div class="value">S/ {{ number_format((float) $caja['total_neto'], 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Ticket promedio</div>
            <div class="value">S/ {{ number_format((float) $ticket_promedio, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Clientes únicos</div>
            <div class="value">{{ $clientes_unicos }}</div>
        </div>
    </div>

    <div class="section-title">Top 5 productos por ingreso</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Ingresos</th>
                <th class="text-right">Margen</th>
                <th class="text-right">Margen %</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($top_productos as $i => $producto)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $producto->producto }} ({{ $producto->presentacion }})</td>
                <td class="text-right">{{ \App\Helpers\CantidadHelper::formatear($producto->cantidad_base) }}</td>
                <td class="text-right">S/ {{ number_format((float) $producto->ingresos, 2) }}</td>
                <td class="text-right">S/ {{ number_format((float) $producto->margen, 2) }}</td>
                <td class="text-right">{{ number_format((float) $producto->margen_pct, 2) }}%</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center" style="color:#999;">No hay ventas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Alertas</div>
    <div class="summary-grid">
        <div class="summary-card {{ $stock_critico > 0 ? 'alerta' : 'ok' }}">
            <div class="label">Stock crítico</div>
            <div class="value">{{ $stock_critico }}</div>
            <div style="font-size:7pt;">productos activos</div>
        </div>
        <div class="summary-card {{ $lotes_vencidos['total_lotes'] > 0 ? 'alerta' : 'ok' }}">
            <div class="label">Lotes vencidos</div>
            <div class="value">{{ $lotes_vencidos['total_lotes'] }}</div>
            <div style="font-size:7pt;">S/ {{ number_format((float) $lotes_vencidos['total_valor_vencido'], 2) }} en riesgo</div>
        </div>
        <div class="summary-card {{ $caja_abierta ? 'alerta' : 'ok' }}">
            <div class="label">Caja</div>
            <div class="value">{{ $caja_abierta ? 'Abierta' : 'Cerrada' }}</div>
        </div>
        <div class="summary-card {{ $anulaciones['cantidad'] > 0 ? 'alerta' : 'ok' }}">
            <div class="label">Anulaciones</div>
            <div class="value">{{ $anulaciones['cantidad'] }}</div>
            <div style="font-size:7pt;">S/ {{ number_format((float) $anulaciones['total'], 2) }}</div>
        </div>
    </div>

    <div class="footer">
        Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
    @include('admin.reportes.pdf.partials.paginacion')
</body>
</html>
