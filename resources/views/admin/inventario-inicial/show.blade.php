<div data-modal-size="4xl" class="p-6 lg:p-8" data-inventario-modal>
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-stone-800">
                Inventario inicial {{ $inventario->correlativo }}
            </h2>
            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full mt-1 {{ $inventario->estado_clase }}">
                {{ $inventario->estado_label }}
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
            <div class="text-sm font-medium text-stone-800">{{ $inventario->fecha?->format('d/m/Y') ?? '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">Usuario</div>
            <div class="text-sm text-stone-700">{{ $inventario->user?->name ?? 'Sistema' }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">Líneas</div>
            <div class="text-sm font-medium text-stone-800">{{ $inventario->detalles->count() }}</div>
        </div>
        <div>
            <div class="text-xs text-stone-500">Costo total</div>
            <div class="text-sm font-medium text-stone-800">S/ {{ number_format((float) $inventario->total_costo, 2) }}</div>
        </div>
        @if ($inventario->estaConfirmado())
            <div>
                <div class="text-xs text-stone-500">Confirmado</div>
                <div class="text-sm text-stone-700">{{ $inventario->confirmado_at?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
        @endif
        @if ($inventario->archivo_nombre)
            <div class="col-span-2">
                <div class="text-xs text-stone-500">Archivo</div>
                <div class="text-sm text-stone-700 font-mono">{{ $inventario->archivo_nombre }}</div>
            </div>
        @endif
        @if ($inventario->observaciones)
            <div class="col-span-2 sm:col-span-4">
                <div class="text-xs text-stone-500">Observaciones</div>
                <div class="text-sm text-stone-700">{{ $inventario->observaciones }}</div>
            </div>
        @endif
    </div>

    <div class="overflow-x-auto border border-stone-200 rounded-lg mb-5">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-3 py-2 font-medium">Producto</th>
                    <th class="px-3 py-2 font-medium">Presentación</th>
                    <th class="px-3 py-2 font-medium">Lote</th>
                    <th class="px-3 py-2 font-medium">Vencimiento</th>
                    <th class="px-3 py-2 font-medium text-right">Cant. present.</th>
                    <th class="px-3 py-2 font-medium text-right">Cant. base</th>
                    <th class="px-3 py-2 font-medium text-right">Costo present.</th>
                    <th class="px-3 py-2 font-medium text-right">Costo total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($inventario->detalles as $detalle)
                    @php
                        $factorDetalle = (float) $detalle->factor > 0
                            ? (float) $detalle->factor
                            : (float) ($detalle->presentacion?->factor ?? 1);
                        $cantidadPresentacion = (float) $detalle->cantidad_presentacion > 0
                            ? (float) $detalle->cantidad_presentacion
                            : ((float) $detalle->cantidad_base / max($factorDetalle, 1));
                        $costoPresentacion = (float) $detalle->costo_unitario_presentacion > 0
                            ? (float) $detalle->costo_unitario_presentacion
                            : ((float) $detalle->costo_unitario_base * $factorDetalle);
                    @endphp
                    <tr>
                        <td class="px-3 py-2 text-stone-700">{{ $detalle->producto?->nombre ?? '—' }}</td>
                        <td class="px-3 py-2 text-stone-600">{{ $detalle->presentacion?->nombre_completo ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono text-stone-600">{{ $detalle->codigo_lote }}</td>
                        <td class="px-3 py-2 text-stone-600">{{ $detalle->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-2 text-stone-600 text-right">{{ \App\Helpers\CantidadHelper::formatear($cantidadPresentacion) }}</td>
                        <td class="px-3 py-2 text-stone-600 text-right">{{ \App\Helpers\CantidadHelper::formatear($detalle->cantidad_base) }}</td>
                        <td class="px-3 py-2 text-stone-600 text-right">S/ {{ number_format($costoPresentacion, 4) }}</td>
                        <td class="px-3 py-2 text-stone-800 font-medium text-right">S/ {{ number_format((float) $detalle->costo_total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-stone-400">Sin líneas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (! $inventario->estaConfirmado())
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 text-xs text-amber-800">
            Al confirmar se crean lotes, capas, stock y kardex <span class="font-mono">INGRESO</span>.
            Esta acción no se puede editar después; las correcciones se hacen con ajustes.
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-end gap-3 pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
        @if (! $inventario->estaConfirmado())
            <button type="button" data-modal="{{ route('admin.inventario-inicial.edit', $inventario) }}"
                class="px-4 py-2 text-sm text-indigo-600 hover:text-indigo-800 border border-indigo-200 hover:border-indigo-300 rounded-lg transition">
                Editar borrador
            </button>
            <form action="{{ route('admin.inventario-inicial.confirmar', $inventario) }}" method="POST">
                @csrf
                <button type="submit"
                    class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                    Confirmar inventario
                </button>
            </form>
        @endif
    </div>
</div>
