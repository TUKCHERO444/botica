<div class="flex items-center justify-between gap-4 mb-6">
    <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Caja #{{ $caja->id_caja }} — Detalle</h1>
    <a href="{{ route('admin.caja.historial') }}" data-panel="{{ route('admin.caja.historial') }}"
        class="text-sm text-stone-500 hover:text-stone-700 px-3 py-2 rounded-lg border border-stone-300 hover:border-stone-400 transition">
        ← Volver al historial
    </a>
</div>

{{-- Resumen inmutable --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Monto inicial</p>
        <p class="text-lg lg:text-xl font-bold text-stone-800">S/ {{ number_format($caja->monto_inicial, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Total ingresos</p>
        <p class="text-lg lg:text-xl font-bold text-emerald-600">S/ {{ number_format($caja->total_ingresos, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Total egresos</p>
        <p class="text-lg lg:text-xl font-bold text-red-600">S/ {{ number_format($caja->total_egresos, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Monto final</p>
        <p class="text-lg lg:text-xl font-bold text-stone-800">S/ {{ number_format($caja->monto_final, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Diferencia</p>
        @php $diferencia = $caja->monto_final - ($caja->monto_inicial + $caja->total_ingresos - $caja->total_egresos); @endphp
        <p class="text-lg lg:text-xl font-bold {{ $diferencia == 0 ? 'text-emerald-600' : 'text-red-600' }}">
            S/ {{ number_format($diferencia, 2) }}
        </p>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-stone-50 rounded-lg border border-stone-200 p-4">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Apertura</p>
        <p class="text-sm font-medium text-stone-700">{{ $caja->apertura_at->format('d/m/Y H:i:s') }}</p>
        <p class="text-sm text-stone-500">por {{ $caja->aperturaUser?->name ?? '—' }}</p>
    </div>
    <div class="bg-stone-50 rounded-lg border border-stone-200 p-4">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Cierre</p>
        <p class="text-sm font-medium text-stone-700">{{ $caja->cierre_at?->format('d/m/Y H:i:s') ?? '—' }}</p>
        <p class="text-sm text-stone-500">por {{ $caja->cierreUser?->name ?? '—' }}</p>
    </div>
</div>

{{-- Movimientos --}}
@if ($caja->movimientos->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
        <div class="px-4 lg:px-6 py-3 border-b border-stone-200 bg-stone-50">
            <h2 class="text-sm font-semibold text-stone-700">Movimientos registrados</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-stone-50 text-stone-600 text-left">
                        <th class="px-4 py-3 font-medium">#</th>
                        <th class="px-4 py-3 font-medium">Tipo</th>
                        <th class="px-4 py-3 font-medium">Monto</th>
                        <th class="px-4 py-3 font-medium">Descripción / Referencia</th>
                        <th class="hidden sm:table-cell px-4 py-3 font-medium">Registrado por</th>
                        <th class="hidden sm:table-cell px-4 py-3 font-medium">Fecha/Hora</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @foreach ($caja->movimientos as $m)
                        <tr class="hover:bg-stone-50 transition">
                            <td class="px-4 py-3 text-stone-500">{{ $m->id_movimiento }}</td>
                            <td class="px-4 py-3">
                                @if ($m->tipo === 'ingreso')
                                    <span class="inline-flex items-center gap-1 text-emerald-600 font-medium">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                        Ingreso
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-red-600 font-medium">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                        Egreso
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-stone-800">S/ {{ number_format($m->monto, 2) }}</td>
                            <td class="px-4 py-3 text-stone-600">
                                @if ($m->descripcion)
                                    {{ $m->descripcion }}
                                @elseif ($m->referencia_tipo === 'venta')
                                    Venta #{{ $m->referencia_id }}
                                @elseif ($m->referencia_tipo === 'atencion')
                                    Atención #{{ $m->referencia_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $m->user?->name ?? '—' }}</td>
                            <td class="hidden sm:table-cell px-4 py-3 text-stone-400">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-6 text-center">
        <p class="text-stone-400 text-sm">No se registraron movimientos en esta caja.</p>
    </div>
@endif

<div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
        </svg>
        <p class="text-sm text-amber-800">Esta caja está cerrada. Los datos mostrados son inmutables y no pueden modificarse.</p>
    </div>
</div>
