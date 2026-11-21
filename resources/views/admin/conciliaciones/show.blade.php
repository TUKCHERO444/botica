<div data-modal-size="3xl" class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">
                Conciliación #{{ $conciliacion->id_conciliacion_inventario }}
            </h2>
            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full mt-1 {{ $conciliacion->estado_clase }}">
                {{ $conciliacion->estado_label }}
            </span>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="bg-stone-50 rounded-lg p-4 mb-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
            <div class="text-xs text-stone-500">Fecha</div>
            <div class="text-sm font-medium text-stone-800">{{ $conciliacion->fecha?->format('d/m/Y H:i') ?? '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">Usuario</div>
            <div class="text-sm text-stone-700">{{ $conciliacion->user?->name ?? 'Sistema' }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">Origen</div>
            <div class="text-sm text-stone-700">{{ $conciliacion->origen_label }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">Diferencia total</div>
            <div class="text-sm font-medium text-stone-800">{{ \App\Helpers\CantidadHelper::formatear($conciliacion->diferencia_total) }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">Productos revisados</div>
            <div class="text-sm font-medium text-stone-800">{{ $conciliacion->total_productos }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">Con diferencia</div>
            <div class="text-sm font-medium {{ $conciliacion->productos_con_diferencia > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                {{ $conciliacion->productos_con_diferencia }}
            </div>
        </div>
        <div>
            <div class="text-xs text-stone-500">Lotes inconsistentes</div>
            <div class="text-sm font-medium text-stone-800">{{ $conciliacion->lotes_inconsistentes }}</div>
        </div>
    </div>

    @if ($conciliacion->detalles->isEmpty())
        <p class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg p-3">
            No se encontraron diferencias entre el stock y los lotes.
        </p>
    @else
        <div class="mb-5">
            <h3 class="text-sm font-semibold text-stone-700 mb-2">Diferencias por producto</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-stone-100 text-stone-600 text-left">
                            <th class="px-3 py-2 font-medium">Producto</th>
                            <th class="px-3 py-2 font-medium text-right">Stock caché</th>
                            <th class="px-3 py-2 font-medium text-right">Stock lotes</th>
                            <th class="px-3 py-2 font-medium text-right">Diferencia</th>
                            <th class="px-3 py-2 font-medium text-right">Lotes inconsistentes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @foreach ($conciliacion->detalles as $detalle)
                            <tr>
                                <td class="px-3 py-2 text-stone-700">{{ $detalle->producto?->nombre ?? '—' }}</td>
                                <td class="px-3 py-2 text-stone-600 text-right">{{ \App\Helpers\CantidadHelper::formatear($detalle->stock_cache) }}</td>
                                <td class="px-3 py-2 text-stone-600 text-right">{{ \App\Helpers\CantidadHelper::formatear($detalle->stock_lotes) }}</td>
                                <td class="px-3 py-2 text-right font-medium {{ (float) $detalle->diferencia === 0.0 ? 'text-stone-600' : 'text-red-600' }}">
                                    {{ \App\Helpers\CantidadHelper::formatear($detalle->diferencia) }}
                                </td>
                                <td class="px-3 py-2 text-stone-600 text-right">{{ $detalle->lotes_inconsistentes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @php
            $lotes = $conciliacion->detalles->flatMap(fn ($detalle) => $detalle->detallesLote);
        @endphp

        @if ($lotes->isNotEmpty())
            <div>
                <h3 class="text-sm font-semibold text-stone-700 mb-2">Diferencias por lote</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-stone-100 text-stone-600 text-left">
                                <th class="px-3 py-2 font-medium">Producto</th>
                                <th class="px-3 py-2 font-medium">Lote</th>
                                <th class="px-3 py-2 font-medium text-right">Cantidad lote</th>
                                <th class="px-3 py-2 font-medium text-right">Suma capas</th>
                                <th class="px-3 py-2 font-medium text-right">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200">
                            @foreach ($lotes as $lote)
                                <tr>
                                    <td class="px-3 py-2 text-stone-700">{{ $lote->detalle?->producto?->nombre ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-stone-600">{{ $lote->lote?->codigo_lote ?? '—' }}</td>
                                    <td class="px-3 py-2 text-stone-600 text-right">{{ \App\Helpers\CantidadHelper::formatear($lote->cantidad_lote) }}</td>
                                    <td class="px-3 py-2 text-stone-600 text-right">{{ \App\Helpers\CantidadHelper::formatear($lote->suma_capas) }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-red-600">{{ \App\Helpers\CantidadHelper::formatear($lote->diferencia) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

    <div class="flex justify-end pt-4 mt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
    </div>
</div>
