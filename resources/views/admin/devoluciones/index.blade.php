<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Devoluciones a proveedor</h1>
        <p class="text-sm text-stone-500 mt-1">Salidas de mercadería por lote, con reembolso opcional a caja.</p>
    </div>
    <button type="button" data-modal="{{ route('admin.devoluciones.create') }}"
        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
        + Nueva devolución
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">Correlativo</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Proveedor</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Compra</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Fecha</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium text-right">Total</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Reembolso</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($devoluciones as $devolucion)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 font-mono font-medium text-stone-800">{{ $devolucion->correlativo }}</td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $devolucion->proveedor->razon_social }}</td>
                        <td class="hidden lg:table-cell px-4 py-3 text-stone-500 font-mono text-xs">
                            {{ $devolucion->ingreso?->correlativo ?? '—' }}
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">
                            {{ $devolucion->fecha ? $devolucion->fecha->format('d/m/Y') : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full {{ $devolucion->estado_clase }}">
                                {{ $devolucion->estado_label }}
                            </span>
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-600 text-right font-medium">
                            S/ {{ number_format((float) $devolucion->total, 2) }}
                        </td>
                        <td class="hidden lg:table-cell px-4 py-3 text-stone-600">
                            {{ $devolucion->reembolsado_a_caja ? 'A caja' : 'Fuera de caja' }}
                            @if ($devolucion->metodo_pago_label)
                                <div class="text-[11px] text-stone-400 mt-0.5">{{ $devolucion->metodo_pago_label }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-modal="{{ route('admin.devoluciones.show', $devolucion->id_devolucion_proveedor) }}"
                                    class="text-sky-600 hover:text-sky-800 p-1.5 hover:bg-sky-50 rounded-lg transition" title="Ver detalle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>

                                @if ($devolucion->estaRegistrada())
                                    <button type="button" data-modal="{{ route('admin.devoluciones.formAnular', $devolucion->id_devolucion_proveedor) }}"
                                        class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition" title="Anular devolución">
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
                        <td colspan="8" class="px-4 py-8 text-center text-stone-400">No hay devoluciones registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>


@include('admin.partials.pagination', ['items' => $devoluciones])
