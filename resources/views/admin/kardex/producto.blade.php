<div data-modal-size="3xl" class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-stone-800">Kardex — {{ $producto->nombre }}</h2>
            <p class="text-sm text-stone-500 mt-1">
                Stock actual:
                <span class="font-semibold text-stone-700">{{ $producto->stock_con_unidad }}</span>
            </p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium whitespace-nowrap">Fecha</th>
                    <th class="px-4 py-3 font-medium">Tipo</th>
                    <th class="px-4 py-3 font-medium">Presentación</th>
                    <th class="px-4 py-3 font-medium">Lote / vencimiento</th>
                    <th class="px-4 py-3 font-medium">Referencia</th>
                    <th class="px-4 py-3 font-medium text-right">Cantidad</th>
                    <th class="px-4 py-3 font-medium text-right">Stock Anterior</th>
                    <th class="px-4 py-3 font-medium text-right">Stock Nuevo</th>
                    <th class="px-4 py-3 font-medium">Usuario</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($movimientos as $m)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-500 whitespace-nowrap text-xs">
                            {{ $m->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $badge = match ($m->tipo_movimiento) {
                                    'INGRESO' => 'bg-emerald-100 text-emerald-700',
                                    'VENTA' => 'bg-blue-100 text-blue-700',
                                    'ATENCION' => 'bg-purple-100 text-purple-700',
                                    'AJUSTE' => 'bg-amber-100 text-amber-700',
                                    'BAJA' => 'bg-red-100 text-red-700',
                                    'DEVOLUCION' => 'bg-orange-100 text-orange-700',
                                    'REVERSION' => 'bg-stone-200 text-stone-700',
                                    default => 'bg-stone-100 text-stone-700',
                                };
                            @endphp
                            <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full {{ $badge }}">
                                {{ $m->tipo_movimiento }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-stone-500">
                            {{ $m->unidad_presentacion ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-stone-500 text-xs">
                            @if ($m->lote_codigo)
                                <div class="font-mono">{{ $m->lote_codigo }}</div>
                                <div>{{ $m->fecha_vencimiento?->format('d/m/Y') }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-stone-600">
                            {{ $m->referencia_codigo }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums {{ $m->cantidad > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $m->cantidad > 0 ? '+' : '' }}{{ \App\Helpers\CantidadHelper::formatear($m->cantidad) }}
                        </td>
                        <td class="px-4 py-3 text-right text-stone-500 tabular-nums">
                            {{ \App\Helpers\CantidadHelper::formatear($m->stock_anterior) }}
                        </td>
                        <td class="px-4 py-3 text-right text-stone-500 tabular-nums">
                            {{ \App\Helpers\CantidadHelper::formatear($m->stock_nuevo) }}
                        </td>
                        <td class="px-4 py-3 text-stone-500">
                            {{ $m->usuario->name ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-stone-400">
                            No hay movimientos para este producto.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['items' => $movimientos, 'modal' => true])
</div>
