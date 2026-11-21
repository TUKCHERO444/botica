@php
    $index = $index ?? 0;
    $detalle = $detalle ?? null;
    $presentacion = $detalle?->presentacion;
    $producto = $presentacion?->producto;
    $factor = (float) ($detalle?->factor ?? $presentacion?->factor ?? 1);
    $cantidadPresentacion = (float) ($detalle?->cantidad_presentacion ?? 0);

    if ($cantidadPresentacion <= 0 && $detalle && $factor > 0) {
        $cantidadPresentacion = (float) $detalle->cantidad_base / $factor;
    }

    $costoPresentacion = (float) ($detalle?->costo_unitario_presentacion ?? 0);

    if ($costoPresentacion <= 0 && $detalle) {
        $costoPresentacion = (float) $detalle->costo_unitario_base * $factor;
    }

    $precioVenta = $presentacion?->precio_venta;
    $label = $producto && $presentacion
        ? trim($producto->nombre.' — '.$presentacion->nombre_completo, ' —')
        : '';
    $stockLabel = $producto?->stock_formateado ?? '';
    $ventaLabel = $precioVenta ? 'S/ '.number_format((float) $precioVenta, 2) : '';
@endphp

<tr class="linea-row" data-factor="{{ $presentacion?->factor }}" data-unidad="{{ $producto?->unidadBase?->codigo }}">
    <td class="px-2 py-3 align-top">
        <div class="producto-search"
            data-field="lineas[{{ $index }}][producto_presentacion_id]"
            data-url="{{ route('admin.inventario-inicial.presentaciones.search') }}"
            data-solo-stock="0"
            data-initial-id="{{ $presentacion?->id_producto_presentacion }}"
            data-initial-label="{{ $label }}"
            data-initial-precio="{{ $costoPresentacion > 0 ? number_format($costoPresentacion, 2, '.', '') : '' }}"
            data-initial-stock="{{ $stockLabel }}"
            data-initial-venta-label="{{ $ventaLabel }}"
            data-placeholder="Buscar producto por nombre..."></div>
    </td>
    <td class="px-2 py-3 align-top">
        <input type="text" name="lineas[{{ $index }}][codigo_lote]" maxlength="50" required
            value="{{ $detalle?->codigo_lote }}"
            class="w-full rounded-lg border border-stone-300 px-2.5 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
            placeholder="Lote">
    </td>
    <td class="px-2 py-3 align-top">
        <input type="date" name="lineas[{{ $index }}][fecha_vencimiento]" required
            value="{{ $detalle?->fecha_vencimiento?->format('Y-m-d') }}"
            class="w-full rounded-lg border border-stone-300 px-2.5 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
    </td>
    <td class="px-2 py-3 align-top">
        <input type="number" name="lineas[{{ $index }}][cantidad_presentacion]" min="0.001" step="0.001" required
            value="{{ $detalle ? \App\Helpers\CantidadHelper::formatear($cantidadPresentacion) : '' }}"
            class="w-full rounded-lg border border-stone-300 px-2.5 py-2.5 text-sm text-right focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
            placeholder="Cantidad">
        <p class="linea-conversion text-[10px] leading-tight text-stone-500 mt-1 hidden"></p>
    </td>
    <td class="px-2 py-3 align-top">
        <input type="number" name="lineas[{{ $index }}][costo_unitario_presentacion]" min="0" step="0.01"
            value="{{ $costoPresentacion > 0 ? number_format($costoPresentacion, 2, '.', '') : '' }}"
            class="costo-input w-full rounded-lg border border-stone-300 px-2.5 py-2.5 text-sm text-right focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
            placeholder="Costo">
    </td>
    <td class="px-2 py-3 align-top">
        <input type="text" readonly tabindex="-1"
            value="{{ $ventaLabel }}"
            class="precio-venta-input w-full rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-2.5 text-sm text-right text-stone-600"
            placeholder="—">
    </td>
    <td class="px-2 py-3 align-top text-right">
        <button type="button" data-eliminar-linea
            class="text-red-500 hover:text-red-700 p-2 hover:bg-red-50 rounded-lg transition" title="Quitar línea">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </td>
</tr>
