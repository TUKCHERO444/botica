@extends('admin.tickets.base')

@section('tipo_documento', 'TICKET DE ATENCIÓN')

@section('contenido')
    @php
        $moneda = config('ticket.moneda', 'S/');
        $cliente = $atencione->cliente;
        $nombreCliente = $cliente?->nombreParaTicket() ?? 'CLIENTE GENERAL';
        $dniCliente = $cliente?->dniParaTicket() ?? '---';
        $numeroTicket = $atencione->correlativo ?? 'A001-'.str_pad($atencione->id_atencion, 8, '0', STR_PAD_LEFT);
        $totalProductos = $atencione->productos->sum(fn ($p) => (float) $p->pivot->cantidad);
        $totalServicios = $atencione->servicios->count();
    @endphp

    @if ($atencione->estaAnulada())
        <div class="anulada">*** ANULADA ***</div>
        <div class="meta">
            <div>
                <span class="etiqueta">Anulada:</span>
                <span class="valor">{{ $atencione->anulada_at?->format('d/m/Y H:i') ?? '—' }}</span>
            </div>
            <div>
                <span class="etiqueta">Motivo:</span>
                <span class="valor">{{ $atencione->motivo_anulacion ?? '—' }}</span>
            </div>
            <div>
                <span class="etiqueta">Anulada por:</span>
                <span class="valor">{{ $atencione->anuladaPor?->name ?? '—' }}</span>
            </div>
        </div>
    @endif

    <div class="separador"></div>

    <div class="meta">
        <div>
            <span class="etiqueta">Ticket:</span>
            <span class="valor">{{ $numeroTicket }}</span>
        </div>
        <div>
            <span class="etiqueta">Fecha:</span>
            <span class="valor">{{ $atencione->created_at?->format('d/m/Y H:i') }}</span>
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
            <span class="valor">{{ $atencione->user?->name ?? '—' }}</span>
        </div>
        @if (filled($atencione->observacio))
            <div>
                <span class="etiqueta">Observación:</span>
                <span class="valor observacion">{{ $atencione->observacio }}</span>
            </div>
        @endif
    </div>

    <div class="separador"></div>

    @if ($atencione->productos->isNotEmpty())
        <div class="seccion-titulo">Productos</div>
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
                @foreach ($atencione->productos as $p)
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
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($atencione->servicios->isNotEmpty())
        <div class="seccion-titulo">Servicios</div>
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
                @foreach ($atencione->servicios as $s)
                    <tr>
                        <td class="cant">1</td>
                        <td class="desc">
                            <span class="nombre">{{ $s->nombre }}</span>
                            <small>Servicio</small>
                        </td>
                        <td class="unit">{{ number_format($s->pivot->subtotal, 2) }}</td>
                        <td class="importe">{{ number_format($s->pivot->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($atencione->productos->isEmpty() && $atencione->servicios->isEmpty())
        <div style="padding: 6px 0;">Sin productos ni servicios registrados.</div>
    @endif

    <div class="leyenda-precios">Precios e importes en {{ $moneda }}</div>

    <div class="separador"></div>

    <div class="totales">
        <div class="resumen">
            <span>Productos:</span>
            <span>{{ \App\Helpers\CantidadHelper::formatear($totalProductos) }}</span>
        </div>
        <div class="resumen">
            <span>Servicios:</span>
            <span>{{ $totalServicios }}</span>
        </div>
        <div class="total">
            <span>TOTAL</span>
            <span>{{ $moneda }} {{ number_format($atencione->total, 2) }}</span>
        </div>
    </div>

    @include('admin.tickets._pagos', ['pagos' => $atencione->pagos, 'moneda' => $moneda])
@endsection
