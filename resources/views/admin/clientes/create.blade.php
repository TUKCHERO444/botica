<form action="{{ route('admin.clientes.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Nuevo cliente</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">DNI <span class="text-stone-400 font-normal">(opcional)</span></label>
            <div class="flex gap-2">
                <input type="text" name="dni" id="cliente-dni-input" maxlength="8" minlength="8"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <button type="button" id="btn-buscar-dni-cliente"
                    class="px-3 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-stone-300 text-white rounded-lg text-sm font-medium transition disabled:cursor-not-allowed flex items-center gap-1"
                    disabled>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Buscar DNI
                </button>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Nombre</label>
            <input type="text" name="nombre" id="cliente-nombre-input" required maxlength="50"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Apellido Paterno <span class="text-stone-400 font-normal">(opcional)</span></label>
            <input type="text" name="apellido_paterno" id="cliente-apellido-paterno-input" maxlength="25"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Apellido Materno <span class="text-stone-400 font-normal">(opcional)</span></label>
            <input type="text" name="apellido_materno" id="cliente-apellido-materno-input" maxlength="25"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Email <span class="text-stone-400 font-normal">(opcional)</span></label>
            <input type="email" name="email" maxlength="255"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Teléfono <span class="text-stone-400 font-normal">(opcional)</span></label>
            <input type="text" name="telefono" maxlength="9" placeholder="987654321"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Guardar</button>
    </div>
</form>

@vite('resources/js/admin/clientes/create.js')
