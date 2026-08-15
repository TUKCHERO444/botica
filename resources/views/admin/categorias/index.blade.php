<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Categorías</h1>
    <button type="button" data-modal="{{ route('admin.categorias.create') }}"
        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
        + Nueva categoría
    </button>
</div>

<div class="mb-4 relative autocomplete-wrapper">
    <input type="text"
        class="w-full max-w-md rounded-lg border-2 border-stone-400 bg-white py-2.5 text-sm focus:border-black focus:ring-2 focus:ring-stone-300 placeholder-stone-400"
        data-autocomplete="{{ route('admin.categorias.search') }}"
        data-search-url="{{ route('admin.categorias.index') }}"
        placeholder="Buscar categoría por nombre..."
        value="{{ $search ?? '' }}" autocomplete="off">
    <div class="autocomplete-dropdown hidden absolute top-full left-0 right-0 max-w-md bg-white border border-stone-300 rounded-lg shadow-lg z-50 mt-1 overflow-hidden"></div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">ID</th>
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Descripción</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Cantidad</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($categorias as $c)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-500">{{ $c->id_categoria }}</td>
                        <td class="px-4 py-3 font-medium text-stone-800">{{ $c->nombre }}</td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $c->descripcion ?? '—' }}</td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">{{ $c->cantidad }}</td>
                        <td class="px-4 py-3">
                            @if ($c->estado)
                                <span class="inline-block bg-emerald-100 text-emerald-700 text-xs font-medium px-2.5 py-0.5 rounded-full">Activo</span>
                            @else
                                <span class="inline-block bg-red-100 text-red-700 text-xs font-medium px-2.5 py-0.5 rounded-full">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-modal="{{ route('admin.categorias.edit', $c->id_categoria) }}"
                                    class="text-indigo-600 hover:text-indigo-800 p-1.5 hover:bg-indigo-50 rounded-lg transition" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button type="button"
                                    data-toggle-status="{{ route('admin.categorias.toggleStatus', $c->id_categoria) }}"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 {{ $c->estado ? 'bg-emerald-500' : 'bg-stone-300' }}"
                                    role="switch" aria-checked="{{ $c->estado ? 'true' : 'false' }}"
                                    title="{{ $c->estado ? 'Desactivar' : 'Activar' }}">
                                    <span class="pointer-events-none inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $c->estado ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-stone-400">No hay categorías registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
