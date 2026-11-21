<form data-modal-size="md" action="{{ route('admin.caja.ingreso') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Registrar ingreso</h2>
            <p class="text-xs text-stone-500 mt-1">Aporte de sencillo, cobro de crédito, ajuste u otro ingreso.</p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Método de pago <span class="text-red-500">*</span></label>
                <select name="metodo_pago_id" required
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    @foreach ($metodosPago as $metodo)
                        <option value="{{ $metodo->id_metodo_pago }}">
                            {{ $metodo->nombre }}{{ $metodo->afecta_efectivo ? ' — efectivo' : ' — digital' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Categoría <span class="text-red-500">*</span></label>
                <select name="categoria" required
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    @foreach ($categoriasIngreso as $clave => $etiqueta)
                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Monto (S/) <span class="text-red-500">*</span></label>
            <input type="number" name="monto" step="0.01" min="0.01" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                placeholder="0.00">
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Descripción <span class="text-red-500">*</span></label>
            <textarea name="descripcion" rows="3" required minlength="5" maxlength="500"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                placeholder="Detalle el origen del ingreso..."></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Comprobante / referencia</label>
            <input type="text" name="comprobante" maxlength="100"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                placeholder="Opcional: recibo, N.º de operación">
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Registrar ingreso</button>
    </div>
</form>
