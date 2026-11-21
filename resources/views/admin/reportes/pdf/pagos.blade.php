<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Cobros por Método de Pago' }}</title>
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
        .nota { font-size: 7pt; color: #666; margin-top: -6px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        @include('admin.reportes.pdf.partials.negocio')
        <p>
            Período: {{ $desde }} al {{ $hasta }}
            | Origen: {{ $tipo === 'venta' ? 'Solo ventas' : ($tipo === 'atencion' ? 'Solo atenciones' : 'Ventas y atenciones') }}
            | Emitido: {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Total cobrado</div>
            <div class="value">S/ {{ number_format((float) $total, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Ventas</div>
            <div class="value">S/ {{ number_format((float) $total_ventas, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Atenciones</div>
            <div class="value">S/ {{ number_format((float) $total_atenciones, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Pagos registrados</div>
            <div class="value">{{ $cantidad_pagos }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Método de pago</th>
                <th class="text-right">Ventas</th>
                <th class="text-right">Atenciones</th>
                <th class="text-right">Total</th>
                <th class="text-right">% del total</th>
                <th class="text-right">Pagos</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($metodos as $i => $metodo)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $metodo->nombre }}</td>
                <td class="text-right">S/ {{ number_format((float) $metodo->total_ventas, 2) }}</td>
                <td class="text-right">S/ {{ number_format((float) $metodo->total_atenciones, 2) }}</td>
                <td class="text-right">S/ {{ number_format((float) $metodo->total, 2) }}</td>
                <td class="text-right">
                    {{ $total > 0 ? number_format(((float) $metodo->total / $total) * 100, 2) : '0.00' }}%
                </td>
                <td class="text-right">{{ $metodo->pagos }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center" style="color:#999;">No hay cobros en el período seleccionado.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="2" class="text-right">Totales</td>
                <td class="text-right">S/ {{ number_format((float) $total_ventas, 2) }}</td>
                <td class="text-right">S/ {{ number_format((float) $total_atenciones, 2) }}</td>
                <td class="text-right">S/ {{ number_format((float) $total, 2) }}</td>
                <td class="text-right">100.00%</td>
                <td class="text-right">{{ $cantidad_pagos }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="nota">
        Los pagos mixtos se reparten por método. No se incluyen ventas anuladas.
        La caja registra un movimiento por método y el arqueo separa efectivo de digital.
    </p>

    <div class="footer">
        Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
@include('admin.reportes.pdf.partials.paginacion')
</body>
</html>
