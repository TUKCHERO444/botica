<div class="flex items-center justify-between gap-4 mb-6">
    <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Historial de cajas</h1>
    <a href="{{ route('admin.caja.index') }}" data-panel="{{ route('admin.caja.index') }}"
        class="text-sm text-stone-500 hover:text-stone-700 px-3 py-2 rounded-lg border border-stone-300 hover:border-stone-400 transition">
        Volver a caja
    </a>
</div>

@if ($cajas->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-stone-50 text-stone-600 text-left">
                        <th class="px-4 py-3 font-medium">#</th>
                        <th class="px-4 py-3 font-medium">Apertura</th>
                        <th class="px-4 py-3 font-medium">Cierre</th>
                        <th class="px-4 py-3 font-medium">Inicial</th>
                        <th class="px-4 py-3 font-medium">Ingresos</th>
                        <th class="px-4 py-3 font-medium">Egresos</th>
                        <th class="px-4 py-3 font-medium">Final</th>
                        <th class="hidden sm:table-cell px-4 py-3 font-medium">Abrió</th>
                        <th class="hidden sm:table-cell px-4 py-3 font-medium">Cerró</th>
                        <th class="px-4 py-3 font-medium text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @foreach ($cajas as $c)
                        <tr class="hover:bg-stone-50 transition">
                            <td class="px-4 py-3 text-stone-500">{{ $c->id_caja }}</td>
                            <td class="px-4 py-3 text-stone-700 whitespace-nowrap">{{ $c->apertura_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-stone-700 whitespace-nowrap">{{ $c->cierre_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-stone-600">S/ {{ number_format($c->monto_inicial, 2) }}</td>
                            <td class="px-4 py-3 text-emerald-600 font-medium">S/ {{ number_format($c->total_ingresos, 2) }}</td>
                            <td class="px-4 py-3 text-red-600 font-medium">S/ {{ number_format($c->total_egresos, 2) }}</td>
                            <td class="px-4 py-3 font-bold text-stone-800">S/ {{ number_format($c->monto_final, 2) }}</td>
                            <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $c->aperturaUser?->name ?? '—' }}</td>
                            <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $c->cierreUser?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.caja.historialShow', $c->id_caja) }}"
                                    data-panel="{{ route('admin.caja.historialShow', $c->id_caja) }}"
                                    class="text-sky-600 hover:text-sky-800 text-xs font-medium">
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-8 text-center">
        <p class="text-stone-400">No hay cajas cerradas aún.</p>
    </div>
@endif
