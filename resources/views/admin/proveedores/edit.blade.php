<form action="{{ route('admin.proveedores.update', $proveedor->id_proveedor) }}" method="POST" class="p-6">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Editar proveedor</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">RUC</label>
            <input type="text" name="ruc" value="{{ $proveedor->ruc }}" required maxlength="11" minlength="11"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Razón Social</label>
            <input type="text" name="razon_social" value="{{ $proveedor->razon_social }}" required maxlength="150"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Condición</label>
            <input type="text" name="condicion" value="{{ $proveedor->condicion }}" required maxlength="15"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Estado</label>
            <select name="estado" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="1" {{ $proveedor->estado == 1 ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ $proveedor->estado == 0 ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Dirección</label>
            <input type="text" name="direccion" value="{{ $proveedor->direccion }}" maxlength="255"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Ubigeo</label>
                <input type="text" name="ubigeo" value="{{ $proveedor->ubigeo }}" maxlength="6"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Distrito</label>
                <input type="text" name="distrito" value="{{ $proveedor->distrito }}" maxlength="100"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Provincia</label>
                <input type="text" name="provincia" value="{{ $proveedor->provincia }}" maxlength="100"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Departamento</label>
                <input type="text" name="departamento" value="{{ $proveedor->departamento }}" maxlength="100"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Actualizar</button>
    </div>
</form>
