<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte General' }}</title>
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
        .summary-grid { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
        .summary-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 14px; text-align: center; flex: 1; min-width: 120px; }
        .summary-card .lbl { font-size: 7pt; color: #666; text-transform: uppercase; }
        .summary-card .val { font-size: 11pt; font-weight: bold; color: #065f46; }
        .summary-card .val.negative { color: #dc2626; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        <p>{{ ucfirst($nombre_mes) }} {{ $anio }} | {{ $inicio_mes }} al {{ $fin_mes }} | Emitido: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="section-title">Resumen Financiero</div>
    <div class="summary-grid">
        <div class="summary-card">
            <div class="lbl">Ventas</div>
            <div class="val">S/ {{ number_format($total_ventas, 2) }}</div>
            <div style="font-size:7pt;">{{ $cant_ventas }} transacciones</div>
        </div>
        <div class="summary-card">
            <div class="lbl">Atenciones</div>
            <div class="val">S/ {{ number_format($total_atenciones, 2) }}</div>
            <div style="font-size:7pt;">{{ $cant_atenciones }} transacciones</div>
        </div>
        <div class="summary-card">
            <div class="lbl">Total Ingresos</div>
            <div class="val">S/ {{ number_format($total_ingresos, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="lbl">Ingresos a Caja</div>
            <div class="val">S/ {{ number_format($total_ingresos_caja, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="lbl">Egresos de Caja</div>
            <div class="val negative">S/ {{ number_format($total_egresos_caja, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="lbl">Stock Crítico</div>
            <div class="val" style="color:#dc2626;">{{ $stock_critico }} productos</div>
        </div>
        <div class="summary-card">
            <div class="lbl">Ingresos (Compras)</div>
            <div class="val">{{ $cant_ingresos }} compras</div>
        </div>
    </div>

    <div class="section-title">Top 10 Productos Más Vendidos</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th class="text-right">Total Vendido</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($top_productos as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->nombre }}</td>
                <td class="text-right">{{ $item->total_vendido }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="text-center" style="color:#999;">No hay ventas en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Página {PAGE_NUM} de {PAGE_COUNT} | Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
</body>
</html>
