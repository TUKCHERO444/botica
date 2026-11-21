<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Categorías en Portada</h1>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    @if ($categorias->isEmpty())
        <div class="p-6 text-center text-sm text-stone-400">
            No hay categorías activas en el sistema. Crea categorías desde <a href="#" data-panel="/admin/categorias" class="text-emerald-600 hover:text-emerald-800 underline">Inventario → Categorías</a>.
        </div>
    @else
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($categorias as $categoria)
                @php
                    $enPortada = in_array($categoria->id_categoria, $seleccionadas);
                    $pc = $enPortada ? App\Models\PortadaCategoria::where('categoria_id_categoria', $categoria->id_categoria)->first() : null;
                @endphp
                <div class="border border-stone-200 rounded-lg p-4 transition hover:border-stone-300 {{ $enPortada ? 'bg-emerald-50' : 'bg-white' }}">
                    <div class="flex items-center justify-between mb-3">
                        <span class="font-medium text-stone-800 text-sm">{{ $categoria->nombre }}</span>
                        <button type="button" data-toggle-portada="{{ $categoria->id_categoria }}"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 {{ $enPortada ? 'bg-emerald-500' : 'bg-stone-300' }}"
                            role="switch" aria-checked="{{ $enPortada ? 'true' : 'false' }}"
                            title="{{ $enPortada ? 'Quitar de portada' : 'Mostrar en portada' }}">
                            <span class="pointer-events-none inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $enPortada ? 'translate-x-4' : 'translate-x-0' }}"></span>
                        </button>
                    </div>
                    @if ($enPortada)
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-stone-500">Orden:</label>
                            <input type="number" value="{{ $pc?->orden ?? 0 }}" min="0"
                                data-pc-id="{{ $pc?->id_portada_categoria }}"
                                class="input-orden-portada w-16 text-center rounded border border-stone-300 px-1 py-1 text-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

@vite('resources/js/admin/portada-categorias/index.js')


@include('admin.partials.pagination', ['items' => $categorias])
