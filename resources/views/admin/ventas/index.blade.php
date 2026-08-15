<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Ventas</h1>
        @if(!$cajaAbierta)
            <span class="text-xs bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full font-medium">Caja cerrada</span>
        @endif
    </div>
    @if($cajaAbierta)
        <button type="button" data-modal="{{ route('admin.ventas.create') }}"
            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
            + Nueva venta
        </button>
    @else
        <button type="button" onclick="AdminToast.show('Debe abrir una caja primero para registrar ventas.', 'warning')"
            class="bg-stone-400 cursor-not-allowed text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
            + Nueva venta
        </button>
    @endif
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">#</th>
                    <th class="px-4 py-3 font-medium">Correlativo</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Cliente</th>
                    <th class="px-4 py-3 font-medium">Total</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Registrado por</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($ventas as $v)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-500">{{ $v->id_venta }}</td>
                        <td class="px-4 py-3 font-mono font-medium text-stone-800">{{ $v->correlativo }}</td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">
                            {{ $v->cliente->nombre }} {{ $v->cliente->apellido_paterno }}
                        </td>
                        <td class="px-4 py-3 font-medium text-stone-800">S/ {{ number_format($v->total, 2) }}</td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">{{ $v->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-modal="{{ route('admin.ventas.show', $v->id_venta) }}"
                                    class="text-sky-600 hover:text-sky-800 p-1.5 hover:bg-sky-50 rounded-lg transition" title="Ver detalle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                                <button type="button" data-modal="{{ route('admin.ventas.edit', $v->id_venta) }}"
                                    class="text-indigo-600 hover:text-indigo-800 p-1.5 hover:bg-indigo-50 rounded-lg transition" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button type="button" data-delete="{{ route('admin.ventas.destroy', $v->id_venta) }}"
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
                        <td colspan="6" class="px-4 py-8 text-center text-stone-400">No hay ventas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
