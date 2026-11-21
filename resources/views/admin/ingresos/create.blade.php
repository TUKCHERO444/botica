<form data-modal-size="3xl" action="{{ route('admin.ingresos.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Nueva compra</h2>
            <p class="text-xs text-stone-500 mt-1">
                Se guarda como borrador. El stock y el pago se registran al recibirla.
            </p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-stone-700 mb-1">Proveedor <span class="text-red-500">*</span></label>
            <select name="proveedor_id_proveedor" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Seleccione proveedor</option>
                @foreach ($proveedores as $prov)
                    <option value="{{ $prov->id_proveedor }}" @selected(old('proveedor_id_proveedor') == $prov->id_proveedor)>
                        {{ $prov->razon_social }} — {{ $prov->ruc }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Fecha del documento</label>
            <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Tipo de documento <span class="text-red-500">*</span></label>
            <select name="tipo_documento" required
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Seleccione</option>
                @foreach (\App\Models\Ingreso::TIPOS_DOCUMENTO as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(old('tipo_documento') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Número de documento <span class="text-red-500">*</span></label>
            <input type="text" name="numero_documento" value="{{ old('numero_documento') }}" maxlength="30" required
                placeholder="F001-000123"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm uppercase focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
    </div>

    <div class="mb-4">
        <label class="block text-sm font-medium text-stone-700 mb-1">Observaciones</label>
        <textarea name="observaciones" rows="2" maxlength="500"
            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
            placeholder="Notas de la compra (opcional)">{{ old('observaciones') }}</textarea>
    </div>

    <div class="bg-stone-50 border border-stone-200 rounded-lg p-4 mb-5">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-stone-700">
                <input type="hidden" name="pagado_desde_caja" value="0">
                <input type="checkbox" name="pagado_desde_caja" value="1" @checked(old('pagado_desde_caja'))
                    class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                ¿Pagado desde caja?
            </label>
            <div class="flex-1">
                <select name="metodo_pago"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="">Método de pago</option>
                    @foreach (\App\Models\Ingreso::METODOS_PAGO as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('metodo_pago') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <input type="text" name="referencia_pago" value="{{ old('referencia_pago') }}" maxlength="100"
                    placeholder="Nro. de operación o nota (opcional)"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
        </div>
        @if ($cajaAbierta)
            <p class="text-xs text-emerald-700 mt-2">
                Caja abierta: #{{ $cajaAbierta->id_caja }} — disponible S/ {{ number_format($cajaAbierta->monto_actual, 2) }}.
            </p>
        @else
            <p class="text-xs text-amber-700 mt-2">
                No hay caja abierta. Puedes guardar la compra, pero al recibirla deberás pagar fuera de caja.
            </p>
        @endif
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
                <div class="sm:col-span-4">
                    <label class="block text-xs font-medium text-stone-600 mb-1">Producto / presentación <span class="text-red-500">*</span></label>
                    <select name="productos[0][producto_presentacion_id]" required
                        class="producto-select w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <option value="">Seleccione</option>
                        @foreach ($presentaciones as $pp)
                            <option value="{{ $pp->id_producto_presentacion }}"
                                data-stock="{{ $pp->producto->stock_formateado }}"
                                data-paso="{{ \App\Helpers\CantidadHelper::formatear($pp->paso) }}"
                                data-precio-venta="{{ number_format((float) $pp->precio_venta, 2, '.', '') }}">
                                {{ $pp->producto->nombre }} — {{ $pp->nombre_completo }} (Stock: {{ $pp->producto->stock_formateado }})
                            </option>
                        @endforeach
                    </select>
                    <span class="producto-info text-[10px] text-stone-400">Stock actual: <span class="producto-stock-actual">0</span></span>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-stone-600 mb-1">Cantidad <span class="text-red-500">*</span></label>
                    <input type="number" name="productos[0][cantidad]" min="0.001" step="1" value="1" required
                        class="producto-cantidad w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-stone-600 mb-1">Lote <span class="text-red-500">*</span></label>
                    <input type="text" name="productos[0][lote]" maxlength="50" required placeholder="LOT-2026"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm uppercase focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-stone-600 mb-1">Vencimiento <span class="text-red-500">*</span></label>
                    <input type="date" name="productos[0][fecha_vencimiento]" min="{{ now()->toDateString() }}" required
                        class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-stone-600 mb-1">Costo unit. (S/)</label>
                    <input type="number" name="productos[0][costo_unitario]" step="0.01" min="0"
                        class="producto-costo w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <p class="producto-alerta text-[11px] text-amber-600 mt-1 hidden">El costo supera el precio de venta de esta presentación.</p>
                </div>
                <div class="sm:col-span-12 flex justify-end">
                    <button type="button" class="eliminar-producto text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-stone-200">
        <div class="text-sm text-stone-600">
            Total: <span class="font-bold text-stone-800">S/ <span id="compra-total">0.00</span></span>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" data-close-modal
                class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Guardar borrador</button>
        </div>
    </div>
</form>

@vite('resources/js/admin/ingresos/create.js')
