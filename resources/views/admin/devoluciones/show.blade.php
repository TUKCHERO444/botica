<div data-modal-size="2xl" class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Devolución {{ $devolucion->correlativo }}</h2>
            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full mt-1 {{ $devolucion->estado_clase }}">
                {{ $devolucion->estado_label }}
            </span>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="bg-stone-50 rounded-lg p-4 mb-5 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Proveedor</span>
            <span class="text-sm font-medium text-stone-800 text-right">{{ $devolucion->proveedor->razon_social }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Compra</span>
            <span class="text-sm text-stone-700 font-mono">{{ $devolucion->ingreso?->correlativo ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Fecha</span>
            <span class="text-sm text-stone-700">{{ $devolucion->fecha?->format('d/m/Y') ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Registrado por</span>
            <span class="text-sm text-stone-700">{{ $devolucion->user?->name ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Reembolso</span>
            <span class="text-sm text-stone-700">
                {{ $devolucion->reembolsado_a_caja ? 'Ingresó a caja' : 'Fuera de caja' }}
                @if ($devolucion->metodo_pago_label)
                    — {{ $devolucion->metodo_pago_label }}
                @endif
            </span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Referencia</span>
            <span class="text-sm text-stone-700">{{ $devolucion->referencia_pago ?: '—' }}</span>
        </div>
        <div class="sm:col-span-2 flex justify-between gap-4">
            <span class="text-sm text-stone-500">Motivo</span>
            <span class="text-sm text-stone-700 text-right">{{ $devolucion->motivo }}</span>
        </div>
        @if ($devolucion->estado === \App\Models\DevolucionProveedor::ESTADO_ANULADA)
            <div class="sm:col-span-2 flex justify-between gap-4 border-t border-stone-200 pt-2">
                <span class="text-sm text-red-600">Anulada</span>
                <span class="text-sm text-red-700 text-right">
                    {{ $devolucion->anulada_at?->format('d/m/Y H:i') }} — {{ $devolucion->motivo_anulacion }}
                </span>
            </div>
        @endif
    </div>

    <div class="mb-4">
        <h3 class="text-sm font-semibold text-stone-700 mb-2">Lotes devueltos</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-stone-100 text-stone-600 text-left">
                        <th class="px-3 py-2 font-medium">Producto</th>
                        <th class="px-3 py-2 font-medium">Presentación</th>
                        <th class="px-3 py-2 font-medium">Lote</th>
                        <th class="px-3 py-2 font-medium text-center">Cantidad</th>
                        <th class="px-3 py-2 font-medium text-center">Unidad base</th>
                        <th class="px-3 py-2 font-medium text-right">Costo unit.</th>
                        <th class="px-3 py-2 font-medium text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @forelse ($devolucion->detalles as $detalle)
                        <tr>
                            <td class="px-3 py-2 text-stone-700">{{ $detalle->producto?->nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-stone-600">{{ $detalle->presentacion?->nombre_completo ?? '—' }}</td>
                            <td class="px-3 py-2 font-mono text-stone-600">{{ $detalle->lote?->codigo_lote ?? '—' }}</td>
                            <td class="px-3 py-2 text-stone-600 text-center">{{ \App\Helpers\CantidadHelper::formatear($detalle->cantidad) }}</td>
                            <td class="px-3 py-2 text-stone-600 text-center">{{ \App\Helpers\CantidadHelper::formatear($detalle->cantidad_base) }}</td>
                            <td class="px-3 py-2 text-stone-600 text-right">
                                S/ {{ number_format((float) $detalle->costo_unitario_base * (float) $detalle->factor, 2) }}
                            </td>
                            <td class="px-3 py-2 text-stone-600 text-right font-medium">S/ {{ number_format((float) $detalle->costo_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-stone-400">No hay líneas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-end border-t border-stone-200 pt-3">
        <div class="text-sm font-bold text-stone-800">Total devuelto: S/ {{ number_format((float) $devolucion->total, 2) }}</div>
    </div>

    <div class="flex justify-end pt-4">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
    </div>
</div>
