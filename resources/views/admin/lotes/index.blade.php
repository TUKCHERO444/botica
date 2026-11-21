<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Lotes y vencimientos</h1>
        <p class="text-sm text-stone-500 mt-1">Control FEFO, stock por lote y alertas de vencimiento.</p>
    </div>
    <form method="GET" action="{{ route('admin.lotes.index') }}" class="flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar correlativo o lote..."
            class="rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        <select name="estado" class="rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            <option value="">Todos</option>
            <option value="vencido" @selected(request('estado') === 'vencido')>Vencidos</option>
            <option value="por_vencer" @selected(request('estado') === 'por_vencer')>Próximos 30 días</option>
            <option value="critico" @selected(request('estado') === 'critico')>Críticos (7 días)</option>
            <option value="proximo" @selected(request('estado') === 'proximo')>Próximos (8 a 30 días)</option>
            <option value="disponible" @selected(request('estado') === 'disponible')>Disponibles</option>
            <option value="agotado" @selected(request('estado') === 'agotado')>Agotados</option>
            <option value="baja" @selected(request('estado') === 'baja')>Dados de baja</option>
        </select>
        <button type="submit" class="bg-stone-800 hover:bg-stone-900 text-white text-sm px-4 py-2 rounded-lg transition">Filtrar</button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">Producto</th>
                    <th class="px-4 py-3 font-medium">Correlativo</th>
                    <th class="px-4 py-3 font-medium">Lote</th>
                    <th class="px-4 py-3 font-medium">Vencimiento</th>
                    <th class="px-4 py-3 font-medium text-right">Disponible</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($lotes as $lote)
                    @php($estado = $lote->estado_visual)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 font-medium text-stone-800">
                            {{ $lote->producto?->nombre ?? '—' }}
                            <span class="text-xs text-stone-400">({{ $lote->producto?->unidadBase?->codigo }})</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-stone-700">{{ $lote->correlativo ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-stone-700">{{ $lote->codigo_lote }}</td>
                        <td class="px-4 py-3 text-stone-600">
                            {{ $lote->fecha_vencimiento?->format('d/m/Y') }}
                            <span class="text-xs text-stone-400">({{ $lote->dias_para_vencer }} días)</span>
                        </td>
                        <td class="px-4 py-3 text-right text-stone-700">
                            <div>{{ \App\Helpers\CantidadHelper::formatear($lote->cantidad_disponible_base) }}</div>
                            @if ((float) $lote->cantidad_disponible_base > 0
                                && $lote->producto?->presentaciones?->contains(fn ($pp) => (float) $pp->factor > 1))
                                <div class="text-[11px] text-stone-400">
                                    {{ \App\Helpers\PresentacionStockHelper::desglosar($lote->producto, (string) $lote->cantidad_disponible_base)['desglose'] }}
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php($colores = [
                                'vigente' => 'bg-emerald-100 text-emerald-700',
                                'proximo' => 'bg-amber-100 text-amber-700',
                                'critico' => 'bg-orange-100 text-orange-700',
                                'vencido' => 'bg-red-100 text-red-700',
                                'agotado' => 'bg-stone-100 text-stone-600',
                                'baja' => 'bg-stone-200 text-stone-700',
                                'bloqueado' => 'bg-purple-100 text-purple-700',
                            ])
                            <span class="inline-block text-xs font-medium px-2.5 py-0.5 rounded-full {{ $colores[$estado] ?? 'bg-stone-100 text-stone-600' }}">
                                {{ ucfirst($estado) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($lote->estado === 'disponible' && $lote->cantidad_disponible_base > 0)
                                <button type="button" data-delete="{{ route('admin.lotes.baja', $lote) }}"
                                    class="text-red-500 hover:text-red-700 text-xs font-medium px-2 py-1 rounded hover:bg-red-50 transition"
                                    title="Dar de baja">
                                    Dar de baja
                                </button>
                            @else
                                <span class="text-xs text-stone-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-stone-400">No hay lotes registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['items' => $lotes])
</div>
