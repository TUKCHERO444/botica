<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Kardex de Inventario</h1>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 mb-6">
    <form id="kardex-filtros" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div>
            <label class="block text-xs font-medium text-stone-600 mb-1">Producto</label>
            <select name="producto_id" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Todos</option>
                @foreach ($productos as $p)
                    <option value="{{ $p->id_producto }}" {{ request('producto_id') == $p->id_producto ? 'selected' : '' }}>
                        {{ $p->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-600 mb-1">Tipo</label>
            <select name="tipo_movimiento" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Todos</option>
                @foreach (['INGRESO', 'VENTA', 'ATENCION', 'AJUSTE', 'BAJA', 'DEVOLUCION', 'REVERSION'] as $tipo)
                    <option value="{{ $tipo }}" {{ request('tipo_movimiento') == $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-600 mb-1">Usuario</label>
            <select name="usuario_id" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Todos</option>
                @foreach ($usuarios as $u)
                    <option value="{{ $u->id }}" {{ request('usuario_id') == $u->id ? 'selected' : '' }}>
                        {{ $u->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-600 mb-1">Desde</label>
            <input type="date" name="fecha_desde" value="{{ request('fecha_desde') }}"
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-stone-600 mb-1">Hasta</label>
            <input type="date" name="fecha_hasta" value="{{ request('fecha_hasta') }}"
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium whitespace-nowrap">Fecha</th>
                    <th class="px-4 py-3 font-medium">Producto</th>
                    <th class="px-4 py-3 font-medium">Presentación</th>
                    <th class="px-4 py-3 font-medium">Lote / vencimiento</th>
                    <th class="px-4 py-3 font-medium">Tipo</th>
                    <th class="px-4 py-3 font-medium">Referencia</th>
                    <th class="px-4 py-3 font-medium text-right">Cantidad</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium text-right">Stock Anterior</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium text-right">Stock Nuevo</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Usuario</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($movimientos as $m)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-500 whitespace-nowrap text-xs">
                            {{ $m->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 font-medium text-stone-800">
                            {{ $m->producto->nombre ?? '—' }}
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
                        <td class="px-4 py-3 font-mono text-xs text-stone-600">
                            {{ $m->referencia_codigo }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums {{ $m->cantidad > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            <div>{{ $m->cantidad > 0 ? '+' : '' }}{{ \App\Helpers\CantidadHelper::formatear($m->cantidad) }}</div>
                            @if ($m->unidad_presentacion && $m->factor && (float) $m->factor > 1)
                                <div class="text-[11px] font-normal text-stone-400">
                                    {{ $m->cantidad < 0 ? '−' : '+' }}{{ \App\Helpers\CantidadHelper::formatear(abs((float) ($m->cantidad_presentacion ?? $m->cantidad))) }}
                                    × {{ $m->unidad_presentacion }}
                                </div>
                            @endif
                        </td>
                        <td class="hidden sm:table-cell px-4 py-3 text-right text-stone-500 tabular-nums">
                            {{ \App\Helpers\CantidadHelper::formatear($m->stock_anterior) }}
                        </td>
                        <td class="hidden sm:table-cell px-4 py-3 text-right text-stone-500 tabular-nums">
                            <div>{{ \App\Helpers\CantidadHelper::formatear($m->stock_nuevo) }}</div>
                            @if ((float) $m->stock_nuevo > 0
                                && $m->producto?->presentaciones?->contains(fn ($pp) => (float) $pp->factor > 1))
                                <div class="text-[11px] font-normal text-stone-400">
                                    {{ \App\Helpers\PresentacionStockHelper::desglosar($m->producto, (string) $m->stock_nuevo)['desglose'] }}
                                </div>
                            @endif
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">
                            {{ $m->usuario->name ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-stone-400">
                            No hay movimientos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['items' => $movimientos])
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('kardex-filtros');
    if (!form) return;

    const selects = form.querySelectorAll('select, input');
    let timeout;

    const aplicarFiltros = () => {
        const params = new URLSearchParams();
        selects.forEach(el => {
            if (el.value) params.set(el.name, el.value);
        });
        const qs = params.toString();
        const baseUrl = window.location.pathname;
        const nuevaUrl = qs ? baseUrl + '?' + qs : baseUrl;

        window.dispatchEvent(new CustomEvent('panel:navigate', {
            detail: { url: nuevaUrl }
        }));
    };

    selects.forEach(el => {
        el.addEventListener('change', aplicarFiltros);
        el.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(aplicarFiltros, 500);
        });
    });
});
</script>
@endpush
