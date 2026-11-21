<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Lotes y Costos' }}</title>
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
        .critico { color: #dc2626; font-weight: bold; }
        .proximo { color: #d97706; font-weight: bold; }
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
            Corte: {{ now()->format('d/m/Y H:i') }}
            @if ($dias_vencimiento === 'vencidos')
                | Solo lotes vencidos con stock
            @elseif ($dias_vencimiento)
                | Próximos {{ $dias_vencimiento }} días
            @endif
        </p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Lotes</div>
            <div class="value">{{ $total_lotes }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Valor total</div>
            <div class="value">S/ {{ number_format((float) $total_valor, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Valor vencido</div>
            <div class="value" style="color:#dc2626;">S/ {{ number_format((float) $total_valor_vencido, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Riesgo próximos 90 días</div>
            <div class="value" style="color:#d97706;">S/ {{ number_format((float) $total_valor_riesgo, 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Lote</th>
                <th>Vence</th>
                <th class="text-center">Días</th>
                <th class="text-center">Estado</th>
                <th class="text-right">Disponible</th>
                <th class="text-right">Costo prom.</th>
                <th class="text-right">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lotes as $i => $lote)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $lote->producto?->nombre ?? '-' }}</td>
                <td>{{ $lote->codigo_lote }}</td>
                <td>{{ $lote->fecha_vencimiento?->format('d/m/Y') }}</td>
                <td class="text-center">{{ $lote->dias_para_vencer }}</td>
                <td class="text-center {{ $lote->estado_visual === 'critico' || $lote->estado_visual === 'vencido' ? 'critico' : ($lote->estado_visual === 'proximo' ? 'proximo' : '') }}">
                    {{ ucfirst($lote->estado_visual) }}
                </td>
                <td class="text-right">{{ \App\Helpers\CantidadHelper::formatear($lote->cantidad_disponible_base) }}</td>
                <td class="text-right">S/ {{ number_format((float) $lote->costo_promedio, 4) }}</td>
                <td class="text-right">S/ {{ number_format((float) $lote->valor_disponible, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center" style="color:#999;">No hay lotes con stock disponible.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6" class="text-right">Totales</td>
                <td class="text-right">{{ \App\Helpers\CantidadHelper::formatear($total_disponible) }}</td>
                <td></td>
                <td class="text-right">S/ {{ number_format((float) $total_valor, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
@include('admin.reportes.pdf.partials.paginacion')
</body>
</html>
