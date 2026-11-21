<form data-modal-size="md" action="{{ route('admin.unidades-medida.update', $unidadMedida) }}" method="POST" class="p-6">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Editar unidad de medida</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Código <span class="text-red-500">*</span></label>
                <input type="text" name="codigo" value="{{ old('codigo', $unidadMedida->codigo) }}" required maxlength="10"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm font-mono uppercase focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                <input type="text" name="nombre" value="{{ old('nombre', $unidadMedida->nombre) }}" required maxlength="50"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Presentación base</label>
            <select name="presentacion_base_id"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Sin presentación base</option>
                @foreach ($presentaciones as $presentacion)
                    <option value="{{ $presentacion->id_presentacion }}" @selected(old('presentacion_base_id', $unidadMedida->presentacion_base_id) == $presentacion->id_presentacion)>
                        {{ $presentacion->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap gap-6">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Fracción</label>
                <input type="hidden" name="permite_fraccion" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="permite_fraccion" value="1" @checked($unidadMedida->permite_fraccion) class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                    Permite cantidades fraccionadas
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Estado</label>
                <input type="hidden" name="activo" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="activo" value="1" @checked($unidadMedida->activo) class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                    Activa
                </label>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit" class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Actualizar</button>
    </div>
</form>
