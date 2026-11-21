@if ($pagos->isNotEmpty())
    <div class="separador"></div>
    <div class="meta">
        <div>
            <span class="etiqueta">Pagos:</span>
            <span class="valor"></span>
        </div>
        @foreach ($pagos as $pago)
            <div>
                <span class="etiqueta">{{ $pago->metodoPago?->nombre ?? 'Método eliminado' }}</span>
                <span class="valor">{{ $moneda }} {{ number_format($pago->monto, 2) }}</span>
            </div>
        @endforeach
    </div>
@endif
