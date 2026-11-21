<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Presentaciones</h1>
        <p class="text-sm text-stone-500 mt-1">Catálogo global de presentaciones (caja, blíster, frasco, etc.).</p>
    </div>
    <button type="button" data-modal="{{ route('admin.presentaciones.create') }}"
        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
        + Nueva presentación
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="px-4 py-3 font-medium">Abreviatura</th>
                    <th class="px-4 py-3 font-medium">Productos asignados</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($presentaciones as $presentacion)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 font-medium text-stone-800">{{ $presentacion->nombre }}</td>
                        <td class="px-4 py-3 text-stone-500">{{ $presentacion->abreviatura ?? '—' }}</td>
                        <td class="px-4 py-3 text-stone-500">{{ $presentacion->producto_presentaciones_count }}</td>
                        <td class="px-4 py-3">
                            @if ($presentacion->activo)
                                <span class="inline-block bg-emerald-100 text-emerald-700 text-xs font-medium px-2.5 py-0.5 rounded-full">Activa</span>
                            @else
                                <span class="inline-block bg-red-100 text-red-700 text-xs font-medium px-2.5 py-0.5 rounded-full">Inactiva</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-modal="{{ route('admin.presentaciones.edit', $presentacion) }}"
                                    class="text-indigo-600 hover:text-indigo-800 p-1.5 hover:bg-indigo-50 rounded-lg transition" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button type="button" data-delete="{{ route('admin.presentaciones.destroy', $presentacion) }}"
                                    class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-stone-400">No hay presentaciones registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>


@include('admin.partials.pagination', ['items' => $presentaciones])
