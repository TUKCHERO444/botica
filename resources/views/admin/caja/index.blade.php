<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Caja</h1>
        <p class="text-sm text-stone-500 mt-1">Control de efectivo, movimientos y arqueo del turno.</p>
    </div>
    <div class="flex items-center gap-2 self-start sm:self-auto">
        <a href="{{ route('admin.caja.historial') }}" data-panel="{{ route('admin.caja.historial') }}"
            class="text-sm text-stone-500 hover:text-stone-700 px-3 py-2 rounded-lg border border-stone-300 hover:border-stone-400 transition">
            Historial
        </a>
    </div>
</div>

@if ($cajaAbierta)
    {{-- Caja activa --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-4">
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
            <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Fondo inicial</p>
            <p class="text-xl lg:text-2xl font-bold text-stone-800">S/ {{ number_format($cajaAbierta->monto_inicial, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
            <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Ingresos en efectivo</p>
            <p class="text-xl lg:text-2xl font-bold text-emerald-600">S/ {{ number_format($cajaAbierta->total_ingresos_efectivo, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6">
            <p class="text-xs text-stone-500 font-medium uppercase tracking-wide mb-1">Egresos en efectivo</p>
            <p class="text-xl lg:text-2xl font-bold text-red-600">S/ {{ number_format($cajaAbierta->total_egresos_efectivo, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-emerald-300 bg-emerald-50 p-4 lg:p-6">
            <p class="text-xs text-emerald-700 font-medium uppercase tracking-wide mb-1">Efectivo esperado</p>
            <p class="text-xl lg:text-2xl font-bold text-emerald-800">S/ {{ number_format((float) $efectivoEsperado, 2) }}</p>
            <p class="text-xs text-emerald-700 mt-1">Lo que debería haber en el cajón.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 px-4 py-3">
            <p class="text-xs text-stone-500 uppercase tracking-wide">Ingresos digitales</p>
            <p class="text-lg font-semibold text-sky-700">S/ {{ number_format($cajaAbierta->total_ingresos_digital, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 px-4 py-3">
            <p class="text-xs text-stone-500 uppercase tracking-wide">Egresos digitales</p>
            <p class="text-lg font-semibold text-sky-700">S/ {{ number_format($cajaAbierta->total_egresos_digital, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 px-4 py-3">
            <p class="text-xs text-stone-500 uppercase tracking-wide">Total operaciones</p>
            <p class="text-lg font-semibold text-stone-700">S/ {{ number_format($cajaAbierta->monto_actual, 2) }}</p>
            <p class="text-xs text-stone-400">Incluye efectivo y digital.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Botones de acción --}}
        <div class="flex flex-wrap gap-3">
            <button type="button" data-modal="{{ route('admin.caja.formIngreso') }}"
                class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-5 py-2.5 rounded-lg transition font-medium">
                Registrar ingreso
            </button>
            <button type="button" data-modal="{{ route('admin.caja.formEgreso') }}"
                class="bg-red-600 hover:bg-red-700 text-white text-sm px-5 py-2.5 rounded-lg transition font-medium">
                Registrar egreso
            </button>
            <button type="button" data-modal="{{ route('admin.caja.formCierre') }}"
                class="bg-stone-700 hover:bg-stone-800 text-white text-sm px-5 py-2.5 rounded-lg transition font-medium">
                Cerrar caja y arquear
            </button>
        </div>

        {{-- Información de apertura --}}
        <div class="text-sm text-stone-500">
            <p>Apertura: <span class="font-medium text-stone-700">{{ $cajaAbierta->apertura_at->format('d/m/Y H:i') }}</span></p>
            <p>Abierta por: <span class="font-medium text-stone-700">{{ $cajaAbierta->aperturaUser?->name ?? '—' }}</span></p>
            @if ($cajaAbierta->observacion_apertura)
                <p class="mt-1 text-amber-700">Observación: {{ $cajaAbierta->observacion_apertura }}</p>
            @endif
        </div>
    </div>

    {{-- Resumen por método --}}
    @if ($resumenMetodos->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden mb-6">
            <div class="px-4 lg:px-6 py-3 border-b border-stone-200">
                <h2 class="text-sm font-semibold text-stone-700">Resumen por método de pago</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-stone-50 text-stone-600 text-left">
                            <th class="px-4 py-3 font-medium">Método</th>
                            <th class="px-4 py-3 font-medium">Afecta efectivo</th>
                            <th class="px-4 py-3 font-medium text-right">Ingresos</th>
                            <th class="px-4 py-3 font-medium text-right">Egresos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @foreach ($resumenMetodos as $r)
                            <tr class="hover:bg-stone-50 transition">
                                <td class="px-4 py-2.5 font-medium text-stone-700">{{ $r->metodo }}</td>
                                <td class="px-4 py-2.5">
                                    @if ($r->afecta_efectivo)
                                        <span class="text-xs font-medium text-emerald-700">Sí</span>
                                    @else
                                        <span class="text-xs font-medium text-stone-500">No (digital)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right text-emerald-700">S/ {{ number_format((float) $r->ingresos, 2) }}</td>
                                <td class="px-4 py-2.5 text-right text-red-600">S/ {{ number_format((float) $r->egresos, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Movimientos --}}
    @if ($movimientos && $movimientos->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
            <div class="px-4 lg:px-6 py-3 border-b border-stone-200">
                <h2 class="text-sm font-semibold text-stone-700">Movimientos de esta caja</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-stone-50 text-stone-600 text-left">
                            <th class="px-4 py-3 font-medium">Tipo</th>
                            <th class="px-4 py-3 font-medium">Método</th>
                            <th class="px-4 py-3 font-medium">Monto</th>
                            <th class="hidden sm:table-cell px-4 py-3 font-medium">Categoría / descripción</th>
                            <th class="hidden md:table-cell px-4 py-3 font-medium">Registrado por</th>
                            <th class="hidden md:table-cell px-4 py-3 font-medium">Hora</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @foreach ($movimientos as $m)
                            <tr class="hover:bg-stone-50 transition">
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
                                <td class="px-4 py-3">
                                    <span class="text-stone-700">{{ $m->metodo_label }}</span>
                                    @unless ($m->afecta_efectivo)
                                        <span class="ml-1 inline-block bg-sky-100 text-sky-700 text-[10px] font-medium px-1.5 py-0.5 rounded">digital</span>
                                    @endunless
                                </td>
                                <td class="px-4 py-3 font-medium text-stone-800">S/ {{ number_format($m->monto, 2) }}</td>
                                <td class="hidden sm:table-cell px-4 py-3 text-stone-500 max-w-xs truncate">
                                    <span class="block text-xs text-stone-400">{{ $m->categoria_label }}</span>
                                    {{ $m->descripcion_visible }}
                                </td>
                                <td class="hidden md:table-cell px-4 py-3 text-stone-500">{{ $m->user?->name ?? '—' }}</td>
                                <td class="hidden md:table-cell px-4 py-3 text-stone-400">{{ $m->created_at->format('H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @include('admin.partials.pagination', ['items' => $movimientos])
        </div>
    @endif
@else
    {{-- Sin caja abierta --}}
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 p-8 lg:p-12 text-center">
        <svg class="w-16 h-16 text-stone-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <h2 class="text-lg font-semibold text-stone-600 mb-2">No hay caja abierta</h2>
        <p class="text-sm text-stone-400 mb-6">Para comenzar a registrar movimientos, abra una nueva caja.</p>
        <button type="button" data-modal="{{ route('admin.caja.formApertura') }}"
            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-6 py-2.5 rounded-lg transition font-medium inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Abrir caja
        </button>
    </div>

    {{-- Últimas cajas cerradas --}}
    @if ($cajasCerradas->isNotEmpty())
        <div class="mt-8">
            <h2 class="text-base font-semibold text-stone-700 mb-3">Últimas cajas cerradas</h2>
            <div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-stone-50 text-stone-600 text-left">
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">Apertura</th>
                                <th class="px-4 py-3 font-medium">Cierre</th>
                                <th class="px-4 py-3 font-medium">Inicial</th>
                                <th class="px-4 py-3 font-medium">Esperado</th>
                                <th class="px-4 py-3 font-medium">Contado</th>
                                <th class="px-4 py-3 font-medium">Diferencia</th>
                                <th class="hidden sm:table-cell px-4 py-3 font-medium">Abrió</th>
                                <th class="px-4 py-3 font-medium text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200">
                            @foreach ($cajasCerradas as $c)
                                <tr class="hover:bg-stone-50 transition">
                                    <td class="px-4 py-3 text-stone-500">{{ $c->id_caja }}</td>
                                    <td class="px-4 py-3 text-stone-700">{{ $c->apertura_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3 text-stone-700">{{ $c->cierre_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-stone-600">S/ {{ number_format($c->monto_inicial, 2) }}</td>
                                    <td class="px-4 py-3 text-stone-600">
                                        {{ $c->efectivo_esperado === null ? '—' : 'S/ ' . number_format((float) $c->efectivo_esperado, 2) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-stone-800">
                                        {{ $c->efectivo_contado === null ? '—' : 'S/ ' . number_format((float) $c->efectivo_contado, 2) }}
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        @if ($c->efectivo_contado === null)
                                            <span class="text-stone-400 text-xs">Sin arqueo</span>
                                        @else
                                            <span class="{{ (float) $c->diferencia === 0.0 ? 'text-emerald-600' : 'text-red-600' }}">
                                                S/ {{ number_format($c->diferencia_real, 2) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $c->aperturaUser?->name ?? '—' }}</td>
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

                @include('admin.partials.pagination', ['items' => $cajasCerradas])
            </div>
        </div>
    @endif
@endif
