<div data-modal-size="2xl" class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Detalle de venta #{{ $venta->id_venta }}</h2>
            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full mt-1 {{ $venta->estado_clase }}">
                {{ $venta->estado_label }}
            </span>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    @if ($venta->estaAnulada())
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4 text-sm text-red-800 space-y-1">
            <p><span class="font-semibold">Venta anulada</span> el {{ $venta->anulada_at?->format('d/m/Y H:i') ?? '—' }}.</p>
            <p>Motivo: {{ $venta->motivo_anulacion ?? '—' }}</p>
            <p>Anulada por: {{ $venta->anuladaPor?->name ?? '—' }}</p>
        </div>
    @endif

    <div class="bg-stone-50 rounded-lg p-4 mb-6 space-y-2">
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Correlativo</span>
            <span class="text-sm font-mono font-medium text-stone-800">{{ $venta->correlativo }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Cliente</span>
            <span class="text-sm font-medium text-stone-800">
                {{ $venta->cliente->nombre }} {{ $venta->cliente->apellido_paterno }} {{ $venta->cliente->apellido_materno }}
                <span class="text-stone-400">— {{ $venta->cliente->dni }}</span>
            </span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Registrado por</span>
            <span class="text-sm text-stone-700">{{ $venta->user?->name ?? '—' }}</span>
        </div>
    </div>

    <div class="mb-4">
        <h3 class="text-sm font-semibold text-stone-700 mb-2">Productos</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-100 text-stone-600 text-left">
                    <th class="px-3 py-2 font-medium">Producto</th>
                    <th class="px-3 py-2 font-medium">Presentación</th>
                    <th class="px-3 py-2 font-medium">Lote(s) consumido(s)</th>
                    <th class="px-3 py-2 font-medium text-center">Cant.</th>
                    <th class="px-3 py-2 font-medium text-right">Precio Unit.</th>
                    <th class="px-3 py-2 font-medium text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($venta->productos as $p)
                @php($pres = $presentaciones[$p->pivot->producto_presentacion_id] ?? null)
                <tr class="hover:bg-stone-50">
                    <td class="px-3 py-2 text-stone-700">{{ $p->nombre }}</td>
                    <td class="px-3 py-2 text-stone-600">{{ $pres?->nombre_completo ?? 'Unidad base' }}</td>
                    <td class="px-3 py-2 text-stone-500 text-xs">
                        @foreach ($lotesPorProducto->get($p->id_producto, collect()) as $consumo)
                            <div class="font-mono">{{ $consumo->lote?->codigo_lote }}: {{ \App\Helpers\CantidadHelper::formatear($consumo->cantidad_base) }}</div>
                        @endforeach
                    </td>
                    <td class="px-3 py-2 text-stone-600 text-center">{{ \App\Helpers\CantidadHelper::formatear($p->pivot->cantidad) }}</td>
                    <td class="px-3 py-2 text-stone-600 text-right">S/ {{ number_format($p->pivot->precio_unitario, 2) }}</td>
                    <td class="px-3 py-2 text-stone-800 font-medium text-right">S/ {{ number_format($p->pivot->sub_total, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-3 py-4 text-center text-stone-400">No hay productos registrados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mb-4">
        <h3 class="text-sm font-semibold text-stone-700 mb-2">Pagos</h3>
        @if ($venta->pagos->isNotEmpty())
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-stone-100 text-stone-600 text-left">
                        <th class="px-3 py-2 font-medium">Método de pago</th>
                        <th class="px-3 py-2 font-medium text-right">Importe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @foreach ($venta->pagos as $pago)
                        <tr>
                            <td class="px-3 py-2 text-stone-700">{{ $pago->metodoPago?->nombre ?? 'Método eliminado' }}</td>
                            <td class="px-3 py-2 text-stone-800 font-medium text-right">S/ {{ number_format($pago->monto, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-sm text-stone-400">No se registró el desglose de pagos.</p>
        @endif
    </div>

    <div class="flex items-center justify-end gap-3 p-3 bg-emerald-50 rounded-lg">
        <span class="text-sm font-medium text-stone-600">Total:</span>
        <span class="text-xl font-bold text-emerald-700">S/ {{ number_format($venta->total, 2) }}</span>
    </div>

    <div class="flex justify-end pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
    </div>
</div>
