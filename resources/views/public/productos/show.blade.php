@extends('layouts.public')

@section('title', $producto->nombre . ' — Botica')
@section('meta_description', Str::limit($producto->descripcion ?? 'Producto disponible en Botica', 160))

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 md:py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-stone-500 mb-6" aria-label="Breadcrumb">
        <ol class="flex items-center gap-1.5 flex-wrap">
            <li><a href="/" class="hover:text-emerald-700 transition">Inicio</a></li>
            <li class="text-stone-300">/</li>
            <li><a href="{{ route('public.productos.index') }}" class="hover:text-emerald-700 transition">Productos</a></li>
            @if($producto->categoria)
                <li class="text-stone-300">/</li>
                <li><a href="{{ route('public.productos.index', ['categorias[]' => $producto->categoria->id_categoria]) }}"
                       class="hover:text-emerald-700 transition">{{ $producto->categoria->nombre }}</a></li>
            @endif
            <li class="text-stone-300">/</li>
            <li class="text-stone-800 font-medium truncate max-w-[200px]">{{ $producto->nombre }}</li>
        </ol>
    </nav>

    {{-- Contenido --}}
    <div class="grid md:grid-cols-2 gap-8 lg:gap-12">

        {{-- Columna izquierda: galería --}}
        <div>
            @php
                $imagenes = $producto->imagenes;
                $total = $imagenes->count();
            @endphp
            @if($total > 0)
                <div id="galeria-principal" class="relative overflow-hidden cursor-zoom-in rounded-xl shadow-sm" data-total="{{ $total }}">
                    <img src="{{ $imagenes[0]->url }}"
                         alt="{{ $producto->nombre }}"
                         id="imagen-principal"
                         class="w-full aspect-square object-cover bg-stone-100">
                    @if($total > 1)
                        <button type="button" id="btn-anterior" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white rounded-full p-2 shadow transition opacity-0 group-hover:opacity-100">
                            <svg class="w-5 h-5 text-stone-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <button type="button" id="btn-siguiente" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white rounded-full p-2 shadow transition">
                            <svg class="w-5 h-5 text-stone-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    @endif
                </div>

                @if($total > 1)
                    <div id="galeria-thumbnails" class="flex gap-2 mt-3 overflow-x-auto pb-1">
                        @foreach($imagenes as $i => $img)
                            <button type="button"
                                    class="thumb-btn flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition {{ $i === 0 ? 'border-emerald-500' : 'border-transparent hover:border-stone-300' }}"
                                    data-index="{{ $i }}">
                                <img src="{{ $img->url }}" alt="Thumbnail {{ $i + 1 }}" class="w-full h-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
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
            @if($producto->categoria)
                <a href="{{ route('public.productos.index', ['categorias[]' => $producto->categoria->id_categoria]) }}"
                   class="text-sm text-emerald-600 hover:text-emerald-700 font-medium transition mb-1">
                    {{ $producto->categoria->nombre }}
                </a>
            @endif

            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-stone-800 leading-tight">{{ $producto->nombre }}</h1>

            @if($producto->marca)
                <p class="text-sm text-stone-500 mt-2">
                    Marca: <span class="text-stone-700 font-medium">{{ $producto->marca->nombre }}</span>
                </p>
            @endif

            <div class="mt-6">
                <span id="precio-producto" class="text-3xl sm:text-4xl font-bold text-emerald-700">
                    S/ {{ number_format($producto->precio_desde, 2) }}
                </span>
                @if ($producto->presentaciones->where('activo', true)->count() > 1)
                    <span class="ml-2 text-sm text-stone-500">desde</span>
                @endif
            </div>

            <div class="mt-4 p-4 bg-stone-50 rounded-lg border border-stone-200">
                @if($producto->stock > 0)
                    <div class="flex items-center gap-2 text-emerald-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="font-medium">En stock — {{ $producto->stock_con_unidad }} {{ $producto->stock == 1 ? 'disponible' : 'disponibles' }}</span>
                    </div>
                @else
                    <div class="flex items-center gap-2 text-amber-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <span class="font-medium">Producto sin stock actualmente</span>
                    </div>
                @endif
            </div>

            {{-- Presentaciones --}}
            @if ($producto->presentaciones->where('activo', true)->isNotEmpty())
                <div class="mt-4 p-4 bg-white rounded-lg border border-stone-200">
                    <label class="block text-sm font-medium text-stone-700 mb-1">Presentación</label>
                    <select id="presentacion-select"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        @foreach ($producto->presentaciones->where('activo', true) as $pp)
                            <option value="{{ $pp->id_producto_presentacion }}"
                                data-precio="{{ $pp->precio_venta }}"
                                data-stock="{{ $pp->producto->stock_formateado }}"
                                data-paso="{{ $pp->paso }}"
                                @selected($pp->es_base)>
                                {{ $pp->nombre_completo }} — S/ {{ number_format($pp->precio_venta, 2) }}
                            </option>
                        @endforeach
                    </select>
                    <div class="mt-3 flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-stone-600 mb-1">Cantidad</label>
                            <input type="number" id="cantidad-producto" min="0.001" step="1" value="1"
                                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        </div>
                        <p id="presentacion-stock" class="text-xs text-stone-500 pb-2">
                            Stock: {{ $producto->stock_con_unidad }}
                        </p>
                    </div>
                </div>
            @endif

            {{-- WhatsApp --}}
            <x-whatsapp-button :producto="$producto" />

            {{-- Add to cart --}}
            @auth('clientes')
                <button type="button"
                        data-item-type="producto"
                        data-item-id="{{ $producto->id_producto }}"
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

            @if($producto->descripcion)
                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-stone-700 uppercase tracking-wider mb-2">Descripción</h2>
                    <p class="text-stone-600 leading-relaxed">{{ $producto->descripcion }}</p>
                </div>
            @endif
        </div>
    </div>
</div>

    {{-- Productos relacionados --}}
    @if($relacionados->isNotEmpty())
        <section class="mt-16 border-t border-stone-200 pt-10">
            <div class="max-w-5xl mx-auto px-4">
                <h2 class="text-xl sm:text-2xl font-bold text-stone-800 mb-6">Productos relacionados</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-5 lg:gap-6">
                    @foreach($relacionados as $rel)
                        <x-product-card :producto="$rel" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
    @vite('resources/js/public/tienda/galeria.js')
    @vite('resources/js/public/carrito.js')
@endpush
