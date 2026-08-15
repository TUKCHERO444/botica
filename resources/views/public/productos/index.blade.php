@extends('layouts.public')

@section('title', 'Nuestros Productos — Botica')

@push('head')
<style>
.scrollbar-thin::-webkit-scrollbar { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #d6d3d1; border-radius: 999px; }
.scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #a8a29e; }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 md:py-8 flex flex-col md:flex-row gap-6 md:gap-8">

    {{-- Sidebar Categorías y Marcas (Desktop) --}}
    <aside class="w-full md:w-56 lg:w-64 shrink-0 hidden md:block">
        <div class="md:sticky md:top-20">
            <h3 class="text-lg font-bold text-stone-800 mb-4">Categorías</h3>
            <div class="space-y-1.5 overflow-y-auto max-h-[340px] scrollbar-thin" id="categorias-list">
                @foreach ($categorias as $cat)
                    @php $count = $cat->productos_count; @endphp
                    <label class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-stone-100 cursor-pointer transition {{ in_array($cat->id_categoria, $selectedCategorias) ? 'bg-emerald-50' : '' }}">
                        <input type="checkbox" name="categorias[]" value="{{ $cat->id_categoria }}"
                               class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                               {{ in_array($cat->id_categoria, $selectedCategorias) ? 'checked' : '' }}>
                        <span class="text-sm text-stone-700 flex-1">{{ $cat->nombre }}</span>
                        <span class="text-xs text-stone-400 tabular-nums">({{ $count }})</span>
                    </label>
                @endforeach
            </div>

            <h3 class="text-lg font-bold text-stone-800 mb-4 mt-6">Marcas</h3>
            <div class="space-y-1.5 overflow-y-auto max-h-[340px] scrollbar-thin" id="marcas-list">
                @foreach ($marcas as $mar)
                    @php $count = $mar->productos_count; @endphp
                    <label class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-stone-100 cursor-pointer transition {{ in_array($mar->id_marca, $selectedMarcas) ? 'bg-emerald-50' : '' }}">
                        <input type="checkbox" name="marcas[]" value="{{ $mar->id_marca }}"
                               class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                               {{ in_array($mar->id_marca, $selectedMarcas) ? 'checked' : '' }}>
                        <span class="text-sm text-stone-700 flex-1">{{ $mar->nombre }}</span>
                        <span class="text-xs text-stone-400 tabular-nums">({{ $count }})</span>
                    </label>
                @endforeach
            </div>

            <h3 class="text-lg font-bold text-stone-800 mb-4 mt-6">Precio máximo</h3>
            <div class="px-1">
                <input type="range" id="precio-range" min="{{ $precioMin }}" max="{{ $precioMax }}" step="1"
                       value="{{ $selectedPrecioMax ?? $precioMax }}"
                       class="w-full accent-emerald-600">
                <div class="flex items-center justify-between mt-1">
                    <span class="text-xs text-stone-400">S/ {{ number_format($precioMin, 2) }}</span>
                    <span class="text-sm font-semibold text-emerald-700" id="precio-range-label">
                        S/ {{ number_format($selectedPrecioMax ? (float) $selectedPrecioMax : $precioMax, 2) }}
                    </span>
                    <span class="text-xs text-stone-400">S/ {{ number_format($precioMax, 2) }}</span>
                </div>
            </div>

            <button type="button" id="limpiar-filtros"
                class="mt-4 text-sm text-emerald-600 hover:text-emerald-700 font-medium transition {{ empty($selectedCategorias) && empty($selectedMarcas) && !$selectedPrecioMax ? 'hidden' : '' }}">
                Limpiar filtros
            </button>
        </div>
    </aside>

    {{-- Botón filtros móvil --}}
    <button id="btn-filtros-mobile" type="button"
        class="md:hidden flex items-center gap-2 text-sm text-stone-600 bg-white border border-stone-300 rounded-lg px-4 py-2.5 shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
        </svg>
        Filtros
        @if(!empty($selectedCategorias))
            <span class="bg-emerald-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">{{ count($selectedCategorias) }}</span>
        @endif
    </button>

    {{-- Contenido principal --}}
    <div class="flex-1 min-w-0">

        {{-- Barra de búsqueda con autocomplete --}}
        <div class="relative mb-6">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-stone-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="search-productos" autocomplete="off"
                       placeholder="Buscar productos por nombre..."
                       class="w-full rounded-lg border border-stone-300 pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <div id="search-suggestions"
                 class="absolute z-30 top-full mt-1 left-0 right-0 bg-white border border-stone-200 rounded-lg shadow-lg hidden overflow-hidden">
            </div>
        </div>

        {{-- Grilla de productos --}}
        <div id="productos-grid">
            @include('public.productos._grid')
        </div>
    </div>
</div>

{{-- Modal de filtros móvil --}}
<div id="mobile-filters-overlay" class="fixed inset-0 z-50 bg-black/50 hidden md:hidden">
    <div id="mobile-filters-panel" class="absolute right-0 top-0 h-full w-72 bg-white shadow-xl overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-stone-800">Filtros</h3>
            <button id="cerrar-filtros-mobile" type="button" class="text-stone-400 hover:text-stone-600 p-1">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="categorias-list-mobile" class="overflow-y-auto max-h-[340px] scrollbar-thin">
            @foreach ($categorias as $cat)
                @php $count = $cat->productos_count; @endphp
                <label class="flex items-center gap-2.5 py-2 cursor-pointer">
                    <input type="checkbox" name="categorias[]" value="{{ $cat->id_categoria }}"
                           class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                           {{ in_array($cat->id_categoria, $selectedCategorias) ? 'checked' : '' }}>
                    <span class="text-sm text-stone-700 flex-1">{{ $cat->nombre }}</span>
                    <span class="text-xs text-stone-400">({{ $count }})</span>
                </label>
            @endforeach
        </div>

        <h3 class="text-lg font-bold text-stone-800 mb-4 mt-6">Marcas</h3>
        <div id="marcas-list-mobile" class="overflow-y-auto max-h-[340px] scrollbar-thin">
            @foreach ($marcas as $mar)
                @php $count = $mar->productos_count; @endphp
                <label class="flex items-center gap-2.5 py-2 cursor-pointer">
                    <input type="checkbox" name="marcas[]" value="{{ $mar->id_marca }}"
                           class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                           {{ in_array($mar->id_marca, $selectedMarcas) ? 'checked' : '' }}>
                    <span class="text-sm text-stone-700 flex-1">{{ $mar->nombre }}</span>
                    <span class="text-xs text-stone-400">({{ $count }})</span>
                </label>
            @endforeach
        </div>

        <h3 class="text-lg font-bold text-stone-800 mb-4 mt-6">Precio máximo</h3>
        <div class="px-1">
            <input type="range" id="precio-range-mobile" min="{{ $precioMin }}" max="{{ $precioMax }}" step="1"
                   value="{{ $selectedPrecioMax ?? $precioMax }}"
                   class="w-full accent-emerald-600">
            <div class="flex items-center justify-between mt-1">
                <span class="text-xs text-stone-400">S/ {{ number_format($precioMin, 2) }}</span>
                <span class="text-sm font-semibold text-emerald-700" id="precio-range-mobile-label">
                    S/ {{ number_format($selectedPrecioMax ? (float) $selectedPrecioMax : $precioMax, 2) }}
                </span>
                <span class="text-xs text-stone-400">S/ {{ number_format($precioMax, 2) }}</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/public/tienda/productos.js')
@endpush
