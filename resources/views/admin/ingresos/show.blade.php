<div data-modal-size="2xl" class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">
                Compra {{ $ingreso->correlativo ?? '— borrador #'.$ingreso->id_ingreso }}
            </h2>
            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full mt-1 {{ $ingreso->estado_clase }}">
                {{ $ingreso->estado_label }}
            </span>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="bg-stone-50 rounded-lg p-4 mb-5 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Proveedor</span>
            <span class="text-sm font-medium text-stone-800 text-right">{{ $ingreso->proveedor->razon_social }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">RUC</span>
            <span class="text-sm text-stone-700 font-mono">{{ $ingreso->proveedor->ruc }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Documento</span>
            <span class="text-sm text-stone-700">
                {{ $ingreso->tipo_documento_label ?? '—' }}
                @if ($ingreso->numero_documento)
                    <span class="font-mono">{{ $ingreso->numero_documento }}</span>
                @endif
            </span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Fecha del documento</span>
            <span class="text-sm text-stone-700">{{ $ingreso->fecha?->format('d/m/Y') ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Registrado por</span>
            <span class="text-sm text-stone-700">{{ $ingreso->user?->name ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Recepción</span>
            <span class="text-sm text-stone-700">
                @if ($ingreso->fecha_recepcion)
                    {{ $ingreso->fecha_recepcion->format('d/m/Y') }} — {{ $ingreso->recepcionUser?->name ?? '—' }}
                @else
                    Pendiente
                @endif
            </span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Pago</span>
            <span class="text-sm text-stone-700">
                {{ $ingreso->pagado_desde_caja ? 'Desde caja' : 'Fuera de caja' }}
                @if ($ingreso->metodo_pago_label)
                    — {{ $ingreso->metodo_pago_label }}
                @endif
            </span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Referencia de pago</span>
            <span class="text-sm text-stone-700">{{ $ingreso->referencia_pago ?: '—' }}</span>
        </div>
        @if ($ingreso->observaciones)
            <div class="sm:col-span-2 flex justify-between gap-4">
                <span class="text-sm text-stone-500">Observaciones</span>
                <span class="text-sm text-stone-700 text-right">{{ $ingreso->observaciones }}</span>
            </div>
        @endif
        @if ($ingreso->estaAnulada())
            <div class="sm:col-span-2 flex justify-between gap-4 border-t border-stone-200 pt-2">
                <span class="text-sm text-red-600">Anulada</span>
                <span class="text-sm text-red-700 text-right">
                    {{ $ingreso->anulada_at?->format('d/m/Y H:i') }} — {{ $ingreso->motivo_anulacion }}
                </span>
            </div>
        @endif
    </div>

    <div class="mb-4">
        <h3 class="text-sm font-semibold text-stone-700 mb-2">Productos</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-stone-100 text-stone-600 text-left">
                        <th class="px-3 py-2 font-medium">Producto</th>
                        <th class="px-3 py-2 font-medium">Presentación</th>
                        <th class="px-3 py-2 font-medium">Lote</th>
                        <th class="px-3 py-2 font-medium">Vence</th>
                        <th class="px-3 py-2 font-medium text-center">Cantidad</th>
                        <th class="px-3 py-2 font-medium text-center">Unidad base</th>
                        <th class="px-3 py-2 font-medium text-right">Costo unit.</th>
                        <th class="px-3 py-2 font-medium text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @forelse ($ingreso->detalles as $detalle)
                        <tr class="hover:bg-stone-50">
                            <td class="px-3 py-2 text-stone-700">{{ $detalle->producto?->nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-stone-600">{{ $detalle->presentacion?->nombre_completo ?? '—' }}</td>
                            <td class="px-3 py-2 font-mono text-stone-600">{{ $detalle->lote_codigo ?? '—' }}</td>
                            <td class="px-3 py-2 text-stone-600">{{ $detalle->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-3 py-2 text-stone-600 text-center font-medium">{{ \App\Helpers\CantidadHelper::formatear($detalle->cantidad) }}</td>
                            <td class="px-3 py-2 text-stone-600 text-center">{{ \App\Helpers\CantidadHelper::formatear($detalle->cantidad_base) }}</td>
                            <td class="px-3 py-2 text-stone-600 text-right">S/ {{ number_format((float) $detalle->costo_unitario, 2) }}</td>
                            <td class="px-3 py-2 text-stone-600 text-right font-medium">S/ {{ number_format((float) $detalle->costo_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-stone-400">No hay productos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex justify-end border-t border-stone-200 pt-3">
        <div class="text-right">
            <div class="text-xs text-stone-500">Subtotal</div>
            <div class="text-sm text-stone-700">S/ {{ number_format((float) $ingreso->subtotal, 2) }}</div>
            <div class="text-sm font-bold text-stone-800 mt-1">Total: S/ {{ number_format((float) $ingreso->total, 2) }}</div>
        </div>
    </div>

    <div class="flex justify-end pt-4">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
    </div>
</div>
