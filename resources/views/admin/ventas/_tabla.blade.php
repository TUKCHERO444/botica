@php($esHistorial = ($modo ?? 'dia') === 'historial')

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">#</th>
                    <th class="px-4 py-3 font-medium">Correlativo</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Cliente</th>
                    <th class="px-4 py-3 font-medium">Total</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Fecha y hora</th>
                    @if ($esHistorial)
                        <th class="hidden xl:table-cell px-4 py-3 font-medium">Registrado por</th>
                    @endif
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($ventas as $v)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-500">{{ $v->id_venta }}</td>
                        <td class="px-4 py-3 font-mono font-medium text-stone-800">{{ $v->correlativo }}</td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">
                            {{ $v->cliente?->nombre }} {{ $v->cliente?->apellido_paterno }}
                        </td>
                        <td class="px-4 py-3 font-medium text-stone-800">S/ {{ number_format((float) $v->total, 2) }}</td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500 text-xs whitespace-nowrap">
                            {{ $v->created_at?->format('d/m/Y H:i') }}
                        </td>
                        @if ($esHistorial)
                            <td class="hidden xl:table-cell px-4 py-3 text-stone-500">{{ $v->user?->name ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full {{ $v->estado_clase }}">
                                {{ $v->estado_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.ventas.ticket', $v->id_venta) }}" target="_blank" rel="noopener"
                                    class="text-emerald-600 hover:text-emerald-800 p-1.5 hover:bg-emerald-50 rounded-lg transition" title="Ver / imprimir ticket">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2V9a2 2 0 00-2-2H9a2 2 0 00-2 2v10a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                    </svg>
                                </a>
                                <button type="button" data-modal="{{ route('admin.ventas.show', $v->id_venta) }}"
                                    class="text-sky-600 hover:text-sky-800 p-1.5 hover:bg-sky-50 rounded-lg transition" title="Ver detalle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                                @if ($v->estaConfirmada())
                                    <button type="button" data-modal="{{ route('admin.ventas.formAnular', $v->id_venta) }}"
                                        class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition" title="Anular venta">
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
                        <td colspan="{{ $esHistorial ? 8 : 7 }}" class="px-4 py-8 text-center text-stone-400">
                            @if ($esHistorial)
                                No hay ventas para los filtros aplicados.
                            @else
                                Aún no registraste ventas hoy.
                                <a href="{{ route('admin.ventas.historial') }}" data-panel="{{ route('admin.ventas.historial') }}"
                                    class="text-emerald-700 hover:text-emerald-900 font-medium">Ver el historial completo</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['items' => $ventas])
</div>
