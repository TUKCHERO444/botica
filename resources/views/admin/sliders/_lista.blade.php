<table class="w-full text-sm">
    <thead>
        <tr class="text-stone-500 border-b border-stone-200">
            <th class="text-left py-2 pr-2 w-16">Orden</th>
            <th class="text-left py-2 px-2 w-12">Foto</th>
            <th class="text-left py-2 px-2">Nombre</th>
            <th class="text-left py-2 px-2">Categoría</th>
            <th class="text-left py-2 px-2">Precio</th>
            <th class="text-right py-2 pl-2 w-20">Acción</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $item)
            <tr class="border-b border-stone-100 hover:bg-stone-50 transition">
                <td class="py-2 pr-2">
                    <input type="number" value="{{ $item->orden }}" min="0"
                        data-producto-id="{{ $item->producto_id_producto }}"
                        class="input-orden w-14 text-center rounded border border-stone-300 px-1 py-1 text-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </td>
                <td class="py-2 px-2">
                    @if($item->producto->foto_url)
                        <img src="{{ $item->producto->foto_url }}" alt=""
                            class="w-10 h-10 object-cover rounded-lg border border-stone-200">
                    @else
                        <span class="w-10 h-10 flex items-center justify-center bg-stone-100 rounded-lg text-stone-400 text-xs">—</span>
                    @endif
                </td>
                <td class="py-2 px-2 text-stone-800 font-medium">{{ $item->producto->nombre }}</td>
                <td class="py-2 px-2 text-stone-500">{{ $item->producto->categoria?->nombre ?? '—' }}</td>
                <td class="py-2 px-2 text-stone-700">S/ {{ number_format($item->producto->precio_venta, 2) }}</td>
                <td class="py-2 pl-2 text-right">
                    <button type="button" data-producto-id="{{ $item->producto_id_producto }}"
                        class="btn-quitar text-red-600 hover:text-red-800 text-xs font-medium transition">
                        Quitar
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="py-8 text-center text-sm text-stone-400">
                    No hay productos asignados a este slider.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

@if ($items->hasPages())
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-4 mt-4 border-t border-stone-200">
        <p class="text-xs text-stone-500">
            Mostrando {{ $items->firstItem() }}–{{ $items->lastItem() }} de {{ $items->total() }} productos
        </p>
        <div class="flex items-center gap-1">
            @if ($items->onFirstPage())
                <span class="px-3 py-1.5 text-xs text-stone-300 border border-stone-200 rounded-lg">Anterior</span>
            @else
                <button type="button" data-slider-page="{{ $items->currentPage() - 1 }}"
                    class="px-3 py-1.5 text-xs text-stone-600 border border-stone-300 rounded-lg hover:bg-stone-50 transition">
                    Anterior
                </button>
            @endif

            @foreach ($items->getUrlRange(max(1, $items->currentPage() - 2), min($items->lastPage(), $items->currentPage() + 2)) as $page => $url)
                @if ($page === $items->currentPage())
                    <span class="min-w-8 text-center px-2 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg">{{ $page }}</span>
                @else
                    <button type="button" data-slider-page="{{ $page }}"
                        class="min-w-8 text-center px-2 py-1.5 text-xs text-stone-600 border border-stone-300 rounded-lg hover:bg-stone-50 transition">
                        {{ $page }}
                    </button>
                @endif
            @endforeach

            @if ($items->hasMorePages())
                <button type="button" data-slider-page="{{ $items->currentPage() + 1 }}"
                    class="px-3 py-1.5 text-xs text-stone-600 border border-stone-300 rounded-lg hover:bg-stone-50 transition">
                    Siguiente
                </button>
            @else
                <span class="px-3 py-1.5 text-xs text-stone-300 border border-stone-200 rounded-lg">Siguiente</span>
            @endif
        </div>
    </div>
@endif
