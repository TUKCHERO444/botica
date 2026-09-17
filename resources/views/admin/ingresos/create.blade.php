<form action="{{ route('admin.ingresos.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Nuevo ingreso</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Correlativo</label>
            <input type="text" value="{{ $siguienteCorrelativo }}" readonly
                class="w-full rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm text-stone-500 outline-none font-mono">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Proveedor <span class="text-red-500">*</span></label>
            <select name="proveedor_id_proveedor" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Seleccione proveedor</option>
                @foreach ($proveedores as $prov)
                    <option value="{{ $prov->id_proveedor }}">{{ $prov->razon_social }} — {{ $prov->ruc }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Fecha</label>
            <input type="date" name="fecha" value="{{ now()->toDateString() }}"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
    </div>

    <div class="mb-4">
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium text-stone-700">Productos</label>
            <button type="button" id="agregar-producto"
                class="text-xs bg-emerald-100 text-emerald-700 hover:bg-emerald-200 px-3 py-1.5 rounded-lg transition font-medium">
                + Agregar producto
            </button>
        </div>

        <div id="productos-container" class="space-y-2">
            <div class="producto-row grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                <div class="sm:col-span-5">
                    <label class="block text-xs font-medium text-stone-600 mb-1">Producto <span class="text-red-500">*</span></label>
                    <select name="productos[0][producto_id_producto]" required
                        class="producto-select w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <option value="">Seleccione</option>
                        @foreach ($productos as $p)
                            <option value="{{ $p->id_producto }}" data-stock="{{ $p->stock }}" data-inventario="{{ $p->inventario }}">
                                {{ $p->nombre }} — S/ {{ number_format($p->precio_venta, 2) }} (Stock: {{ $p->stock }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-medium text-stone-600 mb-1">Cantidad <span class="text-red-500">*</span></label>
                    <input type="number" name="productos[0][cantidad]" min="1" value="1" required
                        class="producto-cantidad w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div class="sm:col-span-4 flex items-end pb-1 gap-2">
                    <span class="producto-info text-xs text-stone-400 pt-2">Stock: <span class="producto-stock-actual">0</span></span>
                    <button type="button" class="eliminar-producto text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-end gap-3 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Guardar</button>
    </div>
</form>

@vite('resources/js/admin/ingresos/create.js')
