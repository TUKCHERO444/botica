<form data-modal-size="3xl" action="{{ route('admin.ajustes.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Nuevo ajuste de inventario</h2>
            <p class="text-xs text-stone-500 mt-1">
                Se registra por lote y en unidad base. No se puede editar ni eliminar.
            </p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Tipo <span class="text-red-500">*</span></label>
            <select id="ajuste-tipo" name="tipo" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                @foreach (\App\Models\AjusteInventario::TIPOS as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Fecha</label>
            <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-stone-700 mb-1">Motivo <span class="text-red-500">*</span></label>
            <input type="text" name="motivo" value="{{ old('motivo') }}" maxlength="255" required
                placeholder="Ej.: conteo mensual, producto roto, lote vencido"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
    </div>

    <div class="mb-4">
        <label class="block text-sm font-medium text-stone-700 mb-1">Observaciones</label>
        <textarea name="observaciones" rows="2" maxlength="500"
            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
            placeholder="Detalle opcional">{{ old('observaciones') }}</textarea>
    </div>

    <div class="mb-4">
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium text-stone-700">Lotes a ajustar</label>
            <button type="button" id="agregar-lote"
                class="text-xs bg-emerald-100 text-emerald-700 hover:bg-emerald-200 px-3 py-1.5 rounded-lg transition font-medium">
                + Agregar lote
            </button>
        </div>

        <div id="lotes-container" class="space-y-2">
            <div class="lote-row">
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-start">
                    <div class="sm:col-span-9">
                        <label class="block text-xs font-medium text-stone-600 mb-1">Producto / lote <span class="text-red-500">*</span></label>
                        <div class="lote-search"
                            data-field="lineas[0][producto_lote_id]"
                            data-url="{{ route('admin.ajustes.lotes.search') }}"
                            data-placeholder="Buscar lote por producto o código..."></div>
                        <p class="lote-info text-[11px] leading-tight text-stone-500 mt-1 hidden"></p>
                        <p class="lote-equivalencia text-[11px] leading-tight text-stone-500 mt-0.5 hidden"></p>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="ajuste-cantidad-label block text-xs font-medium text-stone-600 mb-1">Cantidad (unidad base) <span class="text-red-500">*</span></label>
                        <input type="number" name="lineas[0][cantidad]" value="0" min="0" step="0.001" required
                            class="lote-cantidad w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-right focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <div class="sm:col-span-12 flex justify-end">
                        <button type="button" class="eliminar-lote text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <p class="text-xs text-stone-500 mt-2" id="ajuste-help">
            Para conteo físico, ingresa el stock final contado. El sistema calcula la diferencia.
        </p>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-stone-200">
        <div class="text-sm text-stone-600">
            Valor del ajuste: <span class="font-bold text-stone-800">S/ <span id="ajuste-total">0.00</span></span>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" data-close-modal
                class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Registrar ajuste</button>
        </div>
    </div>
</form>

@vite('resources/js/admin/ajustes/create.js')
