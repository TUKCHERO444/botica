@php
    $item = $item ?? null;
    $pivot = $pivot ?? null;
    $paso = $item?->paso_input ?? 1;
    $cantidad = $pivot ? \App\Helpers\CantidadHelper::formatear($pivot->cantidad) : 1;
    $precio = $pivot ? number_format((float) $pivot->precio_unitario, 2, '.', '') : '';
    $subTotal = $pivot?->sub_total ?? 0;
    $initialLabel = $item
        ? trim(($item->producto?->nombre ?? '').' — '.$item->nombre_completo, ' —')
        : '';
@endphp

<div class="producto-row grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
    <div class="sm:col-span-5">
        <label class="block text-xs font-medium text-stone-600 mb-1">Producto / presentación</label>
        <div class="producto-search"
            data-field="productos[{{ $i }}][producto_presentacion_id]"
            data-url="{{ route('admin.ventas.presentaciones.search') }}"
            data-solo-stock="{{ isset($soloStock) && ! $soloStock ? '0' : '1' }}"
            data-placeholder="Buscar producto por nombre o código..."
            @if ($item)
                data-initial-id="{{ $item->id_producto_presentacion }}"
                data-initial-label="{{ $initialLabel }}"
                data-initial-precio="{{ $item?->precio_venta_input ?? '' }}"
                data-initial-paso="{{ $item?->paso_input ?? 1 }}"
                data-initial-stock="{{ $item->producto?->stock_formateado }}"
            @endif
        ></div>
        <p class="producto-advertencia text-[11px] text-amber-600 mt-1 hidden"></p>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-stone-600 mb-1">Cantidad</label>
        <input type="number" name="productos[{{ $i }}][cantidad]" value="{{ $cantidad }}" min="{{ $paso }}" step="{{ $paso }}" required
            class="producto-cantidad w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
    </div>
    <div class="sm:col-span-3">
        <label class="block text-xs font-medium text-stone-600 mb-1">Precio Unitario (S/)</label>
        <input type="number" name="productos[{{ $i }}][precio]" value="{{ $precio }}" step="0.01" min="0" readonly
            class="producto-precio w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-500 outline-none">
    </div>
    <div class="sm:col-span-2 flex items-end pb-1 gap-2">
        <span class="producto-subtotal text-sm font-medium text-stone-700 pt-2 hidden sm:block">S/ {{ number_format((float) $subTotal, 2) }}</span>
        <button type="button" class="eliminar-producto text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </button>
    </div>
</div>
