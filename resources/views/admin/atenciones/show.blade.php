<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-stone-800">Detalle de atención #{{ $atencione->id_atencion }}</h2>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="bg-stone-50 rounded-lg p-4 mb-6 space-y-2">
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Cliente</span>
            <span class="text-sm font-medium text-stone-800">
                {{ $atencione->cliente->nombre }} {{ $atencione->cliente->apellido_paterno }} {{ $atencione->cliente->apellido_materno }}
                <span class="text-stone-400">— {{ $atencione->cliente->dni }}</span>
            </span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Observación</span>
            <span class="text-sm text-stone-700">{{ $atencione->observacio ?? '—' }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-sm text-stone-500">Registrado por</span>
            <span class="text-sm text-stone-700">{{ $atencione->user?->name ?? '—' }}</span>
        </div>
    </div>

    @if ($atencione->productos->count() > 0)
    <div class="mb-4">
        <h3 class="text-sm font-semibold text-stone-700 mb-2">Productos</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-100 text-stone-600 text-left">
                    <th class="px-3 py-2 font-medium">Producto</th>
                    <th class="px-3 py-2 font-medium text-center">Cant.</th>
                    <th class="px-3 py-2 font-medium text-right">Precio Unit.</th>
                    <th class="px-3 py-2 font-medium text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @foreach ($atencione->productos as $p)
                <tr class="hover:bg-stone-50">
                    <td class="px-3 py-2 text-stone-700">{{ $p->nombre }}</td>
                    <td class="px-3 py-2 text-stone-600 text-center">{{ $p->pivot->cantidad }}</td>
                    <td class="px-3 py-2 text-stone-600 text-right">S/ {{ number_format($p->precio_venta, 2) }}</td>
                    <td class="px-3 py-2 text-stone-800 font-medium text-right">S/ {{ number_format($p->pivot->sub_total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if ($atencione->servicios->count() > 0)
    <div class="mb-4">
        <h3 class="text-sm font-semibold text-stone-700 mb-2">Servicios</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-100 text-stone-600 text-left">
                    <th class="px-3 py-2 font-medium">Servicio</th>
                    <th class="px-3 py-2 font-medium text-right">Precio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @foreach ($atencione->servicios as $s)
                <tr class="hover:bg-stone-50">
                    <td class="px-3 py-2 text-stone-700">{{ $s->nombre }}</td>
                    <td class="px-3 py-2 text-stone-600 text-right">S/ {{ number_format($s->pivot->subtotal, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="flex items-center justify-end gap-3 p-3 bg-emerald-50 rounded-lg">
        <span class="text-sm font-medium text-stone-600">Total:</span>
        <span class="text-xl font-bold text-emerald-700">S/ {{ number_format($atencione->total, 2) }}</span>
    </div>

    <div class="flex justify-end pt-4 border-t border-stone-200">
        <button type="button" data-close-modal
            class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cerrar</button>
    </div>
</div>
