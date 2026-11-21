@php
    $puedeCrear = true;
@endphp

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Inventario inicial</h1>
        <p class="text-sm text-stone-500 mt-1">
            Carga existencias reales por lote y vencimiento. El borrador no mueve stock hasta confirmarlo.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
        <a href="{{ route('admin.inventario-inicial.plantilla') }}"
            class="text-sm text-stone-600 hover:text-stone-800 px-3 py-2 rounded-lg border border-stone-300 hover:border-stone-400 transition">
            Plantilla CSV
        </a>
        <a href="{{ route('admin.inventario-inicial.exportarProductos') }}"
            class="text-sm text-stone-600 hover:text-stone-800 px-3 py-2 rounded-lg border border-stone-300 hover:border-stone-400 transition">
            Exportar productos
        </a>
        <button type="button" data-modal="{{ route('admin.inventario-inicial.create') }}"
            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition">
            + Nuevo inventario
        </button>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">Correlativo</th>
                    <th class="px-4 py-3 font-medium">Fecha</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Usuario</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium text-right">Líneas</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium text-right">Costo total</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($inventarios as $inventario)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 font-mono font-medium text-stone-800">{{ $inventario->correlativo }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $inventario->fecha?->format('d/m/Y') ?? '—' }}</td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">
                            {{ $inventario->user?->name ?? 'Sistema' }}
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-600 text-right">{{ $inventario->detalles_count }}</td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-600 text-right">
                            S/ {{ number_format((float) $inventario->total_costo, 2) }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full {{ $inventario->estado_clase }}">
                                {{ $inventario->estado_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-modal="{{ route('admin.inventario-inicial.show', $inventario) }}"
                                    class="text-sky-600 hover:text-sky-800 p-1.5 hover:bg-sky-50 rounded-lg transition" title="Ver detalle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                                @if (! $inventario->estaConfirmado())
                                    <button type="button" data-modal="{{ route('admin.inventario-inicial.edit', $inventario) }}"
                                        class="text-indigo-600 hover:text-indigo-800 p-1.5 hover:bg-indigo-50 rounded-lg transition" title="Editar borrador">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                    <button type="button" data-delete="{{ route('admin.inventario-inicial.destroy', $inventario) }}"
                                        class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar borrador">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-stone-400">
                            No hay inventarios iniciales. Crea uno manual o importa un CSV.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 text-xs text-stone-500 bg-stone-50 border border-stone-200 rounded-lg p-3">
    Regla: el inventario inicial solo se carga para productos sin stock ni lotes. Si un producto ya tiene stock, usa un ajuste o regulariza primero.
</div>

@vite('resources/js/admin/inventario-inicial/index.js')


@include('admin.partials.pagination', ['items' => $inventarios])
