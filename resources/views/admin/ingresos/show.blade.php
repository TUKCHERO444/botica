<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Detalle de ingreso #{{ $ingreso->id_ingreso }}</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="bg-stone-50 rounded-lg p-4 mb-6 space-y-2">
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Correlativo</span>
            <span class="text-sm font-mono font-medium text-stone-800">{{ $ingreso->correlativo }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Proveedor</span>
            <span class="text-sm font-medium text-stone-800">{{ $ingreso->proveedor->razon_social }} <span class="text-stone-400">— {{ $ingreso->proveedor->ruc }}</span></span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Fecha</span>
            <span class="text-sm text-stone-700">{{ $ingreso->fecha ? \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') : '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Registrado por</span>
            <span class="text-sm text-stone-700">{{ $ingreso->user?->name ?? '—' }}</span>
        </div>
    </div>

    <div class="mb-4">
        <h3 class="text-sm font-semibold text-stone-700 mb-2">Productos ingresados</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-100 text-stone-600 text-left">
                    <th class="px-3 py-2 font-medium">Producto</th>
                    <th class="px-3 py-2 font-medium">Lote</th>
                    <th class="px-3 py-2 font-medium">Vencimiento</th>
                    <th class="px-3 py-2 font-medium text-center">Cantidad</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($ingreso->productos as $p)
                    <tr class="hover:bg-stone-50">
                        <td class="px-3 py-2 text-stone-700">{{ $p->nombre }}</td>
                        <td class="px-3 py-2 font-mono text-stone-600">{{ $p->pivot->lote ?? '—' }}</td>
                        <td class="px-3 py-2 text-stone-600">{{ $p->pivot->fecha_vencimiento ? \Carbon\Carbon::parse($p->pivot->fecha_vencimiento)->format('d/m/Y') : '—' }}</td>
                        <td class="px-3 py-2 text-stone-600 text-center font-medium">{{ $p->pivot->cantidad }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-4 text-center text-stone-400">No hay productos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-end pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
    </div>
</div>
