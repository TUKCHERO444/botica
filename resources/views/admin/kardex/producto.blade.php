<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-stone-800">Kardex — {{ $producto->nombre }}</h2>
            <p class="text-sm text-stone-500 mt-1">
                Stock actual:
                <span class="font-semibold text-stone-700">{{ $producto->stock }}</span>
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
                            @if ($m->tipo_movimiento === 'INGRESO')
                                <span class="inline-block bg-emerald-100 text-emerald-700 text-xs font-medium px-2.5 py-0.5 rounded-full">INGRESO</span>
                            @elseif ($m->tipo_movimiento === 'VENTA')
                                <span class="inline-block bg-blue-100 text-blue-700 text-xs font-medium px-2.5 py-0.5 rounded-full">VENTA</span>
                            @else
                                <span class="inline-block bg-amber-100 text-amber-700 text-xs font-medium px-2.5 py-0.5 rounded-full">ATENCION</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-stone-600">
                            {{ $m->referencia_codigo }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums {{ $m->cantidad > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $m->cantidad > 0 ? '+' . $m->cantidad : $m->cantidad }}
                        </td>
                        <td class="px-4 py-3 text-right text-stone-500 tabular-nums">
                            {{ $m->stock_anterior }}
                        </td>
                        <td class="px-4 py-3 text-right text-stone-500 tabular-nums">
                            {{ $m->stock_nuevo }}
                        </td>
                        <td class="px-4 py-3 text-stone-500">
                            {{ $m->usuario->name ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-stone-400">
                            No hay movimientos para este producto.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($movimientos->hasPages())
        <div class="mt-4">
            {{ $movimientos->links() }}
        </div>
    @endif
</div>
