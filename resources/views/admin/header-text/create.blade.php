<form action="{{ route('admin.header-text.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-stone-800">Nuevo texto para el header</h2>
        <button type="button" onclick="AdminModal.close()" class="text-stone-400 hover:text-stone-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Texto <span class="text-red-500">*</span></label>
            <input type="text" name="texto" maxlength="60" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                placeholder="Ej: Envíos gratis en compras mayores a S/99">
            <p class="text-xs text-stone-400 mt-1">Máximo 60 caracteres.</p>
        </div>

        <div class="flex items-center gap-2">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" id="activo" value="1" checked
                class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
            <label for="activo" class="text-sm text-stone-700">Activo</label>
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" onclick="AdminModal.close()"
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Guardar</button>
    </div>
</form>

@vite('resources/js/admin/header-text/index.js')
