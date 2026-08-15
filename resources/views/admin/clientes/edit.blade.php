<form action="{{ route('admin.clientes.update', $cliente->id_cliente) }}" method="POST" class="p-6">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Editar cliente</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">DNI <span class="text-stone-400 font-normal">(opcional)</span></label>
            <input type="text" name="dni" value="{{ $cliente->dni }}" maxlength="8" minlength="8"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Nombre</label>
            <input type="text" name="nombre" value="{{ $cliente->nombre }}" required maxlength="50"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Apellido Paterno <span class="text-stone-400 font-normal">(opcional)</span></label>
            <input type="text" name="apellido_paterno" value="{{ $cliente->apellido_paterno }}" maxlength="25"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Apellido Materno <span class="text-stone-400 font-normal">(opcional)</span></label>
            <input type="text" name="apellido_materno" value="{{ $cliente->apellido_materno }}" maxlength="25"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Email</label>
            @if ($cliente->registro_web)
                <input type="email" name="email" value="{{ $cliente->email }}" maxlength="255" readonly
                    class="w-full rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm text-stone-500 outline-none cursor-not-allowed">
                <p class="text-xs text-stone-400 mt-1">Registrado desde la web, no editable desde aquí.</p>
            @else
                <input type="email" name="email" value="{{ $cliente->email }}" maxlength="255"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            @endif
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Teléfono <span class="text-stone-400 font-normal">(opcional)</span></label>
            <input type="text" name="telefono" value="{{ $cliente->telefono }}" maxlength="9" placeholder="987654321"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
    </div>
    @if ($cliente->registro_web)
    <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
        <p class="text-sm text-green-700 font-medium flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Cliente registrado desde la web
        </p>
    </div>
    @endif

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Actualizar</button>
    </div>
</form>
