<form data-modal-size="md" action="{{ route('admin.banners.store') }}" method="POST" class="p-6" enctype="multipart/form-data">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-stone-800">Nuevo banner</h2>
        <button type="button" onclick="AdminModal.close()" class="text-stone-400 hover:text-stone-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Imagen del banner <span class="text-red-500">*</span></label>
            <input type="file" name="imagen" id="imagen" accept="image/jpeg,image/jpg,image/png,image/webp" required
                class="w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
            <p class="text-xs text-stone-400 mt-1">JPG, PNG o WebP. Máximo 2 MB.</p>
            <div id="bloque-preview" class="hidden mt-3">
                <img id="preview-imagen" src="#" alt="Preview"
                    class="w-full h-40 object-cover rounded-lg border border-stone-200">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Título</label>
            <input type="text" name="titulo" maxlength="255"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                placeholder="Oferta especial">
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Subtítulo</label>
            <input type="text" name="subtitulo" maxlength="255"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                placeholder="Hasta 50% de descuento">
        </div>

        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Enlace (opcional)</label>
            <input type="url" name="enlace" maxlength="255"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                placeholder="https://ejemplo.com/oferta">
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

@vite('resources/js/admin/banners/index.js')
