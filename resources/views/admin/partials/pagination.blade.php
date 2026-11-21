@php($linkAttribute = ($modal ?? false) ? 'data-modal' : 'data-panel')

@if ($items->hasPages())
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 border-t border-stone-200"
        data-pagination>
        <p class="text-xs text-stone-500">
            Mostrando {{ $items->firstItem() }}–{{ $items->lastItem() }}
            de {{ $items->total() }} registros
        </p>

        <div class="flex items-center gap-1">
            @if ($items->onFirstPage())
                <span class="px-3 py-1.5 text-xs text-stone-300 border border-stone-200 rounded-lg">
                    Anterior
                </span>
            @else
                <a href="{{ $items->previousPageUrl() }}" {{ $linkAttribute }}="{{ $items->previousPageUrl() }}"
                    class="px-3 py-1.5 text-xs text-stone-600 border border-stone-300 rounded-lg hover:bg-stone-50 transition">
                    Anterior
                </a>
            @endif

            @foreach ($items->getUrlRange(max(1, $items->currentPage() - 2), min($items->lastPage(), $items->currentPage() + 2)) as $page => $url)
                @if ($page === $items->currentPage())
                    <span class="min-w-8 text-center px-2 py-1.5 text-xs font-semibold text-white bg-emerald-600 rounded-lg">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $url }}" {{ $linkAttribute }}="{{ $url }}"
                        class="min-w-8 text-center px-2 py-1.5 text-xs text-stone-600 border border-stone-300 rounded-lg hover:bg-stone-50 transition">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($items->hasMorePages())
                <a href="{{ $items->nextPageUrl() }}" {{ $linkAttribute }}="{{ $items->nextPageUrl() }}"
                    class="px-3 py-1.5 text-xs text-stone-600 border border-stone-300 rounded-lg hover:bg-stone-50 transition">
                    Siguiente
                </a>
            @else
                <span class="px-3 py-1.5 text-xs text-stone-300 border border-stone-200 rounded-lg">
                    Siguiente
                </span>
            @endif
        </div>
    </div>
@endif
