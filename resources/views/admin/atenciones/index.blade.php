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
                    <th class="px-4 py-3 font-medium">Correlativo</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Cliente</th>
                    <th class="hidden xl:table-cell px-4 py-3 font-medium">Observación</th>
                    <th class="px-4 py-3 font-medium">Productos</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Servicios</th>
                    <th class="px-4 py-3 font-medium">Total</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Registrado por</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($atenciones as $a)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-500">{{ $a->id_atencion }}</td>
                        <td class="px-4 py-3 font-mono font-medium text-stone-800">{{ $a->correlativo }}</td>
                        <td class="hidden sm:table-cell px-4 py-3 font-medium text-stone-800">
                            {{ $a->cliente->nombre }} {{ $a->cliente->apellido_paterno }}
                        </td>
                        <td class="hidden xl:table-cell px-4 py-3 text-stone-500 max-w-[200px] truncate">
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
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full {{ $a->estado_clase }}">
                                {{ $a->estado_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.atenciones.ticket', $a->id_atencion) }}" target="_blank" rel="noopener"
                                    class="text-emerald-600 hover:text-emerald-800 p-1.5 hover:bg-emerald-50 rounded-lg transition" title="Ver / imprimir ticket">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2V9a2 2 0 00-2-2H9a2 2 0 00-2 2v10a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                </a>
                                <button type="button" data-modal="{{ route('admin.atenciones.show', $a->id_atencion) }}"
                                    class="text-sky-600 hover:text-sky-800 p-1.5 hover:bg-sky-50 rounded-lg transition" title="Ver detalle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                                @if ($a->estaConfirmada())
                                    <button type="button" data-modal="{{ route('admin.atenciones.formAnular', $a->id_atencion) }}"
                                        class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition" title="Anular atención">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-stone-400">No hay atenciones registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>


@include('admin.partials.pagination', ['items' => $atenciones])
