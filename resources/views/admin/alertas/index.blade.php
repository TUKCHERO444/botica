<div class="flex items-center justify-between mb-6">
    <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Alertas de vencimientos</h1>
</div>

@php
    $secciones = [
        'Vencidos' => ['items' => $vencidos, 'color' => 'red', 'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'Críticos (≤7 días)' => ['items' => $criticos, 'color' => 'orange', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z'],
        'Próximos a vencer (≤30 días)' => ['items' => $proximos, 'color' => 'yellow', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z'],
        'Vigentes' => ['items' => $vigentes, 'color' => 'green', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
@endphp

<div class="space-y-6">
    @foreach ($secciones as $titulo => $seccion)
        @php $items = $seccion['items']; $color = $seccion['color']; @endphp
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 border-b border-stone-100
                @switch($color)
                    @case('red') bg-red-50 @break
                    @case('orange') bg-orange-50 @break
                    @case('yellow') bg-yellow-50 @break
                    @default bg-stone-50 @endswitch
            ">
                <svg class="w-5 h-5
                    @switch($color)
                        @case('red') text-red-600 @break
                        @case('orange') text-orange-600 @break
                        @case('yellow') text-yellow-600 @break
                        @default text-emerald-600 @endswitch
                " fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $seccion['icon'] }}"/>
                </svg>
                <h3 class="text-sm font-semibold
                    @switch($color)
                        @case('red') text-red-800 @break
                        @case('orange') text-orange-800 @break
                        @case('yellow') text-yellow-800 @break
                        @default text-emerald-800 @endswitch
                ">{{ $titulo }} ({{ $items->count() }})</h3>
            </div>
            @if ($items->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-stone-500 text-left">
                                <th class="px-4 py-2 font-medium">Producto</th>
                                <th class="px-4 py-2 font-medium">Lote</th>
                                <th class="px-4 py-2 font-medium text-center">Cantidad</th>
                                <th class="px-4 py-2 font-medium">Vencimiento</th>
                                <th class="px-4 py-2 font-medium text-center">Días restantes</th>
                                <th class="px-4 py-2 font-medium text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($items as $lote)
                                @php
                                    $diasRestantes = $hoy->diffInDays($lote->fecha_vencimiento, false);
                                @endphp
                                <tr class="hover:bg-stone-50 transition">
                                    <td class="px-4 py-2 text-stone-700">{{ $lote->producto->nombre }}</td>
                                    <td class="px-4 py-2 font-mono text-stone-600">{{ $lote->codigo_lote }}</td>
                                    <td class="px-4 py-2 text-stone-600 text-center font-medium">{{ $lote->cantidad }}</td>
                                    <td class="px-4 py-2 text-stone-600">{{ $lote->fecha_vencimiento->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if ($diasRestantes <= 0)
                                            <span class="text-red-600 font-medium">{{ $diasRestantes }}</span>
                                        @else
                                            <span class="text-stone-600">{{ $diasRestantes }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        @if ($diasRestantes <= 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Vencido</span>
                                        @elseif ($diasRestantes <= 7)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Crítico</span>
                                        @elseif ($diasRestantes <= 30)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Próximo</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Vigente</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-4 py-6 text-center text-stone-400 text-sm">
                    No hay lotes en esta categoría.
                </div>
            @endif
        </div>
    @endforeach
</div>
