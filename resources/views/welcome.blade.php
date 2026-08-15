@extends('layouts.public')

@section('title', 'Inicio — Botica')

@section('content')
    {{-- Categorías --}}
    <section class="max-w-7xl mx-auto px-4 py-6" aria-label="Categorías">
        <div class="flex flex-wrap justify-center gap-3">
            @forelse ($categoriasPortada as $categoria)
                <a href="{{ route('public.productos.index', ['categorias[]' => $categoria->id_categoria]) }}"
                   class="bg-emerald-100 hover:bg-emerald-200 text-emerald-800 text-sm font-medium px-5 py-2 rounded-full transition">
                    {{ $categoria->nombre }}
                </a>
            @empty
                <span class="text-sm text-stone-400">No hay categorías disponibles</span>
            @endforelse
        </div>
    </section>

    {{-- Carrusel --}}
    <section class="max-w-7xl mx-auto px-4 pb-8" aria-label="Carrusel de ofertas" role="region">
        <div id="carousel" class="relative overflow-hidden rounded-2xl shadow-lg bg-stone-200" role="listbox" aria-label="Imágenes destacadas">
            <div id="carousel-track" class="flex transition-transform duration-700 ease-in-out" role="list">
                @forelse ($banners as $banner)
                    <div class="min-w-full relative" role="listitem">
                        @if($banner->enlace)
                            <a href="{{ $banner->enlace }}" target="_blank" rel="noopener noreferrer" class="block">
                        @endif
                        <img src="{{ $banner->imagen_url }}" alt="{{ $banner->titulo ?? 'Banner' }}"
                            class="w-full h-64 sm:h-80 md:h-96 object-cover">
                        @if($banner->enlace)
                            </a>
                        @endif
                        @if($banner->titulo || $banner->subtitulo)
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-black/20 text-white pointer-events-none">
                                @if($banner->titulo)
                                    <h2 class="text-2xl sm:text-4xl font-bold drop-shadow-lg text-center px-4">{{ $banner->titulo }}</h2>
                                @endif
                                @if($banner->subtitulo)
                                    <p class="text-sm sm:text-lg mt-2 drop-shadow-md text-center px-4">{{ $banner->subtitulo }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="min-w-full" role="listitem">
                        <div class="w-full h-64 sm:h-80 md:h-96 bg-stone-200 flex items-center justify-center text-stone-400 text-sm">
                            No hay banners disponibles
                        </div>
                    </div>
                @endforelse
            </div>

            <button id="carousel-prev" aria-label="Anterior"
                class="absolute left-3 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full w-10 h-10 flex items-center justify-center transition z-10">
                &#8249;
            </button>
            <button id="carousel-next" aria-label="Siguiente"
                class="absolute right-3 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full w-10 h-10 flex items-center justify-center transition z-10">
                &#8250;
            </button>

            <div id="carousel-dots" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2 z-10" role="tablist">
                @foreach ($banners as $i => $banner)
                    <button role="tab" aria-label="Ir a slide {{ $i + 1 }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                        data-index="{{ $i }}"
                        class="carousel-dot w-3 h-3 rounded-full bg-white/50 hover:bg-white/80 transition {{ $i === 0 ? 'bg-white' : '' }}">
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Promociones --}}
    <section class="max-w-7xl mx-auto px-4 pb-8" aria-label="Promociones">
        <h2 class="text-2xl sm:text-3xl font-bold text-stone-800 text-center mb-6">Promociones</h2>
        <div class="grid grid-cols-12 gap-4">
            @forelse ($promociones as $promocion)
                @php
                    $gridClasses = match($promocion->tipo_layout) {
                        'principal' => 'col-span-12 h-48 sm:h-64 md:h-80 lg:h-96',
                        'terciaria' => 'col-span-12 sm:col-span-6 h-32 sm:h-40 md:h-48',
                        'secundaria' => 'col-span-6 sm:col-span-3 h-32 sm:h-40 md:h-48',
                        default => 'col-span-12 h-48 sm:h-64',
                    };
                @endphp
                <div class="relative overflow-hidden rounded-2xl shadow-lg group {{ $gridClasses }}"
                    @if($promocion->enlace_resuelto)
                        onclick="window.open('{{ $promocion->enlace_resuelto }}', '_blank'); fetch('/promociones/{{ $promocion->id_promocion }}/click', { method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}'} })"
                        role="link" style="cursor:pointer"
                    @endif>
                    <img src="{{ $promocion->imagen_url }}" alt="{{ $promocion->titulo ?? 'Promoción' }}"
                        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    @if($promocion->titulo || $promocion->subtitulo)
                        <div class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-t from-black/60 via-black/20 to-transparent text-white pointer-events-none p-4">
                            @if($promocion->titulo)
                                <h3 class="font-bold text-center drop-shadow-lg {{ $promocion->tipo_layout === 'principal' ? 'text-xl sm:text-3xl' : 'text-sm sm:text-base' }}">
                                    {{ $promocion->titulo }}
                                </h3>
                            @endif
                            @if($promocion->subtitulo)
                                <p class="text-center drop-shadow-md {{ $promocion->tipo_layout === 'principal' ? 'text-sm sm:text-base mt-2' : 'text-xs mt-1' }}">
                                    {{ $promocion->subtitulo }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="col-span-12 py-12 text-center text-sm text-stone-400">
                    No hay promociones disponibles
                </div>
            @endforelse
        </div>
    </section>

    {{-- Productos más buscados --}}
    <x-product-slider
        id="buscados"
        titulo="Más buscados"
        :productos="$destacados"
    />

    {{-- Productos más vendidos --}}
    <x-product-slider
        id="vendidos"
        titulo="Más vendidos"
        :productos="$masVendidos"
    />

    {{-- Productos en descuento --}}
    <x-product-slider
        id="descuentos"
        titulo="En descuento"
        :productos="$descuentos"
    />
@endsection

@push('scripts')
    @vite(['resources/js/public/tienda/catalogo.js', 'resources/js/public/tienda/productos-slider.js'])
@endpush
