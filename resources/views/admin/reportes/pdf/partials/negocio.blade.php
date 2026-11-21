@php
    $negocio = config('ticket.negocio', 'Botica');
    $ruc = config('ticket.ruc');
    $direccion = config('ticket.direccion');
    $telefono = config('ticket.telefono');
@endphp

<div style="font-size:8pt;color:#374151;margin:2px 0 4px;">
    <strong>{{ $negocio }}</strong>
    @if ($ruc) · RUC {{ $ruc }} @endif
    @if ($direccion) · {{ $direccion }} @endif
    @if ($telefono) · Tel. {{ $telefono }} @endif
</div>
