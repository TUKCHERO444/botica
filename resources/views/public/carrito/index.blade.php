@extends('layouts.public')

@section('title', 'Mi Carrito — Botica')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6 md:py-8" id="cart-page">

    <h1 class="text-2xl sm:text-3xl font-bold text-stone-800 mb-6 flex items-center gap-3">
        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
        </svg>
        Mi Carrito
        @if($items->isNotEmpty())
            <span class="text-base font-normal text-stone-400">
                ({{ $items->sum('cantidad') }} {{ $items->sum('cantidad') === 1 ? 'item' : 'items' }})
            </span>
        @endif
    </h1>

    @if(session('success'))
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    @if($items->isEmpty())
        <div class="text-center py-16">
            <svg class="w-24 h-24 mx-auto text-stone-300 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
            </svg>
            <h2 class="text-xl font-semibold text-stone-500 mb-2">Tu carrito está vacío</h2>
            <p class="text-stone-400 mb-6">Explora nuestros productos y añade los que más te gusten.</p>
            <a href="{{ route('public.productos.index') }}"
               class="inline-block bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-6 py-3 rounded-lg transition">
                Ver productos
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($items as $item)
                <div class="cart-item bg-white rounded-xl border border-stone-200 shadow-sm p-4 flex items-center gap-4"
                     data-item-id="{{ $item->id_carrito_item }}"
                     data-precio="{{ $item->precio_unitario }}"
                     data-paso="{{ $item->productoPresentacion?->paso ?? 1 }}">
                    <div class="w-20 h-20 rounded-lg overflow-hidden bg-stone-100 shrink-0">
                        @if($item->foto_url)
                            <img src="{{ $item->foto_url }}" alt="{{ $item->nombre }}"
                                 class="w-full h-full object-cover"
                                 onerror="this.style.display='none'">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-stone-300">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-stone-400 uppercase text-xs font-medium">
                            {{ $item->item_type === 'combo' ? 'Combo' : 'Producto' }}
                        </p>
                        <p class="font-semibold text-stone-800 truncate">{{ $item->nombre ?? '—' }}</p>
                        <p class="text-sm text-stone-500 mt-0.5">S/ <span class="precio-unitario">{{ number_format($item->precio_unitario, 2) }}</span> c/u</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="qty-minus w-8 h-8 rounded-full border border-stone-300 text-stone-600 hover:bg-stone-100 transition flex items-center justify-center text-lg font-medium" {{ (float) $item->cantidad <= (float) ($item->productoPresentacion?->paso ?? 1) ? 'disabled' : '' }}>
                            −
                        </button>
                        <span class="qty-value w-8 text-center font-semibold text-stone-800 tabular-nums">{{ \App\Helpers\CantidadHelper::formatear($item->cantidad) }}</span>
                        <button type="button" class="qty-plus w-8 h-8 rounded-full border border-stone-300 text-stone-600 hover:bg-stone-100 transition flex items-center justify-center text-lg font-medium">
                            +
                        </button>
                    </div>
                    <div class="text-right min-w-[90px]">
                        <p class="text-xs text-stone-400">Subtotal</p>
                        <p class="item-subtotal text-base font-bold text-emerald-700">S/ {{ number_format($item->subtotal, 2) }}</p>
                    </div>
                    <button type="button" class="remove-item text-stone-400 hover:text-red-500 transition p-1" title="Eliminar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>

        <div class="mt-8 bg-white rounded-xl border border-stone-200 shadow-sm p-6">
            <div class="flex justify-between items-center">
                <span class="text-lg font-semibold text-stone-700">Total</span>
                <span class="text-2xl font-bold text-emerald-700 cart-total">S/ {{ number_format($total, 2) }}</span>
            </div>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a href="{{ route('public.productos.index') }}"
               class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 border border-emerald-600 text-emerald-700 font-semibold rounded-lg hover:bg-emerald-50 transition text-center">
                Seguir comprando
            </a>
            <a href="{{ route('public.carrito.pdf') }}"
               class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-stone-600 hover:bg-stone-700 text-white font-semibold rounded-lg transition text-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Descargar PDF
            </a>
            <a href="{{ route('public.carrito.whatsapp') }}"
               target="_blank"
               rel="noopener noreferrer"
               class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-semibold rounded-lg transition text-center">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                Cotizar por WhatsApp
            </a>
        </div>
    @endif
</div>
@endsection

@push('scripts')
    @vite('resources/js/public/carrito.js')
@endpush
