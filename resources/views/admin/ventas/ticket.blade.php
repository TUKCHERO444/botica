@extends('admin.tickets.base')

@section('tipo_documento', 'TICKET DE VENTA')

@section('contenido')
    @php
        $moneda = config('ticket.moneda', 'S/');
        $cliente = $venta->cliente;
        $nombreCliente = $cliente?->nombreParaTicket() ?? 'CLIENTE GENERAL';
        $dniCliente = $cliente?->dniParaTicket() ?? '---';
        $totalArticulos = $venta->productos->sum(fn ($p) => (float) $p->pivot->cantidad);
    @endphp

    @if ($venta->estaAnulada())
        <div class="anulada">*** ANULADA ***</div>
        <div class="meta">
            <div>
                <span class="etiqueta">Anulada:</span>
                <span class="valor">{{ $venta->anulada_at?->format('d/m/Y H:i') ?? '—' }}</span>
            </div>
            <div>
                <span class="etiqueta">Motivo:</span>
                <span class="valor">{{ $venta->motivo_anulacion ?? '—' }}</span>
            </div>
            <div>
                <span class="etiqueta">Anulada por:</span>
                <span class="valor">{{ $venta->anuladaPor?->name ?? '—' }}</span>
            </div>
        </div>
    @endif

    <div class="separador"></div>

    <div class="meta">
        <div>
            <span class="etiqueta">Ticket:</span>
            <span class="valor">{{ $venta->correlativo }}</span>
        </div>
        <div>
            <span class="etiqueta">Fecha:</span>
            <span class="valor">{{ $venta->created_at?->format('d/m/Y H:i') }}</span>
        </div>
        <div>
            <span class="etiqueta">Cliente:</span>
            <span class="valor">{{ $nombreCliente }}</span>
        </div>
        <div>
            <span class="etiqueta">DNI:</span>
            <span class="valor">{{ $dniCliente }}</span>
        </div>
        <div>
            <span class="etiqueta">Atendido por:</span>
            <span class="valor">{{ $venta->user?->name ?? '—' }}</span>
        </div>
    </div>

    <div class="separador"></div>

    <table>
        <thead>
            <tr>
                <th class="cant">Cant.</th>
                <th class="desc">Descripción</th>
                <th class="unit">P. Unit.</th>
                <th class="importe">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($venta->productos as $p)
                @php($pres = $presentaciones[$p->pivot->producto_presentacion_id] ?? null)
                <tr>
                    <td class="cant">{{ \App\Helpers\CantidadHelper::formatear($p->pivot->cantidad) }}</td>
                    <td class="desc">
                        <span class="nombre">{{ $p->nombre }}</span>
                        @if ($pres)
                            <small>{{ $pres->nombre_completo }}</small>
                        @endif
                    </td>
                    <td class="unit">{{ number_format($p->pivot->precio_unitario, 2) }}</td>
                    <td class="importe">{{ number_format($p->pivot->sub_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="padding: 6px 0;">Sin productos registrados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="leyenda-precios">Precios e importes en {{ $moneda }}</div>

    <div class="separador"></div>

    <div class="totales">
        <div class="resumen">
            <span>Artículos:</span>
            <span>{{ \App\Helpers\CantidadHelper::formatear($totalArticulos) }}</span>
        </div>
        <div class="total">
            <span>TOTAL</span>
            <span>{{ $moneda }} {{ number_format($venta->total, 2) }}</span>
        </div>
    </div>

    @include('admin.tickets._pagos', ['pagos' => $venta->pagos, 'moneda' => $moneda])
@endsection
