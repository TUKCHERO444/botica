<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Compras</h1>
        <p class="text-sm text-stone-500 mt-1">Compras a proveedores con documento, recepción y control de pago.</p>
    </div>
    <button type="button" data-modal="{{ route('admin.ingresos.create') }}"
        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
        + Nueva compra
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">Correlativo</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Proveedor</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Documento</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Fecha</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium text-right">Total</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Pago</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Productos</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($ingresos as $i)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 font-mono font-medium text-stone-800">
                            {{ $i->correlativo ?? 'BORRADOR #'.$i->id_ingreso }}
                        </td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $i->proveedor->razon_social }}</td>
                        <td class="hidden lg:table-cell px-4 py-3 text-stone-500">
                            {{ $i->tipo_documento_label ?? '—' }}
                            @if ($i->numero_documento)
                                <span class="font-mono text-xs text-stone-600">{{ $i->numero_documento }}</span>
                            @endif
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">
                            {{ $i->fecha ? $i->fecha->format('d/m/Y') : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full {{ $i->estado_clase }}">
                                {{ $i->estado_label }}
                            </span>
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-600 text-right font-medium">
                            S/ {{ number_format((float) $i->total, 2) }}
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-600">
                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full {{ $i->pagado_desde_caja ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-600' }}">
                                {{ $i->pagado_desde_caja ? 'Caja' : 'Fuera de caja' }}
                            </span>
                            @if ($i->metodo_pago_label)
                                <div class="text-[11px] text-stone-400 mt-0.5">{{ $i->metodo_pago_label }}</div>
                            @endif
                        </td>
                        <td class="hidden lg:table-cell px-4 py-3">
                            <span class="inline-flex items-center gap-1 bg-stone-100 text-stone-700 text-xs font-medium px-2.5 py-0.5 rounded-full">
                                {{ $i->detalles_count }} {{ $i->detalles_count == 1 ? 'línea' : 'líneas' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-modal="{{ route('admin.ingresos.show', $i->id_ingreso) }}"
                                    class="text-sky-600 hover:text-sky-800 p-1.5 hover:bg-sky-50 rounded-lg transition" title="Ver detalle">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>

                                @if ($i->estaEnBorrador())
                                    <button type="button" data-modal="{{ route('admin.ingresos.formRecibir', $i->id_ingreso) }}"
                                        class="text-emerald-600 hover:text-emerald-800 p-1.5 hover:bg-emerald-50 rounded-lg transition" title="Recibir compra">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>

                                    <button type="button" data-modal="{{ route('admin.ingresos.edit', $i->id_ingreso) }}"
                                        class="text-indigo-600 hover:text-indigo-800 p-1.5 hover:bg-indigo-50 rounded-lg transition" title="Editar borrador">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>

                                    <button type="button" data-delete="{{ route('admin.ingresos.destroy', $i->id_ingreso) }}"
                                        class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar borrador">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                @endif

                                @if ($i->estaRecibida())
                                    @can('devoluciones')
                                    <button type="button" data-modal="{{ route('admin.devoluciones.create', ['ingreso_id' => $i->id_ingreso]) }}"
                                        class="text-amber-600 hover:text-amber-800 p-1.5 hover:bg-amber-50 rounded-lg transition" title="Devolver al proveedor">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                        </svg>
                                    </button>
                                    @endcan

                                    <button type="button" data-modal="{{ route('admin.ingresos.formAnular', $i->id_ingreso) }}"
                                        class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition" title="Anular compra">
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
                        <td colspan="9" class="px-4 py-8 text-center text-stone-400">No hay compras registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>


@include('admin.partials.pagination', ['items' => $ingresos])
