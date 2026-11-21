<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Compras' }}</title>
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
        .section-title { font-size: 11pt; font-weight: bold; color: #065f46; margin: 15px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #bbf7d0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        @include('admin.reportes.pdf.partials.negocio')
        <p>
            Período: {{ $desde }} al {{ $hasta }}
            | Pago: {{ $pago === 'caja' ? 'Desde caja' : ($pago === 'externo' ? 'Fuera de caja' : 'Todos') }}
            | Emitido: {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Compras</div>
            <div class="value">{{ $cantidad }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Monto total</div>
            <div class="value">S/ {{ number_format((float) $total_compras, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Desde caja ({{ $cantidad_caja }})</div>
            <div class="value">S/ {{ number_format((float) $total_caja, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Fuera de caja ({{ $cantidad_externo }})</div>
            <div class="value">S/ {{ number_format((float) $total_externo, 2) }}</div>
        </div>
    </div>

    <div class="summary" style="margin-top:-6px;">
        <div class="summary-card">
            <div class="label">Devoluciones ({{ $cantidad_devoluciones }})</div>
            <div class="value">S/ {{ number_format((float) $total_devoluciones, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Anuladas ({{ $cantidad_anuladas }})</div>
            <div class="value">S/ {{ number_format((float) $total_anuladas, 2) }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Compra neta</div>
            <div class="value">S/ {{ number_format((float) $total_neto, 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Documento</th>
                <th>Proveedor</th>
                <th>Usuario</th>
                <th class="text-right">Total</th>
                <th>Pago</th>
                <th>Productos recibidos</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ingresos as $i => $ingreso)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $ingreso->correlativo }}</td>
                <td>{{ $ingreso->fecha ? \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') : '-' }}</td>
                <td>{{ $ingreso->tipo_documento_label ?? '-' }} {{ $ingreso->numero_documento }}</td>
                <td>{{ $ingreso->proveedor?->razon_social ?? '-' }}</td>
                <td>{{ $ingreso->user?->name ?? '-' }}</td>
                <td class="text-right">S/ {{ number_format((float) $ingreso->total, 2) }}</td>
                <td>
                    {{ $ingreso->pagado_desde_caja ? 'Caja' : 'Fuera de caja' }}
                    @if ($ingreso->metodo_pago_label)
                        <div style="color:#666;">{{ $ingreso->metodo_pago_label }}</div>
                    @endif
                </td>
                <td>
                    @foreach ($ingreso->detalles as $detalle)
                        <div class="mb-1">
                            {{ $detalle->producto?->nombre ?? '-' }}
                            <span class="badge" style="background:#e0e7ff;color:#3730a3;">
                                x{{ \App\Helpers\CantidadHelper::formatear($detalle->cantidad) }}
                                {{ $detalle->presentacion?->nombre_completo }}
                                ({{ \App\Helpers\CantidadHelper::formatear($detalle->cantidad_base) }} base)
                            </span>
                            <span class="badge" style="background:#fef3c7;color:#92400e;">
                                Lote {{ $detalle->lote_codigo }}
                            </span>
                        </div>
                    @endforeach
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center" style="color:#999;">No hay compras en el período seleccionado.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6" class="text-right">Total comprado</td>
                <td class="text-right">S/ {{ number_format((float) $total_compras, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    @if ($devoluciones->isNotEmpty())
    <div class="section-title">Devoluciones a proveedor</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Proveedor</th>
                <th>Motivo</th>
                <th class="text-right">Total</th>
                <th>Reembolso</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($devoluciones as $i => $devolucion)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $devolucion->correlativo }}</td>
                <td>{{ $devolucion->fecha?->format('d/m/Y') }}</td>
                <td>{{ $devolucion->proveedor?->razon_social ?? '-' }}</td>
                <td>{{ Str::limit($devolucion->motivo, 50) }}</td>
                <td class="text-right">S/ {{ number_format((float) $devolucion->total, 2) }}</td>
                <td>{{ $devolucion->reembolsado_a_caja ? 'Caja' : 'Fuera de caja' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" class="text-right">Total devuelto</td>
                <td class="text-right">S/ {{ number_format((float) $total_devoluciones, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    @endif

    @if ($anuladas->isNotEmpty())
    <div class="section-title">Compras anuladas</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Proveedor</th>
                <th>Motivo</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($anuladas as $i => $anulada)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $anulada->correlativo }}</td>
                <td>{{ $anulada->fecha?->format('d/m/Y') }}</td>
                <td>{{ $anulada->proveedor?->razon_social ?? '-' }}</td>
                <td>{{ Str::limit($anulada->motivo_anulacion, 50) }}</td>
                <td class="text-right">S/ {{ number_format((float) $anulada->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
@include('admin.reportes.pdf.partials.paginacion')
</body>
</html>
