@extends('layouts.public')

@section('title', $combo->nombre . ' — Botica')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 md:py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-stone-500 mb-6" aria-label="Breadcrumb">
        <ol class="flex items-center gap-1.5 flex-wrap">
            <li><a href="/" class="hover:text-emerald-700 transition">Inicio</a></li>
            <li class="text-stone-300">/</li>
            <li><a href="{{ route('public.productos.index') }}" class="hover:text-emerald-700 transition">Productos</a></li>
            <li class="text-stone-300">/</li>
            <li class="text-stone-800 font-medium truncate max-w-[200px]">{{ $combo->nombre }}</li>
        </ol>
    </nav>

    {{-- Contenido: dos columnas como en detalle de producto --}}
    <div class="grid md:grid-cols-2 gap-8 lg:gap-12">

        {{-- Columna izquierda: imagen --}}
        <div>
            @if($combo->imagen_url)
                <div class="relative overflow-hidden rounded-xl shadow-sm bg-stone-100">
                    <img src="{{ $combo->imagen_url }}"
                         alt="{{ $combo->nombre }}"
                         class="w-full aspect-square object-cover">
                </div>
            @else
                <div class="w-full aspect-square bg-stone-100 rounded-xl flex flex-col items-center justify-center text-stone-400">
                    <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm">Sin imagen disponible</span>
                </div>
            @endif
        </div>

        {{-- Columna derecha: información --}}
        <div class="flex flex-col">
            <span class="text-sm text-emerald-600 font-medium mb-1">Combo</span>

            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-stone-800 leading-tight">{{ $combo->nombre }}</h1>

            @php
                $sumaIndividual = $combo->productos->sum(fn($p) => $p->precio_venta * $p->pivot->cantidad);
                $ahorro = $sumaIndividual - $combo->precio_sugerido;
            @endphp

            {{-- Comparativa de precios --}}
            <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-stone-50 rounded-xl p-4 text-center border border-stone-200">
                    <p class="text-xs text-stone-500 uppercase font-medium">Precio regular</p>
                    <p class="text-lg text-stone-500 line-through mt-1 font-semibold">S/ {{ number_format($sumaIndividual, 2) }}</p>
                </div>
                <div class="bg-emerald-50 rounded-xl p-4 text-center border-2 border-emerald-200">
                    <p class="text-xs text-emerald-600 uppercase font-medium">Precio combo</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1">S/ {{ number_format($combo->precio_sugerido, 2) }}</p>
                </div>
                <div class="bg-amber-50 rounded-xl p-4 text-center border border-amber-200">
                    <p class="text-xs text-amber-600 uppercase font-medium">Ahorras</p>
                    <p class="text-lg font-bold text-amber-700 mt-1">S/ {{ number_format($ahorro, 2) }}</p>
                </div>
            </div>

            {{-- WhatsApp --}}
            <x-whatsapp-button :combo="$combo" />

            {{-- Add to cart --}}
            @auth('clientes')
                <button type="button"
                        data-item-type="combo"
                        data-item-id="{{ $combo->id_combo }}"
                        class="add-to-cart-btn inline-flex items-center justify-center gap-2 w-full mt-3 px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg transition-colors duration-200 shadow-sm">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                    </svg>
                    Añadir al carrito
                </button>
            @else
                <a href="{{ route('public.login') }}"
                   class="inline-flex items-center justify-center gap-2 w-full mt-3 px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg transition-colors duration-200 shadow-sm">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                    </svg>
                    Inicia sesión para añadir al carrito
                </a>
            @endauth

            @if($combo->descripcion)
                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-stone-700 uppercase tracking-wider mb-2">Descripción</h2>
                    <p class="text-stone-600 leading-relaxed">{{ $combo->descripcion }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Productos incluidos --}}
    <section class="mt-12 border-t border-stone-200 pt-8">
        <h2 class="text-xl sm:text-2xl font-bold text-stone-800 mb-6">Productos incluidos</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($combo->productos as $producto)
                <div class="flex items-center gap-4 p-4 bg-white rounded-xl border border-stone-200 shadow-sm">
                    <img src="{{ $producto->foto_url }}"
                         alt="{{ $producto->nombre }}"
                         class="w-20 h-20 object-cover rounded-lg border border-stone-200 shrink-0"
                         onerror="this.style.display='none'">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-stone-800 truncate">{{ $producto->nombre }}</p>
                        <p class="text-xs text-stone-400">{{ $producto->categoria?->nombre }}</p>
                        <div class="flex items-center gap-2 mt-1.5">
                            <span class="inline-flex items-center justify-center bg-emerald-100 text-emerald-700 text-xs font-semibold px-2 py-0.5 rounded-full">{{ $producto->pivot->cantidad }}x</span>
                            <span class="text-sm text-stone-600">S/ {{ number_format($producto->precio_venta, 2) }} c/u</span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-xs text-stone-400">Subtotal</p>
                        <p class="text-base font-bold text-stone-800">S/ {{ number_format($producto->precio_venta * $producto->pivot->cantidad, 2) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection

@push('scripts')
    @vite('resources/js/public/carrito.js')
@endpush
