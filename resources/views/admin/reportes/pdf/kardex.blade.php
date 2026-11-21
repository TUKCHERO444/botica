<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Kardex' }}</title>
    <style>
        body { font-family: sans-serif; font-size: 9pt; color: #1e1e1e; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #065f46; }
        .header h1 { color: #065f46; font-size: 16pt; margin: 0 0 4px; }
        .header p { color: #666; font-size: 8pt; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th { background: #065f46; color: white; padding: 6px 8px; text-align: left; font-size: 8pt; text-transform: uppercase; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; font-size: 8pt; }
        tr:nth-child(even) { background: #f9fafb; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 7pt; color: #999; border-top: 1px solid #ddd; padding-top: 4px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .entrada { color: #065f46; font-weight: bold; }
        .salida { color: #dc2626; font-weight: bold; }
        .producto-info { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px; margin-bottom: 15px; }
        .producto-info .row { display: flex; gap: 20px; flex-wrap: wrap; }
        .producto-info .item { }
        .producto-info .lbl { font-size: 7pt; color: #666; text-transform: uppercase; }
        .producto-info .val { font-size: 10pt; font-weight: bold; color: #1e1e1e; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        @include('admin.reportes.pdf.partials.negocio')
        @if ($desde || $hasta)
        <p>Período: {{ $desde ?? 'Inicio' }} al {{ $hasta ?? 'Actual' }} | Emitido: {{ now()->format('d/m/Y H:i') }}</p>
        @else
        <p>Emitido: {{ now()->format('d/m/Y H:i') }}</p>
        @endif
    </div>

    @if ($producto)
    <div class="producto-info">
        <div class="row">
            <div class="item">
                <div class="lbl">Producto</div>
                <div class="val">{{ $producto->nombre }}</div>
            </div>
            <div class="item">
                <div class="lbl">Stock Actual</div>
                <div class="val">{{ $producto->stock_con_unidad }}</div>
            </div>
            <div class="item">
                <div class="lbl">Precio Venta</div>
                <div class="val">S/ {{ number_format($producto->precio_venta, 2) }}</div>
            </div>
        </div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Referencia</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Costo unit.</th>
                <th class="text-right">Valor</th>
                <th class="text-right">Stock Anterior</th>
                <th class="text-right">Stock Nuevo</th>
                <th>Observación</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimientos as $i => $m)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
                <td><span class="{{ $m->tipo_movimiento }}">{{ ucfirst($m->tipo_movimiento) }}</span></td>
                <td>
                    {{ $m->referencia_tipo }} #{{ $m->referencia_id }} ({{ $m->referencia_codigo ?? '-' }})
                    @if ($m->lote_codigo)
                        <div class="text-muted">Lote {{ $m->lote_codigo }} · vence {{ $m->fecha_vencimiento?->format('d/m/Y') }}</div>
                    @endif
                </td>
                <td class="text-right">{{ \App\Helpers\CantidadHelper::formatear($m->cantidad) }} {{ $m->producto?->unidadBase?->codigo }}</td>
                <td class="text-right">S/ {{ number_format((float) ($m->costo_unitario_base ?? 0), 4) }}</td>
                <td class="text-right">S/ {{ number_format(abs((float) $m->cantidad) * (float) ($m->costo_unitario_base ?? 0), 2) }}</td>
                <td class="text-right">{{ \App\Helpers\CantidadHelper::formatear($m->stock_anterior) }}</td>
                <td class="text-right">{{ \App\Helpers\CantidadHelper::formatear($m->stock_nuevo) }}</td>
                <td>
                    @if ($m->unidad_presentacion)
                        <div><strong>{{ $m->unidad_presentacion }}</strong> ({{ \App\Helpers\CantidadHelper::formatear($m->cantidad_presentacion) }})</div>
                    @endif
                    {{ $m->observacion ?? '-' }}
                </td>
                <td>{{ $m->usuario?->name ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="11" class="text-center" style="color:#999;">No hay movimientos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
@include('admin.reportes.pdf.partials.paginacion')
</body>
</html>
