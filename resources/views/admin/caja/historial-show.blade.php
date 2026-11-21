<div class="flex items-center justify-between gap-4 mb-6">
    <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Caja #{{ $caja->id_caja }} — Detalle</h1>
    <a href="{{ route('admin.caja.historial') }}" data-panel="{{ route('admin.caja.historial') }}"
        class="text-sm text-stone-500 hover:text-stone-700 px-3 py-2 rounded-lg border border-stone-300 hover:border-stone-400 transition">
        ← Volver al historial
    </a>
</div>

{{-- Resumen inmutable --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 lg:gap-6 mb-4">
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Fondo inicial</p>
        <p class="text-lg lg:text-xl font-bold text-stone-800">S/ {{ number_format($caja->monto_inicial, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Ingresos efectivo</p>
        <p class="text-lg lg:text-xl font-bold text-emerald-600">S/ {{ number_format($caja->total_ingresos_efectivo, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Egresos efectivo</p>
        <p class="text-lg lg:text-xl font-bold text-red-600">S/ {{ number_format($caja->total_egresos_efectivo, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-emerald-200 bg-emerald-50 p-4 lg:p-6">
        <p class="text-xs text-emerald-700 font-medium uppercase tracking-wide mb-1">Efectivo esperado</p>
        <p class="text-lg lg:text-xl font-bold text-emerald-800">
            {{ $caja->efectivo_esperado === null ? '—' : 'S/ ' . number_format((float) $caja->efectivo_esperado, 2) }}
        </p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Efectivo contado</p>
        <p class="text-lg lg:text-xl font-bold text-stone-800">
            {{ $caja->efectivo_contado === null ? '—' : 'S/ ' . number_format((float) $caja->efectivo_contado, 2) }}
        </p>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 px-4 py-3">
        <p class="text-xs text-stone-500 uppercase tracking-wide">Diferencia del arqueo</p>
        @if ($caja->efectivo_contado === null)
            <p class="text-lg font-semibold text-stone-400">Sin arqueo registrado</p>
        @else
            <p class="text-lg font-bold {{ (float) $caja->diferencia === 0.0 ? 'text-emerald-600' : 'text-red-600' }}">
                S/ {{ number_format($caja->diferencia_real, 2) }}
            </p>
        @endif
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 px-4 py-3">
        <p class="text-xs text-stone-500 uppercase tracking-wide">Ingresos digitales</p>
        <p class="text-lg font-semibold text-sky-700">S/ {{ number_format($caja->total_ingresos_digital, 2) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 px-4 py-3">
        <p class="text-xs text-stone-500 uppercase tracking-wide">Egresos digitales</p>
        <p class="text-lg font-semibold text-sky-700">S/ {{ number_format($caja->total_egresos_digital, 2) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-stone-50 rounded-lg border border-stone-200 p-4">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Apertura</p>
        <p class="text-sm font-medium text-stone-700">{{ $caja->apertura_at->format('d/m/Y H:i:s') }}</p>
        <p class="text-sm text-stone-500">por {{ $caja->aperturaUser?->name ?? '—' }}</p>
        @if ($caja->observacion_apertura)
            <p class="text-xs text-amber-700 mt-1">{{ $caja->observacion_apertura }}</p>
        @endif
    </div>
    <div class="bg-stone-50 rounded-lg border border-stone-200 p-4">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Cierre</p>
        <p class="text-sm font-medium text-stone-700">{{ $caja->cierre_at?->format('d/m/Y H:i:s') ?? '—' }}</p>
        <p class="text-sm text-stone-500">por {{ $caja->cierreUser?->name ?? '—' }}</p>
    </div>
    <div class="bg-stone-50 rounded-lg border border-stone-200 p-4">
        <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Arqueo</p>
        <p class="text-sm font-medium text-stone-700">{{ $caja->arqueo_at?->format('d/m/Y H:i:s') ?? '—' }}</p>
        <p class="text-sm text-stone-500">por {{ $caja->arqueoUser?->name ?? '—' }}</p>
    </div>
</div>

@if ($caja->motivo_diferencia || $caja->observacion_cierre)
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
        <p class="text-xs font-semibold text-amber-800 uppercase tracking-wide mb-1">Justificación del arqueo</p>
        @if ($caja->motivo_diferencia)
            <p class="text-sm text-amber-800"><span class="font-medium">Motivo:</span> {{ $caja->motivo_diferencia_label }}</p>
        @endif
        @if ($caja->observacion_cierre)
            <p class="text-sm text-amber-800 mt-1">{{ $caja->observacion_cierre }}</p>
        @endif
    </div>
@endif

{{-- Movimientos --}}
@if ($movimientos->isNotEmpty())
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
                        <th class="px-4 py-3 font-medium">Método</th>
                        <th class="px-4 py-3 font-medium">Monto</th>
                        <th class="px-4 py-3 font-medium">Categoría / descripción</th>
                        <th class="hidden sm:table-cell px-4 py-3 font-medium">Registrado por</th>
                        <th class="hidden sm:table-cell px-4 py-3 font-medium">Fecha/Hora</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @foreach ($movimientos as $m)
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
                            <td class="px-4 py-3 text-stone-700">
                                {{ $m->metodo_label }}
                                @unless ($m->afecta_efectivo)
                                    <span class="ml-1 inline-block bg-sky-100 text-sky-700 text-[10px] font-medium px-1.5 py-0.5 rounded">digital</span>
                                @endunless
                            </td>
                            <td class="px-4 py-3 font-medium text-stone-800">S/ {{ number_format($m->monto, 2) }}</td>
                            <td class="px-4 py-3 text-stone-600">
                                <span class="block text-xs text-stone-400">{{ $m->categoria_label }}</span>
                                {{ $m->descripcion_visible }}
                            </td>
                            <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $m->user?->name ?? '—' }}</td>
                            <td class="hidden sm:table-cell px-4 py-3 text-stone-400">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @include('admin.partials.pagination', ['items' => $movimientos])
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
