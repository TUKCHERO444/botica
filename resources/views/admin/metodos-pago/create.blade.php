<form data-modal-size="md" action="{{ route('admin.metodos-pago.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Nuevo método de pago</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" required maxlength="50" placeholder="Ej.: Efectivo, Yape, Plin, BCP"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Descripción</label>
            <input type="text" name="descripcion" maxlength="100" placeholder="Opcional"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Estado</label>
            <select name="estado" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">¿Afecta el efectivo de caja?</label>
            <select name="afecta_efectivo"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="1">Sí, entra al cajón</option>
                <option value="0">No, es digital (Yape, Plin, tarjeta, transferencia)</option>
            </select>
            <p class="mt-1 text-xs text-stone-500">Solo los métodos que afectan efectivo se cuentan en el arqueo.</p>
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Guardar</button>
    </div>
</form>
