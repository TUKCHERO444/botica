<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Reporte de Auditoría' }}</title>
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
        .badge-crear { background: #f0fdf4; color: #16a34a; padding: 1px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
        .badge-actualizar { background: #fefce8; color: #a16207; padding: 1px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
        .badge-eliminar { background: #fef2f2; color: #dc2626; padding: 1px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
        .diff { font-size: 7pt; color: #666; }
        .diff old { color: #dc2626; }
        .diff new { color: #16a34a; }
        .summary { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .summary-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 14px; text-align: center; flex: 1; margin: 0 4px; }
        .summary-card .label { font-size: 7pt; color: #666; text-transform: uppercase; }
        .summary-card .value { font-size: 12pt; font-weight: bold; color: #065f46; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titulo }}</h1>
        @if ($desde || $hasta)
        <p>Período: {{ $desde ?? 'Inicio' }} al {{ $hasta ?? 'Actual' }} | Emitido: {{ now()->format('d/m/Y H:i') }}</p>
        @else
        <p>Emitido: {{ now()->format('d/m/Y H:i') }}</p>
        @endif
    </div>

    <div class="summary">
        <div class="summary-card">
            <div class="label">Registros</div>
            <div class="value">{{ $cantidad }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Módulo</th>
                <th>Acción</th>
                <th>Registro ID</th>
                <th>Descripción</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $r->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                <td>{{ $r->user?->name ?? '-' }}</td>
                <td>{{ $r->modulo ?? '-' }}</td>
                <td>
                    @switch($r->accion)
                        @case('crear') <span class="badge-crear">Crear</span> @break
                        @case('actualizar') <span class="badge-actualizar">Actualizar</span> @break
                        @case('eliminar') <span class="badge-eliminar">Eliminar</span> @break
                        @default {{ $r->accion }}
                    @endswitch
                </td>
                <td>{{ $r->registro_id ?? '-' }}</td>
                <td>{{ Str::limit($r->descripcion, 60) }}</td>
                <td>{{ $r->ip_address ?? '-' }}</td>
            </tr>
            @if ($r->valores_anteriores || $r->valores_nuevos)
            <tr style="background:#fafafa;">
                <td colspan="8" class="diff">
                    @if ($r->valores_anteriores)
                        <strong style="color:#dc2626;">Anterior:</strong> {{ json_encode($r->valores_anteriores) }}
                    @endif
                    @if ($r->valores_nuevos)
                        | <strong style="color:#16a34a;">Nuevo:</strong> {{ json_encode($r->valores_nuevos) }}
                    @endif
                </td>
            </tr>
            @endif
            @empty
            <tr><td colspan="8" class="text-center" style="color:#999;">No hay registros de auditoría.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Página {PAGE_NUM} de {PAGE_COUNT} | Generado por {{ auth()->user()?->name ?? 'Sistema' }}
    </div>
</body>
</html>
