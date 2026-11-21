@props(['producto'])

<a href="{{ route('public.productos.show', $producto) }}"
   class="group bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden hover:shadow-lg transition-all duration-200 flex flex-col">

    <div class="relative overflow-hidden bg-stone-100 aspect-square">
        @if($producto->foto_url)
            <img src="{{ $producto->foto_url }}"
                 alt="{{ $producto->nombre }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        @else
            <div class="w-full h-full flex items-center justify-center text-stone-300">
                <svg class="w-14 h-14 sm:w-16 sm:h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        @endif

        @if($producto->stock <= 0)
            <span class="absolute top-2 left-2 bg-amber-500 text-white text-xs font-semibold px-2.5 py-1 rounded-full">
                Sin stock
            </span>
        @endif
    </div>

    <div class="p-4 sm:p-5 flex flex-col flex-1">
        @if($producto->categoria)
            <p class="text-xs sm:text-sm text-emerald-600 font-medium truncate">{{ $producto->categoria->nombre }}</p>
        @endif
        <h3 class="text-sm sm:text-base lg:text-lg font-semibold text-stone-800 truncate mt-1 group-hover:text-emerald-700 transition-colors">
            {{ $producto->nombre }}
        </h3>
        <p class="text-emerald-700 font-bold mt-auto pt-3 text-base sm:text-lg lg:text-xl">
            @if ($producto->presentaciones->where('activo', true)->count() > 1)
                <span class="text-xs font-medium text-stone-500">Desde</span>
            @endif
            S/ {{ number_format($producto->precio_desde, 2) }}
        </p>
    </div>
</a>
