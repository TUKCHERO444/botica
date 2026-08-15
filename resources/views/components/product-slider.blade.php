<section class="max-w-7xl mx-auto px-4 py-8" aria-label="{{ $titulo }}">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl sm:text-2xl font-bold text-stone-800">{{ $titulo }}</h2>
        <div class="flex gap-2">
            <button id="slider-{{ $id }}-prev" aria-label="Anterior"
                class="bg-stone-200 hover:bg-stone-300 text-stone-700 rounded-full w-9 h-9 flex items-center justify-center transition">
                &#8249;
            </button>
            <button id="slider-{{ $id }}-next" aria-label="Siguiente"
                class="bg-stone-200 hover:bg-stone-300 text-stone-700 rounded-full w-9 h-9 flex items-center justify-center transition">
                &#8250;
            </button>
        </div>
    </div>

    <div id="slider-{{ $id }}" class="relative overflow-hidden cursor-grab active:cursor-grabbing select-none">
        <div id="slider-{{ $id }}-track" class="flex transition-transform duration-500 ease-in-out will-change-transform">
            @foreach ($productos as $prod)
                <div class="w-1/2 sm:w-1/3 lg:w-1/5 shrink-0 px-2">
                    <a href="{{ route('public.productos.show', $prod) }}"
                       class="block bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden hover:shadow-md transition group">
                        @if($prod->foto_url)
                            <img src="{{ $prod->foto_url }}" alt="{{ $prod->nombre }}"
                                class="w-full aspect-square object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="w-full aspect-square bg-stone-100 flex items-center justify-center text-stone-400 text-sm">
                                Sin foto
                            </div>
                        @endif
                        <div class="p-3">
                            <h3 class="text-sm font-semibold text-stone-800 truncate group-hover:text-emerald-700 transition-colors">{{ $prod->nombre }}</h3>
                            <p class="text-emerald-700 font-bold mt-1">S/ {{ number_format($prod->precio_venta, 2) }}</p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
