<form data-modal-size="md" action="{{ route('admin.servicios.update', $servicio->id_servicio) }}" method="POST" class="p-6">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Editar servicio</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ $servicio->nombre }}" required maxlength="25"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Descripción</label>
            <textarea name="descripcion" maxlength="100" rows="3"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">{{ $servicio->descripcion }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Precio (S/)</label>
            <input type="number" name="precio" value="{{ $servicio->precio }}" step="0.01" min="0" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Costo (S/) <span class="text-stone-400 font-normal">(opcional)</span></label>
            <input type="number" name="costo" value="{{ $servicio->costo }}" step="0.01" min="0"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            <p class="text-xs text-stone-400 mt-1">Se usa para calcular el margen del servicio.</p>
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Actualizar</button>
    </div>
</form>
