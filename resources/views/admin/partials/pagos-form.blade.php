@php
    $pagosPorMetodo = collect($pagosActuales ?? [])->keyBy('metodo_pago_id');
    $totalSource = $totalSource ?? '#total-venta';
    $modoInicialMixto = $pagosPorMetodo->count() > 1;
@endphp

<div class="mb-4 rounded-xl border border-stone-200 bg-stone-50 p-4"
    data-pagos
    data-modo="{{ $modoInicialMixto ? 'mixto' : 'unico' }}"
    data-total-source="{{ $totalSource }}">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-stone-700">Pago <span class="text-red-500">*</span></h3>
                <span class="inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
                    data-pagos-modo-badge>{{ $modoInicialMixto ? 'Pago mixto' : 'Pago único' }}</span>
            </div>
            <p class="text-xs text-stone-500" data-pagos-ayuda>
                {{ $modoInicialMixto
                    ? 'Distribuya el total entre dos o más métodos. Los botones quedan bloqueados mientras el pago mixto está activo.'
                    : 'Seleccione un método para cobrar el total.' }}
            </p>
        </div>
        <div class="text-right">
            <p class="text-xs text-stone-500">Total a pagar</p>
            <p class="text-lg font-bold text-stone-800" data-pagos-total>S/ 0.00</p>
        </div>
    </div>

    @if ($metodosPago->isEmpty())
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            No hay métodos de pago activos. Configure al menos uno en
            <a href="{{ route('admin.metodos-pago.index') }}" data-panel="/admin/metodos-pago" class="font-semibold underline">Métodos de pago</a>
            antes de registrar la operación.
        </div>
    @else
        @if ($metodosPago->count() > 1)
            <div class="mb-2 flex justify-end">
                <button type="button" data-pago-mixto data-activo="{{ $modoInicialMixto ? 'true' : 'false' }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-xs font-semibold text-stone-600 transition hover:border-emerald-300 hover:text-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-3-3m3 3l-3 3M16 17H4m0 0l3 3m-3-3l3-3"/>
                    </svg>
                    <span data-pago-mixto-label>{{ $modoInicialMixto ? 'Volver a pago único' : 'Pago mixto' }}</span>
                </button>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach ($metodosPago as $i => $metodo)
                <button type="button"
                    data-metodo-btn
                    data-metodo-id="{{ $metodo->id_metodo_pago }}"
                    data-selected="{{ ! $modoInicialMixto && $pagosPorMetodo->has($metodo->id_metodo_pago) ? 'true' : 'false' }}"
                    aria-pressed="{{ ! $modoInicialMixto && $pagosPorMetodo->has($metodo->id_metodo_pago) ? 'true' : 'false' }}"
                    @disabled($modoInicialMixto)
                    class="metodo-btn group relative flex flex-col items-start gap-0.5 rounded-xl border-2 border-stone-200 bg-white px-3 py-2.5 text-left transition hover:border-emerald-300 hover:bg-emerald-50/40 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-stone-200 disabled:hover:bg-white">
                    <span class="text-sm font-semibold text-stone-800">{{ $metodo->nombre }}</span>
                    @if ($metodo->descripcion)
                        <span class="text-[11px] leading-tight text-stone-400">{{ $metodo->descripcion }}</span>
                    @endif
                    <span class="absolute right-2 top-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-white"
                        data-metodo-check>
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                </button>

                <input type="hidden" name="pagos[{{ $i }}][metodo_pago_id]" value="{{ $metodo->id_metodo_pago }}">
            @endforeach
        </div>

        <div class="mt-3 space-y-2 {{ $modoInicialMixto ? '' : 'hidden' }}" data-pagos-detalle>
            @foreach ($metodosPago as $i => $metodo)
                @php($pagoActual = $pagosPorMetodo->get($metodo->id_metodo_pago))
                <div class="flex items-center gap-3" data-pago-row data-metodo-id="{{ $metodo->id_metodo_pago }}">
                    <label class="flex-1 text-sm text-stone-700">{{ $metodo->nombre }}</label>
                    <div class="relative w-32 shrink-0">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-stone-400">S/</span>
                        <input type="number" name="pagos[{{ $i }}][monto]"
                            data-metodo-id="{{ $metodo->id_metodo_pago }}"
                            value="{{ $pagoActual ? number_format((float) $pagoActual->monto, 2, '.', '') : '' }}"
                            step="0.01" min="0" inputmode="decimal" placeholder="0.00"
                            class="pago-monto w-full rounded-lg border border-stone-300 bg-white pl-8 pr-3 py-2 text-sm text-right focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    </div>
                    <button type="button" data-pago-resto
                        class="shrink-0 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-2 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100"
                        title="Completar el importe restante con este método">Resto</button>
                </div>
            @endforeach
        </div>

        <div class="mt-3 grid grid-cols-2 gap-3 border-t border-stone-200 pt-3">
            <div>
                <p class="text-xs text-stone-500">Pagado</p>
                <p class="text-sm font-semibold text-stone-800" data-pagos-pagado>S/ 0.00</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-stone-500" data-pagos-estado>Falta</p>
                <p class="text-sm font-bold text-red-600" data-pagos-falta>S/ 0.00</p>
            </div>
        </div>
        <p class="mt-2 hidden text-xs font-medium text-red-600" data-pagos-alerta></p>
    @endif
</div>
