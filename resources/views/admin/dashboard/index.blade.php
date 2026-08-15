<h1 class="text-xl lg:text-2xl font-bold text-stone-800 mb-6">Dashboard</h1>

{{-- Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
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

    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-stone-500 font-medium uppercase tracking-wide">Stock bajo</p>
                <p class="text-xl lg:text-2xl font-bold text-stone-800 mt-0.5">{{ $stockBajo }}</p>
            </div>
        </div>
    </div>
</div>

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
        <h2 class="text-base font-semibold text-stone-800 mb-4">Productos con riesgo de stock</h2>
        @if ($productosRiesgo->isNotEmpty())
            <div class="space-y-3">
                @foreach ($productosRiesgo as $item)
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-stone-700 truncate mr-4">{{ $item->nombre }}</span>
                        <span class="text-xs font-semibold text-red-600 shrink-0">{{ $item->porcentaje_agotado }}% agotado</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-stone-100 rounded-full h-2">
                            <div class="bg-red-500 h-2 rounded-full" style="width: {{ min($item->porcentaje_agotado, 100) }}%"></div>
                        </div>
                        <span class="text-xs text-stone-500 shrink-0 w-16 text-right">{{ $item->stock }}/{{ $item->inventario }}</span>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-stone-400 mt-3">* Productos con stock ≤ 20% del inventario inicial</p>
        @else
            <p class="text-stone-500 text-sm">No hay productos con riesgo de stock.</p>
        @endif
    </div>
</div>

@vite('resources/js/admin/dashboard/index.js')
