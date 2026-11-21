<form data-modal-size="sm" action="{{ route('admin.devoluciones.anular', $devolucion->id_devolucion_proveedor) }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Anular devolución {{ $devolucion->correlativo }}</h2>
            <p class="text-xs text-stone-500 mt-1">
                Se restaura el stock y el lote. Si hubo reembolso a caja, se registra un egreso compensatorio.
            </p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="bg-stone-50 rounded-lg p-4 mb-5 space-y-2">
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Proveedor</span>
            <span class="text-sm font-medium text-stone-800">{{ $devolucion->proveedor->razon_social }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Compra</span>
            <span class="text-sm text-stone-700 font-mono">{{ $devolucion->ingreso?->correlativo ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Total</span>
            <span class="text-sm font-bold text-stone-800">S/ {{ number_format((float) $devolucion->total, 2) }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Reembolso</span>
            <span class="text-sm text-stone-700">{{ $devolucion->reembolsado_a_caja ? 'Ingresó a caja' : 'Fuera de caja' }}</span>
        </div>
    </div>

    <div class="mb-4">
        <label class="block text-sm font-medium text-stone-700 mb-1">Motivo de anulación <span class="text-red-500">*</span></label>
        <textarea name="motivo_anulacion" rows="3" maxlength="255" required
            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
            placeholder="Ej.: devolución registrada por error">{{ old('motivo_anulacion') }}</textarea>
    </div>

    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
        Si la caja del reembolso ya fue cerrada, la anulación se bloqueará para no alterar un cierre.
    </p>

    <div class="flex justify-end gap-3 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-red-600 hover:bg-red-700 text-white rounded-lg transition">Anular devolución</button>
    </div>
</form>
