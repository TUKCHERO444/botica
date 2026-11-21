<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Inventario' }}</title>
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
        .low-stock { color: #dc2626; font-weight: bold; }
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
        <p>{{ $solo_activos ? 'Solo productos activos' : 'Incluye productos inactivos' }} | Emitido: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Productos</div>
            <div class="value">{{ $cantidad }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Valor a costo real</div>
            <div class="value">S/ {{ number_format((float) $total_valorizado, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Valor a precio compra</div>
            <div class="value">S/ {{ number_format((float) $total_valor_compra, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Diferencia</div>
            <div class="value">S/ {{ number_format((float) $total_valor_compra - (float) $total_valorizado, 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th>Marca</th>
                <th class="text-right">Stock Actual</th>
                <th class="text-right">Costo real prom.</th>
                <th class="text-right">Valor a costo</th>
                <th class="text-right">Valor a compra</th>
                <th class="text-right">Precio Venta</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($productos as $i => $p)
            @php $bajo = $p->stock <= $p->stock_minimo; @endphp
            @php
                $costoPromedio = (float) $p->stock > 0 ? (float) $p->valor_costo / (float) $p->stock : 0;
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="{{ $bajo ? 'low-stock' : '' }}">{{ $p->nombre }}</td>
                <td>{{ $p->categoria?->nombre ?? '-' }}</td>
                <td>{{ $p->marca?->nombre ?? '-' }}</td>
                <td class="text-right {{ $bajo ? 'low-stock' : '' }}">{{ \App\Helpers\CantidadHelper::formatear($p->stock) }} {{ $p->unidadBase?->codigo }}</td>
                <td class="text-right">S/ {{ number_format($costoPromedio, 2) }}</td>
                <td class="text-right">S/ {{ number_format((float) $p->valor_costo, 2) }}</td>
                <td class="text-right">S/ {{ number_format((float) $p->stock * (float) $p->precio_compra, 2) }}</td>
                <td class="text-right">S/ {{ number_format($p->precio_venta, 2) }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center" style="color:#999;">No hay productos registrados.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" class="text-right">Totales</td>
                <td class="text-right">{{ number_format($total_stock) }}</td>
                <td></td>
                <td class="text-right">S/ {{ number_format((float) $total_valorizado, 2) }}</td>
                <td class="text-right">S/ {{ number_format((float) $total_valor_compra, 2) }}</td>
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
