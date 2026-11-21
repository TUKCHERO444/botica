<div data-modal-size="2xl" class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Ajuste {{ $ajuste->correlativo }}</h2>
            <p class="text-xs text-stone-500 mt-1">{{ $ajuste->tipo_label }} — registro inmutable</p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="bg-stone-50 rounded-lg p-4 mb-5 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Tipo</span>
            <span class="text-sm font-medium text-stone-800">{{ $ajuste->tipo_label }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Fecha</span>
            <span class="text-sm text-stone-700">{{ $ajuste->fecha?->format('d/m/Y') ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Registrado por</span>
            <span class="text-sm text-stone-700">{{ $ajuste->user?->name ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Valor</span>
            <span class="text-sm font-bold text-stone-800">S/ {{ number_format((float) $ajuste->total, 2) }}</span>
        </div>
        <div class="sm:col-span-2 flex justify-between gap-4">
            <span class="text-sm text-stone-500">Motivo</span>
            <span class="text-sm text-stone-700 text-right">{{ $ajuste->motivo }}</span>
        </div>
        @if ($ajuste->observaciones)
            <div class="sm:col-span-2 flex justify-between gap-4">
                <span class="text-sm text-stone-500">Observaciones</span>
                <span class="text-sm text-stone-700 text-right">{{ $ajuste->observaciones }}</span>
            </div>
        @endif
    </div>

    <div class="mb-4">
        <h3 class="text-sm font-semibold text-stone-700 mb-2">Lotes ajustados</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-stone-100 text-stone-600 text-left">
                        <th class="px-3 py-2 font-medium">Producto</th>
                        <th class="px-3 py-2 font-medium">Lote</th>
                        <th class="px-3 py-2 font-medium">Vence</th>
                        <th class="px-3 py-2 font-medium text-center">Cantidad base</th>
                        <th class="px-3 py-2 font-medium text-right">Costo unit. base</th>
                        <th class="px-3 py-2 font-medium text-right">Costo total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @forelse ($ajuste->detalles as $detalle)
                        <tr>
                            <td class="px-3 py-2 text-stone-700">{{ $detalle->producto?->nombre ?? '—' }}</td>
                            <td class="px-3 py-2 font-mono text-stone-600">{{ $detalle->lote?->codigo_lote ?? '—' }}</td>
                            <td class="px-3 py-2 text-stone-600">{{ $detalle->lote?->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-center font-medium {{ (float) $detalle->cantidad_base >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                <div>{{ (float) $detalle->cantidad_base > 0 ? '+' : '' }}{{ \App\Helpers\CantidadHelper::formatear($detalle->cantidad_base) }}</div>
                                @if ((float) $detalle->cantidad_base !== 0.0
                                    && $detalle->producto?->presentaciones?->contains(fn ($pp) => (float) $pp->factor > 1))
                                    <div class="text-[11px] font-normal text-stone-400">
                                        {{ \App\Helpers\PresentacionStockHelper::desglosar($detalle->producto, (string) abs((float) $detalle->cantidad_base))['desglose'] }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-stone-600 text-right">S/ {{ number_format((float) $detalle->costo_unitario_base, 4) }}</td>
                            <td class="px-3 py-2 text-stone-600 text-right font-medium">S/ {{ number_format((float) $detalle->costo_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-stone-400">No hay líneas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-end pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
    </div>
</div>
