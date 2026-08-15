<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Vencimientos' }}</title>
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
        .section-title { font-size: 11pt; font-weight: bold; color: #065f46; margin: 15px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #bbf7d0; }
        .badge-vencido { background: #fef2f2; color: #dc2626; padding: 2px 8px; border-radius: 4px; font-size: 7pt; font-weight: bold; }
        .badge-critico { background: #fff7ed; color: #c2410c; padding: 2px 8px; border-radius: 4px; font-size: 7pt; font-weight: bold; }
        .badge-proximo { background: #fefce8; color: #a16207; padding: 2px 8px; border-radius: 4px; font-size: 7pt; font-weight: bold; }
        .badge-vigente { background: #f0fdf4; color: #16a34a; padding: 2px 8px; border-radius: 4px; font-size: 7pt; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        <p>Total de lotes: {{ $total_lotes }} | Emitido: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @if ($vencidos->isNotEmpty())
    <div class="section-title">Vencidos</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Código Lote</th>
                <th>Fecha Vencimiento</th>
                <th class="text-right">Cantidad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vencidos as $i => $l)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $l->producto?->nombre ?? '-' }}</td>
                <td>{{ $l->codigo_lote ?? '-' }}</td>
                <td>{{ $l->fecha_vencimiento?->format('d/m/Y') ?? '-' }}</td>
                <td class="text-right">{{ $l->cantidad }}</td>
                <td><span class="badge-vencido">Vencido</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if ($criticos->isNotEmpty())
    <div class="section-title">Críticos (≤ 7 días)</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Código Lote</th>
                <th>Fecha Vencimiento</th>
                <th class="text-right">Cantidad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($criticos as $i => $l)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $l->producto?->nombre ?? '-' }}</td>
                <td>{{ $l->codigo_lote ?? '-' }}</td>
                <td>{{ $l->fecha_vencimiento?->format('d/m/Y') ?? '-' }}</td>
                <td class="text-right">{{ $l->cantidad }}</td>
                <td><span class="badge-critico">Crítico</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if ($proximos->isNotEmpty())
    <div class="section-title">Próximos a Vencer</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Código Lote</th>
                <th>Fecha Vencimiento</th>
                <th class="text-right">Cantidad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($proximos as $i => $l)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $l->producto?->nombre ?? '-' }}</td>
                <td>{{ $l->codigo_lote ?? '-' }}</td>
                <td>{{ $l->fecha_vencimiento?->format('d/m/Y') ?? '-' }}</td>
                <td class="text-right">{{ $l->cantidad }}</td>
                <td><span class="badge-proximo">Próximo</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if ($vigentes->isNotEmpty())
    <div class="section-title">Vigentes</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Producto</th>
                <th>Código Lote</th>
                <th>Fecha Vencimiento</th>
                <th class="text-right">Cantidad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vigentes as $i => $l)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $l->producto?->nombre ?? '-' }}</td>
                <td>{{ $l->codigo_lote ?? '-' }}</td>
                <td>{{ $l->fecha_vencimiento?->format('d/m/Y') ?? '-' }}</td>
                <td class="text-right">{{ $l->cantidad }}</td>
                <td><span class="badge-vigente">Vigente</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if ($vencidos->isEmpty() && $criticos->isEmpty() && $proximos->isEmpty() && $vigentes->isEmpty())
    <p style="text-align:center;color:#999;">No hay lotes registrados.</p>
    @endif

    <div class="footer">
        Página {PAGE_NUM} de {PAGE_COUNT} | Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
</body>
</html>
