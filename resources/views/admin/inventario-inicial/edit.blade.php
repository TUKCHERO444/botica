<div data-modal-size="4xl" class="p-6 lg:p-8" data-inventario-modal>
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-stone-800">
                Editar inventario {{ $inventario->correlativo }}
            </h2>
            <p class="text-sm text-stone-500 mt-1">
                Solo se puede editar mientras esté en borrador. No mueve stock hasta confirmar.
            </p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1 shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <section class="border border-stone-200 rounded-xl p-5">
        <form action="{{ route('admin.inventario-inicial.update', $inventario) }}" method="POST" data-inventario-form>
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <label class="block text-xs font-medium text-stone-600 mb-1.5">Fecha</label>
                    <input type="date" name="fecha" value="{{ old('fecha', $inventario->fecha?->format('Y-m-d')) }}"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-600 mb-1.5">Observaciones</label>
                    <input type="text" name="observaciones" maxlength="500"
                        value="{{ old('observaciones', $inventario->observaciones) }}"
                        class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                        placeholder="Conteo inicial, migración, etc.">
                </div>
            </div>

            <div class="border border-stone-200 rounded-lg">
                <table class="w-full table-fixed text-sm" data-lineas-table>
                    <thead>
                        <tr class="bg-stone-50 text-stone-600 text-left">
                            <th class="w-[34%] px-2 py-3 font-medium">Producto</th>
                            <th class="w-[10%] px-2 py-3 font-medium">Lote</th>
                            <th class="w-[14%] px-2 py-3 font-medium">Vencimiento</th>
                            <th class="w-[10%] px-2 py-3 font-medium text-right">Cant. present.</th>
                            <th class="w-[10%] px-2 py-3 font-medium text-right">Costo present.</th>
                            <th class="w-[11%] px-2 py-3 font-medium text-right">Precio venta</th>
                            <th class="w-[11%] px-2 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody data-lineas class="divide-y divide-stone-100">
                        @forelse ($inventario->detalles as $i => $detalle)
                            @include('admin.inventario-inicial._linea-row', [
                                'index' => $i,
                                'detalle' => $detalle,
                            ])
                        @empty
                            @include('admin.inventario-inicial._linea-row')
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 mt-4">
                <button type="button" data-agregar-linea
                    class="text-sm text-emerald-700 hover:text-emerald-900 font-medium">
                    + Agregar línea
                </button>
                <button type="submit"
                    class="px-5 py-2.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                    Actualizar borrador
                </button>
            </div>

            <p class="text-xs text-stone-500 mt-4">
                La cantidad y el costo se ingresan por la presentación seleccionada.
                El costo se calcula con el costo base del producto y su factor; el precio de venta
                se muestra desde la presentación para referencia. El sistema convierte todo a unidad base.
            </p>
        </form>
    </section>

    <div class="flex justify-end pt-4 mt-6 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
    </div>
</div>
