<h1 class="text-xl lg:text-2xl font-bold text-stone-800 mb-6">Dashboard</h1>

{{-- Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-stone-500 font-medium uppercase tracking-wide">Ingresos hoy</p>
                <p class="text-xl lg:text-2xl font-bold text-stone-800 mt-0.5">S/ {{ number_format($totalIngresosHoy, 2) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-stone-500 font-medium uppercase tracking-wide">Ventas hoy</p>
                <p class="text-xl lg:text-2xl font-bold text-stone-800 mt-0.5">{{ $ventasHoy }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-stone-500 font-medium uppercase tracking-wide">Atenciones hoy</p>
                <p class="text-xl lg:text-2xl font-bold text-stone-800 mt-0.5">{{ $atencionesHoy }}</p>
            </div>
        </div>
    </div>

    <a href="{{ route('admin.productos.index', ['alerta' => 'inventario']) }}"
        class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6 hover:border-amber-300 hover:shadow transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-stone-500 font-medium uppercase tracking-wide">Stock bajo</p>
                <p class="text-xl lg:text-2xl font-bold text-stone-800 mt-0.5">{{ $stockBajo }}</p>
                <p class="text-[11px] font-medium {{ $stockAgotado > 0 ? 'text-red-600' : 'text-stone-400' }}">
                    {{ $stockAgotado }} agotado{{ $stockAgotado === 1 ? '' : 's' }}
                </p>
            </div>
        </div>
    </a>

    <a href="{{ route('admin.lotes.index', ['estado' => 'por_vencer']) }}"
        class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6 hover:border-orange-300 hover:shadow transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-orange-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-stone-500 font-medium uppercase tracking-wide">Lotes por vencer</p>
                <p class="text-xl lg:text-2xl font-bold text-stone-800 mt-0.5">{{ $lotesPorVencer }}</p>
                <p class="text-[11px] font-medium {{ $lotesVencidos > 0 ? 'text-red-600' : 'text-stone-400' }}">
                    {{ $lotesVencidos }} vencido{{ $lotesVencidos === 1 ? '' : 's' }} con stock
                </p>
            </div>
        </div>
    </a>
</div>

@if ($lotesVencidos > 0)
    <div class="mb-8 flex flex-col gap-2 rounded-xl border border-red-200 bg-red-50 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-red-700">
                Hay {{ $lotesVencidos }} lote{{ $lotesVencidos === 1 ? '' : 's' }} vencido{{ $lotesVencidos === 1 ? '' : 's' }} con stock.
            </p>
            <p class="text-xs text-red-600">No se puede vender y sigue sumando al stock físico. Revísalo y dalo de baja.</p>
        </div>
        <a href="{{ route('admin.lotes.index', ['estado' => 'vencido']) }}"
            class="shrink-0 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-red-700">
            Revisar lotes vencidos
        </a>
    </div>
@endif

{{-- Charts --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6 mb-8">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <h2 class="text-base font-semibold text-stone-800 mb-4">Ingresos diarios (últimos 7 días)</h2>
        <div class="relative h-72">
            <canvas id="chartSemanal"
                data-labels='@json($chartLabels)'
                data-ventas='@json($chartVentas)'
                data-atenciones='@json($chartAtenciones)'></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <h2 class="text-base font-semibold text-stone-800 mb-4">Distribución del mes</h2>
        <div class="relative h-72">
            <canvas id="chartDistribucion"
                data-ventas="{{ $totalVentasMes }}"
                data-atenciones="{{ $totalAtencionesMes }}"></canvas>
        </div>
    </div>
</div>

{{-- Top productos + Riesgo stock --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <h2 class="text-base font-semibold text-stone-800 mb-4">Top 5 productos más vendidos (este mes)</h2>
        @if ($topProductos->isNotEmpty())
            <div class="space-y-3">
                @foreach ($topProductos as $item)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-stone-700 truncate mr-4">{{ $item->nombre }}</span>
                        <span class="text-sm font-semibold text-stone-800 shrink-0">{{ $item->total_vendido }} vendidos</span>
                    </div>
                    <div class="w-full bg-stone-100 rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ $item->total_vendido / $topProductos->max('total_vendido') * 100 }}%"></div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-stone-500 text-sm">No hay ventas este mes.</p>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-stone-800">Productos con riesgo de stock</h2>
            <a href="{{ route('admin.productos.index', ['alerta' => 'inventario']) }}"
                class="shrink-0 text-xs font-medium text-emerald-700 hover:text-emerald-800">Ver todos</a>
        </div>
        @if ($productosRiesgo->isNotEmpty())
            <div class="space-y-3">
                @foreach ($productosRiesgo as $item)
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-stone-700 truncate mr-4">{{ $item->nombre }}</span>
                        <span class="text-xs font-semibold text-red-600 shrink-0">
                            @if ((float) $item->stock_vendible <= 0)
                                Agotado
                            @else
                                {{ $item->porcentaje_agotado }}% bajo mínimo
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-stone-100 rounded-full h-2">
                            <div class="bg-red-500 h-2 rounded-full" style="width: {{ min($item->porcentaje_agotado, 100) }}%"></div>
                        </div>
                        <span class="text-xs text-stone-500 shrink-0 w-20 text-right">{{ \App\Helpers\CantidadHelper::formatear($item->stock_vendible) }}/{{ \App\Helpers\CantidadHelper::formatear($item->stock_minimo) }}</span>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-stone-400 mt-3">* Sobre stock vendible (lotes disponibles no vencidos) igual o menor al mínimo.</p>
        @else
            <p class="text-stone-500 text-sm">No hay productos con riesgo de stock.</p>
        @endif
    </div>
</div>

{{-- Vencimientos próximos --}}
<div class="mb-8 bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-stone-800">Vencimientos próximos</h2>
            <p class="text-xs text-stone-400 mt-0.5">Lotes con stock que vencen en los próximos 30 días.</p>
        </div>
        <a href="{{ route('admin.lotes.index', ['estado' => 'por_vencer']) }}"
            class="shrink-0 text-xs font-medium text-emerald-700 hover:text-emerald-800">Ver todos</a>
    </div>

    @if ($lotesProximos->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-stone-200 text-left text-xs uppercase tracking-wide text-stone-500">
                        <th class="pb-2 pr-3 font-medium">Producto</th>
                        <th class="pb-2 pr-3 font-medium">Lote</th>
                        <th class="pb-2 pr-3 font-medium">Vence</th>
                        <th class="pb-2 pr-3 text-center font-medium">Días</th>
                        <th class="pb-2 text-right font-medium">Disponible</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($lotesProximos as $lote)
                        @php($dias = $lote->dias_para_vencer)
                        <tr class="{{ $dias <= 7 ? 'bg-red-50/60' : '' }}">
                            <td class="py-2 pr-3 font-medium text-stone-700">{{ $lote->producto?->nombre ?? '—' }}</td>
                            <td class="py-2 pr-3 font-mono text-xs text-stone-500">{{ $lote->codigo_lote }}</td>
                            <td class="py-2 pr-3 text-stone-600">{{ $lote->fecha_vencimiento?->format('d/m/Y') }}</td>
                            <td class="py-2 pr-3 text-center">
                                <span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $dias <= 7 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $dias }} d
                                </span>
                            </td>
                            <td class="py-2 text-right text-stone-700">
                                {{ \App\Helpers\CantidadHelper::formatear($lote->cantidad_disponible_base) }}
                                {{ $lote->producto?->unidadBase?->codigo }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-stone-500 text-sm">No hay lotes próximos a vencer en los próximos 30 días.</p>
    @endif
</div>

@vite('resources/js/admin/dashboard/index.js')
