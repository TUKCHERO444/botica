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
                <option value="INGRESO" {{ request('tipo_movimiento') == 'INGRESO' ? 'selected' : '' }}>INGRESO</option>
                <option value="VENTA" {{ request('tipo_movimiento') == 'VENTA' ? 'selected' : '' }}>VENTA</option>
                <option value="ATENCION" {{ request('tipo_movimiento') == 'ATENCION' ? 'selected' : '' }}>ATENCION</option>
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
                        <td class="hidden sm:table-cell px-4 py-3 text-right text-stone-500 tabular-nums">
                            {{ $m->stock_anterior }}
                        </td>
                        <td class="hidden sm:table-cell px-4 py-3 text-right text-stone-500 tabular-nums">
                            {{ $m->stock_nuevo }}
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">
                            {{ $m->usuario->name ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-stone-400">
                            No hay movimientos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($movimientos->hasPages())
        <div class="px-4 py-3 border-t border-stone-200">
            {{ $movimientos->links() }}
        </div>
    @endif
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
