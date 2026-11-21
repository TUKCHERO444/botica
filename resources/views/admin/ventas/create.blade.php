<form data-modal-size="3xl" action="{{ route('admin.ventas.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Nueva venta</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Correlativo</label>
            <input type="text" value="{{ $siguienteCorrelativo }}" readonly
                class="w-full rounded-lg border border-stone-200 bg-stone-50 px-4 py-2 text-sm text-stone-500 outline-none font-mono">
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Cliente <span class="text-red-500">*</span>
                <button type="button" data-generico class="ml-2 align-middle text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 hover:border-emerald-300 rounded-full px-2.5 py-0.5 transition-colors">
                    <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    Cliente sin identificar
                </button>
            </label>
            <div class="client-search" data-field="cliente_id_cliente" data-placeholder="Buscar cliente por nombre o DNI..."></div>
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
            @include('admin.ventas._producto-row', ['i' => 0])
        </div>

        <template id="producto-row-template">
            @include('admin.ventas._producto-row', ['i' => 0])
        </template>
    </div>

    <div class="flex items-center justify-end gap-3 mb-4 p-3 bg-emerald-50 rounded-lg">
        <span class="text-sm font-medium text-stone-600">Total:</span>
        <span id="total-venta" class="text-xl font-bold text-emerald-700">S/ 0.00</span>
    </div>

    @include('admin.partials.pagos-form', ['metodosPago' => $metodosPago, 'totalSource' => '#total-venta'])

    <div class="flex justify-end gap-3 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
        <button type="submit"
            class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Guardar</button>
    </div>
</form>

@vite('resources/js/admin/ventas/create.js')
