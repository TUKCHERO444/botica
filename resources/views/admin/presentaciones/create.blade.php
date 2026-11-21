<form data-modal-size="md" action="{{ route('admin.presentaciones.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Nueva presentación</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required maxlength="50"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Abreviatura</label>
            <input type="text" name="abreviatura" value="{{ old('abreviatura') }}" maxlength="10"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Estado</label>
            <input type="hidden" name="activo" value="0">
            <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" name="activo" value="1" checked class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                Activa
            </label>
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit" class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Guardar</button>
    </div>
</form>
