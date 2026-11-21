<form data-modal-size="2xl" action="{{ route('admin.combos.update', $combo) }}" method="POST" class="p-6" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-stone-800">Editar combo</h2>
        <button type="button" onclick="AdminModal.close()" class="text-stone-400 hover:text-stone-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input type="text" name="nombre" maxlength="255" required value="{{ $combo->nombre }}"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Descripción</label>
            <textarea name="descripcion" maxlength="500" rows="2"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">{{ $combo->descripcion }}</textarea>
        </div>

        <div>
            @if($combo->imagen_url)
            <label class="block text-sm font-medium text-stone-700 mb-1">Imagen actual</label>
            <img src="{{ $combo->imagen_url }}" alt="{{ $combo->nombre }}"
                class="w-full h-40 object-cover rounded-lg border border-stone-200 mb-3">
            @endif
            <label class="block text-sm font-medium text-stone-700 mb-1">Nueva imagen (opcional)</label>
            <input type="file" name="imagen" id="imagen" accept="image/jpeg,image/jpg,image/png,image/webp"
                class="w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
            <div id="bloque-preview" class="hidden mt-3">
                <img id="preview-imagen" src="#" alt="Nueva imagen"
                    class="w-full h-40 object-cover rounded-lg border border-stone-200">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Productos <span class="text-red-500">*</span></label>
            <div class="relative mb-3">
                <input type="text" id="buscar-producto-input" placeholder="Buscar producto..."
                    class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <div id="resultados-busqueda" class="hidden absolute z-50 mt-1 w-full bg-white border border-stone-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"></div>
            </div>
            <div id="productos-seleccionados" class="space-y-2">
                @foreach($combo->productos as $producto)
                    <div class="producto-row flex items-center gap-2 p-2 bg-stone-50 rounded-lg" data-id="{{ $producto->id_producto }}" data-precio="{{ $producto->precio_venta }}">
                        <input type="hidden" name="productos[{{ $loop->index }}][id]" value="{{ $producto->id_producto }}">
                        <img src="{{ $producto->foto_url }}" alt="" class="w-10 h-10 object-cover rounded">
                        <span class="flex-1 text-sm text-stone-700">{{ $producto->nombre }}</span>
                        <span class="text-xs text-stone-400">S/ {{ number_format($producto->precio_venta, 2) }}</span>
                        <input type="number" name="productos[{{ $loop->index }}][cantidad]" value="{{ \App\Helpers\CantidadHelper::formatear($producto->pivot->cantidad) }}" min="1"
                            class="producto-cantidad w-16 text-center rounded border border-stone-300 px-1 py-1 text-xs">
                        <span class="producto-subtotal text-xs text-stone-500">S/ {{ number_format($producto->precio_venta * $producto->pivot->cantidad, 2) }}</span>
                        <button type="button" class="btn-quitar-producto text-red-500 hover:text-red-700 text-xs font-medium">Quitar</button>
                    </div>
                @endforeach
            </div>
            <p id="sin-productos-msg" class="hidden text-xs text-red-500 mt-1">Debe agregar al menos un producto.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Precio Sugerido <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-3">
                <span class="text-stone-500">S/</span>
                <input type="number" name="precio_sugerido" id="precio-sugerido" step="0.01" min="0" required value="{{ $combo->precio_sugerido }}"
                    class="w-40 rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <span id="precio-original" class="text-xs text-stone-400"></span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" name="activo" id="activo" value="1" @checked($combo->activo)
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

@vite('resources/js/admin/combos/index.js')
