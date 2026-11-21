<form data-modal-size="lg" action="{{ route('admin.ingresos.recibir', $ingreso->id_ingreso) }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Recibir compra</h2>
            <p class="text-xs text-stone-500 mt-1">
                Al recibir se crean los lotes, se aumenta el stock y se registra el pago si corresponde.
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
            <span class="text-sm font-medium text-stone-800">{{ $ingreso->proveedor->razon_social }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Documento</span>
            <span class="text-sm text-stone-700">
                {{ $ingreso->tipo_documento_label }} <span class="font-mono">{{ $ingreso->numero_documento }}</span>
            </span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Líneas</span>
            <span class="text-sm text-stone-700">{{ $ingreso->detalles->count() }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Total</span>
            <span class="text-sm font-bold text-stone-800">S/ {{ number_format((float) $ingreso->total, 2) }}</span>
        </div>
    </div>

    <div class="mb-5">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-100 text-stone-600 text-left">
                    <th class="px-3 py-2 font-medium">Producto</th>
                    <th class="px-3 py-2 font-medium">Lote</th>
                    <th class="px-3 py-2 font-medium">Vence</th>
                    <th class="px-3 py-2 font-medium text-center">Cantidad</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @foreach ($ingreso->detalles as $detalle)
                    <tr>
                        <td class="px-3 py-2 text-stone-700">
                            {{ $detalle->producto?->nombre }}
                            <span class="text-stone-400 text-xs">({{ $detalle->presentacion?->nombre_completo }})</span>
                        </td>
                        <td class="px-3 py-2 font-mono text-stone-600">{{ $detalle->lote_codigo }}</td>
                        <td class="px-3 py-2 text-stone-600">{{ $detalle->fecha_vencimiento?->format('d/m/Y') }}</td>
                        <td class="px-3 py-2 text-stone-600 text-center">{{ \App\Helpers\CantidadHelper::formatear($detalle->cantidad) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-stone-50 border border-stone-200 rounded-lg p-4 mb-5">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-stone-700">
                <input type="hidden" name="pagado_desde_caja" value="0">
                <input type="checkbox" name="pagado_desde_caja" value="1" @checked($ingreso->pagado_desde_caja)
                    class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                ¿Pagado desde caja?
            </label>
            <div class="flex-1">
                <select name="metodo_pago"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="">Método de pago</option>
                    @foreach (\App\Models\Ingreso::METODOS_PAGO as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected($ingreso->metodo_pago === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <input type="text" name="referencia_pago" value="{{ $ingreso->referencia_pago }}" maxlength="100"
                    placeholder="Nro. de operación o nota (opcional)"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
        </div>
        @if ($cajaAbierta)
            <p class="text-xs text-emerald-700 mt-2">
                Caja abierta: #{{ $cajaAbierta->id_caja }} — disponible S/ {{ number_format($cajaAbierta->monto_actual, 2) }}.
            </p>
        @else
            <p class="text-xs text-amber-700 mt-2">
                No hay caja abierta. Registra el pago como externo o abre una caja antes de recibir.
            </p>
        @endif
    </div>

    <div class="flex justify-end gap-3 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Recibir y actualizar stock</button>
    </div>
</form>
