<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Ajustes de inventario</h1>
        <p class="text-sm text-stone-500 mt-1">Movimientos formales por lote. Son inmutables: un error se corrige con otro ajuste.</p>
    </div>
    <button type="button" data-modal="{{ route('admin.ajustes.create') }}"
        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
        + Nuevo ajuste
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">Correlativo</th>
                    <th class="px-4 py-3 font-medium">Tipo</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Fecha</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Motivo</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Usuario</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Lotes</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium text-right">Valor</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($ajustes as $ajuste)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 font-mono font-medium text-stone-800">{{ $ajuste->correlativo }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $ajuste->tipo_label }}</td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">
                            {{ $ajuste->fecha ? $ajuste->fecha->format('d/m/Y') : '—' }}
                        </td>
                        <td class="hidden lg:table-cell px-4 py-3 text-stone-500">{{ $ajuste->motivo }}</td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">{{ $ajuste->user?->name ?? '—' }}</td>
                        <td class="hidden lg:table-cell px-4 py-3">
                            <span class="inline-flex items-center gap-1 bg-stone-100 text-stone-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                {{ $ajuste->detalles_count }} {{ $ajuste->detalles_count == 1 ? 'lote' : 'lotes' }}
                            </span>
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-600 text-right font-medium">
                            S/ {{ number_format((float) $ajuste->total, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" data-modal="{{ route('admin.ajustes.show', $ajuste->id_ajuste_inventario) }}"
                                class="text-sky-600 hover:text-sky-800 p-1.5 hover:bg-sky-50 rounded-lg transition" title="Ver detalle">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-stone-400">No hay ajustes registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>


@include('admin.partials.pagination', ['items' => $ajustes])
