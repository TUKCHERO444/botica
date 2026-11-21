<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Caja' }}</title>
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
        .caja-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px; margin-bottom: 15px; }
        .caja-card h3 { margin: 0 0 8px; color: #065f46; font-size: 11pt; }
        .caja-card .grid { display: flex; gap: 10px; flex-wrap: wrap; }
        .caja-card .item { flex: 1; min-width: 100px; }
        .caja-card .item .lbl { font-size: 7pt; color: #666; text-transform: uppercase; }
        .caja-card .item .val { font-size: 10pt; font-weight: bold; color: #1e1e1e; }
        .movimiento-ingreso { color: #065f46; }
        .movimiento-egreso { color: #dc2626; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        @include('admin.reportes.pdf.partials.negocio')
        <p>Emitido: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @php
        $totalInicial = $cajas->sum('monto_inicial');
        $totalIngresos = $cajas->sum('total_ingresos');
        $totalEgresos = $cajas->sum('total_egresos');
        $totalFinal = $cajas->where('estado', 'cerrada')->sum('monto_final');
        $totalEsperado = $cajas->whereNotNull('efectivo_esperado')->sum('efectivo_esperado');
        $totalContado = $cajas->whereNotNull('efectivo_contado')->sum('efectivo_contado');
        $totalDiferencia = $cajas->whereNotNull('diferencia')->sum('diferencia');
    @endphp

    <div class="caja-card">
        <h3>Resumen del período ({{ $cajas->count() }} cajas)</h3>
        <div class="grid">
            <div class="item">
                <div class="lbl">Monto inicial</div>
                <div class="val">S/ {{ number_format((float) $totalInicial, 2) }}</div>
            </div>
            <div class="item">
                <div class="lbl">Ingresos</div>
                <div class="val" style="color:#065f46;">S/ {{ number_format((float) $totalIngresos, 2) }}</div>
            </div>
            <div class="item">
                <div class="lbl">Egresos</div>
                <div class="val" style="color:#dc2626;">S/ {{ number_format((float) $totalEgresos, 2) }}</div>
            </div>
            <div class="item">
                <div class="lbl">Monto final (cajas cerradas)</div>
                <div class="val">S/ {{ number_format((float) $totalFinal, 2) }}</div>
            </div>
            <div class="item">
                <div class="lbl">Efectivo esperado</div>
                <div class="val">S/ {{ number_format((float) $totalEsperado, 2) }}</div>
            </div>
            <div class="item">
                <div class="lbl">Efectivo contado</div>
                <div class="val">S/ {{ number_format((float) $totalContado, 2) }}</div>
            </div>
            <div class="item">
                <div class="lbl">Diferencia de arqueos</div>
                <div class="val" style="color:{{ (float) $totalDiferencia !== 0.0 ? '#dc2626' : '#065f46' }};">S/ {{ number_format((float) $totalDiferencia, 2) }}</div>
            </div>
        </div>
    </div>

    @forelse ($cajas as $caja)
    <div class="caja-card">
        <h3>Caja #{{ $caja->id_caja }} - {{ ucfirst($caja->estado) }}</h3>
        <div class="grid">
            <div class="item">
                <div class="lbl">Apertura</div>
                <div class="val">{{ $caja->apertura_at?->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
            <div class="item">
                <div class="lbl">Cierre</div>
                <div class="val">{{ $caja->cierre_at?->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
            <div class="item">
                <div class="lbl">Apertura por</div>
                <div class="val">{{ $caja->aperturaUser?->name ?? '-' }}</div>
            </div>
            <div class="item">
                <div class="lbl">Cierre por</div>
                <div class="val">{{ $caja->cierreUser?->name ?? '-' }}</div>
            </div>
            <div class="item">
                <div class="lbl">Monto Inicial</div>
                <div class="val">S/ {{ number_format($caja->monto_inicial, 2) }}</div>
            </div>
            <div class="item">
                <div class="lbl">Ingresos</div>
                <div class="val" style="color:#065f46;">S/ {{ number_format($caja->total_ingresos, 2) }}</div>
            </div>
            <div class="item">
                <div class="lbl">Egresos</div>
                <div class="val" style="color:#dc2626;">S/ {{ number_format($caja->total_egresos, 2) }}</div>
            </div>
            <div class="item">
                <div class="lbl">Efectivo Esperado</div>
                <div class="val">{{ $caja->efectivo_esperado !== null ? 'S/ '.number_format((float) $caja->efectivo_esperado, 2) : '—' }}</div>
            </div>
            <div class="item">
                <div class="lbl">Efectivo Contado</div>
                <div class="val">{{ $caja->efectivo_contado !== null ? 'S/ '.number_format((float) $caja->efectivo_contado, 2) : '—' }}</div>
            </div>
            <div class="item">
                <div class="lbl">Diferencia</div>
                <div class="val" style="color:{{ $caja->efectivo_contado !== null && (float) $caja->diferencia !== 0.0 ? '#dc2626' : '#065f46' }};">
                    {{ $caja->efectivo_contado !== null ? 'S/ '.number_format($caja->diferencia_real, 2) : '—' }}
                </div>
            </div>
            <div class="item">
                <div class="lbl">Monto Final (contado)</div>
                <div class="val">
                    {{ $caja->monto_final !== null ? 'S/ '.number_format((float) $caja->monto_final, 2) : '— (caja abierta)' }}
                </div>
            </div>
        </div>

        @if ($caja->movimientos->isNotEmpty())
        <table style="margin-top:10px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tipo</th>
                    <th>Método</th>
                    <th>Monto</th>
                    <th>Categoría</th>
                    <th>Descripción</th>
                    <th>Usuario</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($caja->movimientos as $j => $m)
                <tr>
                    <td>{{ $j + 1 }}</td>
                    <td><span class="{{ $m->tipo == 'ingreso' ? 'movimiento-ingreso' : 'movimiento-egreso' }}">{{ ucfirst($m->tipo) }}</span></td>
                    <td>{{ $m->metodo_label }}{{ $m->afecta_efectivo ? '' : ' (digital)' }}</td>
                    <td>S/ {{ number_format($m->monto, 2) }}</td>
                    <td>{{ $m->categoria_label }}</td>
                    <td>{{ $m->descripcion_visible }}</td>
                    <td>{{ $m->user?->name ?? '-' }}</td>
                    <td>{{ $m->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @empty
    <p style="text-align:center;color:#999;">No hay registros de caja en el período seleccionado.</p>
    @endforelse

    <div class="footer">
        Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
@include('admin.reportes.pdf.partials.paginacion')
</body>
</html>
