<form action="{{ route('admin.productos.update', $producto->id_producto) }}" method="POST" class="p-8" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between mb-8">
        <h2 class="text-xl font-bold text-stone-800">Editar producto</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
        <div class="lg:col-span-3 space-y-6">
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1.5">Nombre</label>
                <input type="text" name="nombre" value="{{ $producto->nombre }}" required maxlength="50"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1.5">Descripción</label>
                <input type="text" name="descripcion" value="{{ $producto->descripcion }}" maxlength="50"
                    class="w-full rounded-lg border border-stone-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1.5">Categoría</label>
                    <select name="categoria_id_categoria" required
                        class="w-full rounded-lg border border-stone-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <option value="">Seleccione</option>
                        @foreach ($categorias as $cat)
                            <option value="{{ $cat->id_categoria }}" {{ $producto->categoria_id_categoria == $cat->id_categoria ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1.5">Marca</label>
                    <select name="marca_id_marca" required
                        class="w-full rounded-lg border border-stone-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <option value="">Seleccione</option>
                        @foreach ($marcas as $m)
                            <option value="{{ $m->id_marca }}" {{ $producto->marca_id_marca == $m->id_marca ? 'selected' : '' }}>{{ $m->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1.5">P. Compra (S/)</label>
                    <input type="number" name="precio_compra" value="{{ $producto->precio_compra }}" step="0.01" min="0"
                        class="w-full rounded-lg border border-stone-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1.5">P. Venta (S/)</label>
                    <input type="number" name="precio_venta" value="{{ $producto->precio_venta }}" step="0.01" min="0" required
                        class="w-full rounded-lg border border-stone-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1.5">Inventario</label>
                    <input type="number" name="inventario" value="{{ $producto->inventario }}" min="0" required
                        class="w-full rounded-lg border border-stone-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1.5">Stock</label>
                    <input type="number" name="stock" value="{{ $producto->stock }}" min="0" required
                        class="w-full rounded-lg border border-stone-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-stone-700 mb-1.5">Estado</label>
                <select name="estado" required
                    class="w-full rounded-lg border border-stone-300 px-4 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="1" {{ $producto->estado == 1 ? 'selected' : '' }}>Activo</option>
                    <option value="0" {{ $producto->estado == 0 ? 'selected' : '' }}>Inactivo</option>
                </select>
            </div>
        </div>

        <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-stone-700 mb-3">Imágenes del producto <span class="text-stone-400">(máx. 4)</span></label>
            <div id="gestor-imagenes" class="grid grid-cols-2 gap-3">
                @for ($i = 0; $i < 4; $i++)
                    @php
                        $imgExistente = $producto->imagenes->get($i);
                    @endphp
                    <div class="slot-imagen relative border-2 rounded-lg aspect-square flex flex-col items-center justify-center cursor-pointer transition {{ $imgExistente ? 'border-stone-300 bg-stone-50' : 'border-dashed border-stone-300 hover:border-emerald-400 text-stone-400 hover:text-emerald-500' }}" data-slot="{{ $i }}" data-existing="{{ $imgExistente ? '1' : '0' }}" data-img-id="{{ $imgExistente?->id_producto_imagen ?? '' }}">
                        @if ($imgExistente)
                            <img src="{{ $imgExistente->url }}" alt="Imagen {{ $i+1 }}" class="w-full h-full object-cover rounded-lg">
                            <input type="hidden" name="orden_imagenes[{{ $imgExistente->id_producto_imagen }}]" value="{{ $i }}" class="input-orden">
                        @else
                            <input type="file" name="imagenes[]" accept="image/jpeg,image/jpg,image/png,image/webp" class="hidden">
                            <input type="hidden" name="orden_imagenes[]" value="{{ $i }}" class="input-orden">
                            <svg class="w-8 h-8 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-xs">Agregar imagen</span>
                        @endif
                        <button type="button" class="btn-eliminar-imagen absolute top-1 right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs {{ $imgExistente ? '' : 'hidden' }} hover:bg-red-600">&times;</button>
                        <div class="indicador-arrastre absolute top-1 left-1 text-stone-300 {{ $imgExistente ? '' : 'hidden' }}">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 6a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm8 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4zM8 14a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm8 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4zM8 22a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm8 0a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/></svg>
                        </div>
                    </div>
                @endfor
            </div>
            <p class="text-xs text-stone-400 mt-3">Arrastre para reordenar. La primera imagen se usará como miniatura.</p>
        </div>
    </div>

    <div id="imagenes-eliminar-container"></div>

    <div class="flex justify-end gap-3 mt-8 pt-5 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Actualizar</button>
    </div>
</form>

@vite('resources/js/admin/productos/edit.js')
