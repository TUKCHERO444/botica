<form action="{{ route('admin.ventas.update', $venta->id_venta) }}" method="POST" class="p-6">
    @csrf
    @method('PUT')
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Editar venta</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Correlativo</label>
            <input type="text" value="{{ $venta->correlativo }}" readonly
                class="w-full rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm text-stone-500 outline-none font-mono">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Cliente <span class="text-red-500">*</span>
                <button type="button" data-generico class="ml-2 align-middle text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 hover:border-emerald-300 rounded-full px-2.5 py-0.5 transition-colors">
                    <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Cliente sin identificar
                </button>
            </label>
            <div class="client-search"
                data-field="cliente_id_cliente"
                data-initial-id="{{ $selectedCliente->id_cliente }}"
                data-initial-text="{{ $selectedCliente->nombre }} {{ $selectedCliente->apellido_paterno }} — {{ $selectedCliente->dni }}"
                data-placeholder="Buscar cliente por nombre o DNI..."></div>
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

        <div id="productos-container" data-start-index="{{ max(count($venta->productos), 1) }}" class="space-y-2">
            @forelse ($venta->productos as $i => $p)
                <div class="producto-row grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                    <div class="sm:col-span-5">
                        <label class="block text-xs font-medium text-stone-600 mb-1">Producto</label>
                        <select name="productos[{{ $i }}][producto_id_producto]" required
                            class="producto-select w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                            <option value="">Seleccione</option>
                            @foreach ($productos as $prod)
                                <option value="{{ $prod->id_producto }}" data-precio="{{ $prod->precio_venta }}" data-stock="{{ $prod->stock }}"
                                    {{ $p->id_producto == $prod->id_producto ? 'selected' : '' }}>
                                    {{ $prod->nombre }} — S/ {{ number_format($prod->precio_venta, 2) }} (Stock: {{ $prod->stock }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-stone-600 mb-1">Cantidad</label>
                        <input type="number" name="productos[{{ $i }}][cantidad]" value="{{ $p->pivot->cantidad }}" min="1" required
                            class="producto-cantidad w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-medium text-stone-600 mb-1">Precio Unitario (S/)</label>
                        <input type="number" name="productos[{{ $i }}][precio]" value="{{ $p->precio_venta }}" step="0.01" min="0" required readonly
                            class="producto-precio w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-500 outline-none">
                    </div>
                    <div class="sm:col-span-2 flex items-end pb-1 gap-2">
                        <span class="producto-subtotal text-sm font-medium text-stone-700 pt-2 hidden sm:block">S/ {{ number_format($p->pivot->cantidad * $p->precio_venta, 2) }}</span>
                        <button type="button" class="eliminar-producto text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="producto-row grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                    <div class="sm:col-span-5">
                        <label class="block text-xs font-medium text-stone-600 mb-1">Producto</label>
                        <select name="productos[0][producto_id_producto]" required
                            class="producto-select w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                            <option value="">Seleccione</option>
                            @foreach ($productos as $prod)
                                <option value="{{ $prod->id_producto }}" data-precio="{{ $prod->precio_venta }}" data-stock="{{ $prod->stock }}">
                                    {{ $prod->nombre }} — S/ {{ number_format($prod->precio_venta, 2) }} (Stock: {{ $prod->stock }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-stone-600 mb-1">Cantidad</label>
                        <input type="number" name="productos[0][cantidad]" min="1" value="1" required
                            class="producto-cantidad w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-medium text-stone-600 mb-1">Precio Unitario (S/)</label>
                        <input type="number" name="productos[0][precio]" step="0.01" min="0" required readonly
                            class="producto-precio w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-500 outline-none">
                    </div>
                    <div class="sm:col-span-2 flex items-end pb-1 gap-2">
                        <span class="producto-subtotal text-sm font-medium text-stone-700 pt-2 hidden sm:block">S/ 0.00</span>
                        <button type="button" class="eliminar-producto text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 mb-4 p-3 bg-emerald-50 rounded-lg">
        <span class="text-sm font-medium text-stone-600">Total:</span>
        <span id="total-venta" class="text-xl font-bold text-emerald-700">S/ {{ number_format($venta->total, 2) }}</span>
    </div>

    <div class="flex justify-end gap-3 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Actualizar</button>
    </div>
</form>

@vite('resources/js/admin/ventas/edit.js')
