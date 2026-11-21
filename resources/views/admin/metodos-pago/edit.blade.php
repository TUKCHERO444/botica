<form data-modal-size="md" action="{{ route('admin.metodos-pago.update', $metodoPago->id_metodo_pago) }}" method="POST" class="p-6">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Editar método de pago</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" value="{{ $metodoPago->nombre }}" required maxlength="50"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Descripción</label>
            <input type="text" name="descripcion" value="{{ $metodoPago->descripcion }}" maxlength="100"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Estado</label>
            <select name="estado" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="1" {{ $metodoPago->estado ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ ! $metodoPago->estado ? 'selected' : '' }}>Inactivo</option>
            </select>
            <p class="mt-1 text-xs text-stone-500">Al desactivarlo dejará de mostrarse en los formularios, pero se conservará el historial de pagos.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">¿Afecta el efectivo de caja?</label>
            <select name="afecta_efectivo"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="1" {{ $metodoPago->afecta_efectivo ? 'selected' : '' }}>Sí, entra al cajón</option>
                <option value="0" {{ ! $metodoPago->afecta_efectivo ? 'selected' : '' }}>No, es digital</option>
            </select>
            <p class="mt-1 text-xs text-stone-500">Solo los métodos que afectan efectivo se cuentan en el arqueo.</p>
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Actualizar</button>
    </div>
</form>
