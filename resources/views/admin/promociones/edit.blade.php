<form data-modal-size="2xl" action="{{ route('admin.promociones.update', $promocion) }}" method="POST" class="p-6" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-stone-800">Editar promoción</h2>
        <button type="button" onclick="AdminModal.close()" class="text-stone-400 hover:text-stone-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Imagen actual</label>
            <img src="{{ $promocion->imagen_url }}" alt="{{ $promocion->titulo ?? '' }}"
                class="w-full h-40 object-cover rounded-lg border border-stone-200 mb-3">
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Nueva imagen (opcional)</label>
            <input type="file" name="imagen" id="imagen" accept="image/jpeg,image/jpg,image/png,image/webp"
                class="w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
            <div id="bloque-preview" class="hidden mt-3">
                <img id="preview-imagen" src="#" alt="Nueva imagen"
                    class="w-full h-40 object-cover rounded-lg border border-stone-200">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Título</label>
                <input type="text" name="titulo" maxlength="255" value="{{ $promocion->titulo }}"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Subtítulo</label>
                <input type="text" name="subtitulo" maxlength="255" value="{{ $promocion->subtitulo }}"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Layout <span class="text-red-500">*</span></label>
            <select name="tipo_layout" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="principal" @selected($promocion->tipo_layout === 'principal')>Principal (100% ancho)</option>
                <option value="secundaria" @selected($promocion->tipo_layout === 'secundaria')>Secundaria (25% ancho)</option>
                <option value="terciaria" @selected($promocion->tipo_layout === 'terciaria')>Terciaria (50% ancho)</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Tipo de promoción</label>
            <select name="tipo_promocion" id="tipo_promocion"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Simple (enlace manual)</option>
                <option value="descuento" @selected($promocion->tipo_promocion === 'descuento')>Descuento</option>
                <option value="combo" @selected($promocion->tipo_promocion === 'combo')>Combo</option>
            </select>
        </div>

        {{-- Campos de descuento --}}
        <div id="campos-descuento" class="space-y-4 p-4 bg-amber-50 rounded-lg border border-amber-200 {{ $promocion->tipo_promocion === 'descuento' ? '' : 'hidden' }}">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Ámbito <span class="text-red-500">*</span></label>
                <select name="ambito" id="ambito"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="">Seleccione ámbito</option>
                    <option value="producto" @selected($promocion->ambito === 'producto')>Producto específico</option>
                    <option value="categoria" @selected($promocion->ambito === 'categoria')>Categoría</option>
                    <option value="marca" @selected($promocion->ambito === 'marca')>Marca</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Entidad <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="text" id="buscar-entidad-input" placeholder="Buscar..."
                        class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                        value="{{ $promocion->ambito_id ? 'ID: ' . $promocion->ambito_id : '' }}">
                    <input type="hidden" name="ambito_id" id="ambito_id" value="{{ $promocion->ambito_id }}">
                    <div id="resultados-entidad" class="hidden absolute z-50 mt-1 w-full bg-white border border-stone-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"></div>
                </div>
                <p id="entidad-seleccionada" class="text-xs text-emerald-600 mt-1 {{ $promocion->ambito_id ? '' : 'hidden' }}">
                    {{ $promocion->ambito_id ? 'ID seleccionado: ' . $promocion->ambito_id : '' }}
                </p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1">Tipo descuento <span class="text-red-500">*</span></label>
                    <select name="descuento_tipo"
                        class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <option value="porcentaje" @selected($promocion->descuento_tipo === 'porcentaje')>Porcentaje (%)</option>
                        <option value="fijo" @selected($promocion->descuento_tipo === 'fijo')>Monto fijo (S/)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1">Valor <span class="text-red-500">*</span></label>
                    <input type="number" name="descuento_valor" step="0.01" min="0" max="999999" value="{{ $promocion->descuento_valor }}"
                        class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
            </div>
        </div>

        {{-- Campos de combo --}}
        <div id="campos-combo" class="space-y-4 p-4 bg-purple-50 rounded-lg border border-purple-200 {{ $promocion->tipo_promocion === 'combo' ? '' : 'hidden' }}">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1">Combo <span class="text-red-500">*</span></label>
                <select name="combo_id_combo"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="">Seleccione un combo</option>
                    @foreach($combos as $combo)
                        <option value="{{ $combo->id_combo }}" @selected($promocion->combo_id_combo === $combo->id_combo)>{{ $combo->nombre }} — S/ {{ number_format($combo->precio_sugerido, 2) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Enlace manual --}}
        <div id="campos-enlace">
            <label class="block text-sm font-medium text-stone-700 mb-1">Enlace (opcional)</label>
            <input type="url" name="enlace" maxlength="255" value="{{ $promocion->enlace }}"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            <p class="text-xs text-stone-400 mt-1">Solo para promociones simples.</p>
        </div>

        <div class="flex items-center gap-2">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" id="activo" value="1" @checked($promocion->activo)
                class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
            <label for="activo" class="text-sm text-stone-700">Activo</label>
        </div>
    </div>

    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-stone-200">
        <button type="button" onclick="AdminModal.close()"
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Guardar cambios</button>
    </div>
</form>

@vite('resources/js/admin/promociones/index.js')
