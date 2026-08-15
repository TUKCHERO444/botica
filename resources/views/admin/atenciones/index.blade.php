<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Atenciones</h1>
        @if(!$cajaAbierta)
            <span class="text-xs bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full font-medium">Caja cerrada</span>
        @endif
    </div>
    @if($cajaAbierta)
        <button type="button" data-modal="{{ route('admin.atenciones.create') }}"
            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
            + Nueva atención
        </button>
    @else
        <button type="button" onclick="AdminToast.show('Debe abrir una caja primero para registrar atenciones.', 'warning')"
            class="bg-stone-400 cursor-not-allowed text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
            + Nueva atención
        </button>
    @endif
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">#</th>
                    <th class="px-4 py-3 font-medium">Cliente</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Observación</th>
                    <th class="px-4 py-3 font-medium">Productos</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Servicios</th>
                    <th class="px-4 py-3 font-medium">Total</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Registrado por</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($atenciones as $a)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-500">{{ $a->id_atencion }}</td>
                        <td class="px-4 py-3 font-medium text-stone-800">
                            {{ $a->cliente->nombre }} {{ $a->cliente->apellido_paterno }}
                        </td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500 max-w-[200px] truncate">
                            {{ $a->observacio ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-stone-600">
                            {{ $a->productos->count() > 0 ? $a->productos->count() . ' prod.' : '—' }}
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-600">
                            {{ $a->servicios->count() > 0 ? $a->servicios->count() . ' serv.' : '—' }}
                        </td>
                        <td class="px-4 py-3 font-medium text-stone-800">S/ {{ number_format($a->total, 2) }}</td>
                        <td class="hidden lg:table-cell px-4 py-3 text-stone-500">{{ $a->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-modal="{{ route('admin.atenciones.show', $a->id_atencion) }}"
                                    class="text-sky-600 hover:text-sky-800 p-1.5 hover:bg-sky-50 rounded-lg transition" title="Ver detalle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                                <button type="button" data-modal="{{ route('admin.atenciones.edit', $a->id_atencion) }}"
                                    class="text-indigo-600 hover:text-indigo-800 p-1.5 hover:bg-indigo-50 rounded-lg transition" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button type="button" data-delete="{{ route('admin.atenciones.destroy', $a->id_atencion) }}"
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
                        <td colspan="8" class="px-4 py-8 text-center text-stone-400">No hay atenciones registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
