<div data-modal-size="4xl" class="p-6 lg:p-8" data-inventario-modal>
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-stone-800">Nuevo inventario inicial</h2>
            <p class="text-sm text-stone-500 mt-1">
                Carga manual o importa un CSV. Se guarda como borrador; no mueve stock hasta confirmar.
            </p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1 shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="space-y-6">
        <section class="border border-stone-200 rounded-xl p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-base font-semibold text-stone-700">Carga manual</h3>
                    <p class="text-xs text-stone-500 mt-0.5">
                        Busca el producto y registra lote, vencimiento, cantidad base y costo.
                    </p>
                </div>
            </div>

            <form action="{{ route('admin.inventario-inicial.store') }}" method="POST" data-inventario-form>
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1.5">Fecha</label>
                        <input type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}"
                            class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-stone-600 mb-1.5">Observaciones</label>
                        <input type="text" name="observaciones" maxlength="500" value="{{ old('observaciones') }}"
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
                            @include('admin.inventario-inicial._linea-row')
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
                        Guardar borrador
                    </button>
                </div>

                <p class="text-xs text-stone-500 mt-4">
                    La cantidad y el costo se ingresan por la presentación seleccionada.
                    El costo se calcula con el costo base del producto y su factor; el precio de venta
                    se muestra desde la presentación para referencia. El sistema convierte todo a unidad base.
                    Si el producto ya tiene stock o lotes, bloqueará el guardado.
                </p>
            </form>
        </section>

        <section class="border border-stone-200 rounded-xl p-5" data-csv-form
            data-import-url="{{ route('admin.inventario-inicial.importar') }}">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-base font-semibold text-stone-700">Importar CSV</h3>
                    <p class="text-xs text-stone-500 mt-0.5">
                        Un archivo con una fila por lote. Puede crear productos nuevos y sus presentaciones base.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.inventario-inicial.plantilla') }}"
                        class="text-xs text-sky-700 hover:text-sky-900 underline">Descargar plantilla</a>
                    <a href="{{ route('admin.inventario-inicial.exportarProductos') }}"
                        class="text-xs text-sky-700 hover:text-sky-900 underline">Exportar productos actuales</a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 items-start">
                <input type="file" accept=".csv,text/csv" data-csv-archivo
                    class="block w-full text-sm text-stone-600 file:mr-3 file:px-4 file:py-2.5 file:rounded-lg file:border-0 file:bg-stone-100 file:text-stone-700 hover:file:bg-stone-200">

                <div class="flex flex-wrap gap-2">
                    <button type="button" data-csv-validar data-dry-run="1"
                        class="px-4 py-2.5 text-sm border border-stone-300 text-stone-700 rounded-lg hover:bg-stone-50 transition">
                        Validar solamente
                    </button>
                    <button type="button" data-csv-validar data-dry-run="0"
                        class="px-4 py-2.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                        Importar como borrador
                    </button>
                </div>
            </div>

            <div data-csv-resultado class="mt-4 text-sm"></div>

            <p class="text-xs text-stone-500 mt-4">
                Columnas obligatorias: nombre, unidad_base, lote, vencimiento, cantidad_base.
                Opcionales: categoria, marca, precio_venta, precio_compra, costo_unitario_base,
                presentacion, factor, precio_presentacion.
            </p>
        </section>
    </div>

    <div class="flex justify-end pt-4 mt-6 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
    </div>
</div>
