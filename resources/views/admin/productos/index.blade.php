<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Productos</h1>
    <button type="button" data-modal="{{ route('admin.productos.create') }}"
        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
        + Nuevo producto
    </button>
</div>

{{-- Filtros rápidos de alertas --}}
<div class="flex flex-wrap gap-2 mb-4">
    @foreach ([
        'todos' => ['label' => 'Todos', 'alerta' => null],
        'riesgo' => ['label' => 'Riesgo', 'alerta' => 'inventario'],
        'bajo' => ['label' => 'Bajo mínimo', 'alerta' => 'bajo'],
        'agotado' => ['label' => 'Agotados', 'alerta' => 'agotado'],
        'por_vencer' => ['label' => 'Por vencer', 'alerta' => 'por_vencer'],
    ] as $chip)
        @php($activo = $alerta === $chip['alerta'])
        <a href="{{ route('admin.productos.index', array_filter(['q' => $search, 'alerta' => $chip['alerta']])) }}"
            class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium transition
                {{ $activo
                    ? 'border-stone-800 bg-stone-800 text-white'
                    : 'border-stone-200 bg-white text-stone-600 hover:bg-stone-50' }}">
            {{ $chip['label'] }}
        </a>
    @endforeach
</div>

<div class="mb-4 relative autocomplete-wrapper">
    <input type="text"
        class="w-full max-w-md rounded-lg border-2 border-stone-400 bg-white py-2.5 text-sm focus:border-black focus:ring-2 focus:ring-stone-300 placeholder-stone-400"
        data-autocomplete="{{ route('admin.productos.search') }}"
        data-search-url="{{ route('admin.productos.index', array_filter(['alerta' => $alerta])) }}"
        placeholder="Buscar producto por nombre..."
        value="{{ $search ?? '' }}" autocomplete="off">
    <div class="autocomplete-dropdown hidden absolute top-full left-0 right-0 max-w-md bg-white border border-stone-300 rounded-lg shadow-lg z-50 mt-1 overflow-hidden"></div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">Foto</th>
                    <th class="px-4 py-3 font-medium">ID</th>
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Categoría</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Marca</th>
                    <th class="px-4 py-3 font-medium">Precio Venta</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Stock</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($productos as $p)
                    <tr class="hover:bg-stone-50 transition" data-producto-row>
                        <td class="px-4 py-3">
                            @if($p->foto_url)
                                <img src="{{ $p->foto_url }}" alt="{{ $p->nombre }}"
                                    class="w-10 h-10 object-cover rounded-lg border border-stone-200">
                            @else
                                <span class="w-10 h-10 flex items-center justify-center bg-stone-100 rounded-lg text-stone-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-stone-500">{{ $p->id_producto }}</td>
                        <td class="px-4 py-3 font-medium text-stone-800">{{ $p->nombre }}</td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $p->categoria->nombre ?? '—' }}</td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $p->marca->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium text-stone-800">S/ {{ number_format($p->precio_venta, 2) }}</td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">
                            @php($vendible = $p->lotes_count > 0 ? (float) $p->stock_vendible_calc : (float) $p->stock)
                            <div class="flex flex-col gap-1">
                                <span>{{ $p->stock_formateado }}</span>
                                @if ($vendible < (float) $p->stock)
                                    <span class="text-[11px] text-stone-400">
                                        Vendible: {{ \App\Helpers\CantidadHelper::formatear($vendible) }}
                                        {{ $p->unidadBase?->codigo }}
                                    </span>
                                @endif
                                <div class="flex flex-wrap gap-1">
                                    @if ($p->lotes_count > 0 && $vendible <= 0)
                                        <span class="inline-block rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">Agotado</span>
                                    @elseif ($p->stock_minimo > 0 && $vendible <= (float) $p->stock_minimo)
                                        <span class="inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Bajo mínimo</span>
                                    @endif
                                    @if ($p->lotes_por_vencer_count > 0)
                                        <span class="inline-block rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold text-orange-700">Por vencer</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($p->estado)
                                <span class="inline-block bg-emerald-100 text-emerald-700 text-xs font-medium px-2.5 py-0.5 rounded-full">Activo</span>
                            @else
                                <span class="inline-block bg-red-100 text-red-700 text-xs font-medium px-2.5 py-0.5 rounded-full">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-modal="{{ route('admin.kardex.producto', $p->id_producto) }}"
                                    class="text-emerald-600 hover:text-emerald-800 p-1.5 hover:bg-emerald-50 rounded-lg transition" title="Ver Kardex">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </button>
                                <button type="button" data-modal="{{ route('admin.productos.edit', $p->id_producto) }}"
                                    class="text-indigo-600 hover:text-indigo-800 p-1.5 hover:bg-indigo-50 rounded-lg transition" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button type="button"
                                    data-toggle-status="{{ route('admin.productos.toggleStatus', $p->id_producto) }}"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 {{ $p->estado ? 'bg-emerald-500' : 'bg-stone-300' }}"
                                    role="switch" aria-checked="{{ $p->estado ? 'true' : 'false' }}"
                                    title="{{ $p->estado ? 'Desactivar' : 'Activar' }}">
                                    <span class="pointer-events-none inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $p->estado ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-stone-400">No hay productos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['items' => $productos])
</div>
