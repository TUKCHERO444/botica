<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Movimientos de Inventario' }}</title>
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
        .in { color: #047857; }
        .out { color: #dc2626; }
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
        @include('admin.reportes.pdf.partials.negocio')
        <p>
            Período: {{ $desde }} al {{ $hasta }}
            | Tipo: {{ $tipo ?: 'Todos' }}
            | Emitido: {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Movimientos</div>
            <div class="value">{{ $cantidad }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Entradas</div>
            <div class="value in">{{ $cantidad_entradas }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Salidas</div>
            <div class="value out">{{ $cantidad_salidas }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Valor movido</div>
            <div class="value">S/ {{ number_format((float) $total_costo, 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Tipo</th>
                <th>Referencia</th>
                <th>Lote</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Costo unit.</th>
                <th class="text-right">Valor</th>
                <th class="text-right">Stock anterior</th>
                <th class="text-right">Stock nuevo</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimientos as $movimiento)
            <tr>
                <td>{{ $movimiento->created_at?->format('d/m/Y H:i') }}</td>
                <td>{{ $movimiento->producto?->nombre ?? '-' }}</td>
                <td>{{ $movimiento->tipo_movimiento }}</td>
                <td>{{ $movimiento->referencia_tipo }} #{{ $movimiento->referencia_id }} ({{ $movimiento->referencia_codigo }})</td>
                <td>{{ $movimiento->lote_codigo ?? '-' }}</td>
                <td class="text-right {{ (float) $movimiento->cantidad >= 0 ? 'in' : 'out' }}">
                    {{ (float) $movimiento->cantidad > 0 ? '+' : '' }}{{ \App\Helpers\CantidadHelper::formatear($movimiento->cantidad) }}
                </td>
                <td class="text-right">S/ {{ number_format((float) ($movimiento->costo_unitario_base ?? 0), 4) }}</td>
                <td class="text-right">S/ {{ number_format(abs((float) $movimiento->cantidad) * (float) ($movimiento->costo_unitario_base ?? 0), 2) }}</td>
                <td class="text-right">{{ \App\Helpers\CantidadHelper::formatear($movimiento->stock_anterior) }}</td>
                <td class="text-right">{{ \App\Helpers\CantidadHelper::formatear($movimiento->stock_nuevo) }}</td>
                <td>{{ $movimiento->usuario?->name ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="11" class="text-center" style="color:#999;">No hay movimientos en el período.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="7" class="text-right">Valor movido</td>
                <td class="text-right">S/ {{ number_format((float) $total_costo, 2) }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
@include('admin.reportes.pdf.partials.paginacion')
</body>
</html>
