@php
    $pp = $pp ?? null;
    $i = $i ?? '__INDEX__';
    $pasoValor = is_numeric($i)
        ? old("presentaciones.{$i}.paso", $pp?->paso_input ?? 1)
        : ($pp?->paso_input ?? 1);
    $pasoAvanzado = (float) $pasoValor !== 1.0;
@endphp

<div class="presentacion-row grid grid-cols-1 sm:grid-cols-2 md:grid-cols-12 gap-3 items-start bg-stone-50 border border-stone-200 rounded-lg p-3">
    <div class="sm:col-span-2 md:col-span-4">
        <label class="block text-xs font-medium text-stone-600 mb-1.5">Presentación</label>
        <select name="presentaciones[{{ $i }}][presentacion_id]" required
            class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            <option value="">Seleccione</option>
            @foreach ($presentaciones as $presentacion)
                <option value="{{ $presentacion->id_presentacion }}" @selected(($pp?->presentacion_id ?? null) == $presentacion->id_presentacion)>
                    {{ $presentacion->nombre }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-stone-600 mb-1.5">Factor</label>
        <input type="number" name="presentaciones[{{ $i }}][factor]" value="{{ $pp?->factor_input ?? 1 }}"
            step="0.001" min="0.001" required title="Unidades base"
            class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
    </div>

    <div class="md:col-span-2">
        <label class="block text-xs font-medium text-stone-600 mb-1.5">Precio (S/)</label>
        <input type="number" name="presentaciones[{{ $i }}][precio_venta]" value="{{ $pp?->precio_venta_input ?? '' }}"
            step="0.01" min="0" required
            class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
    </div>

    <div class="md:col-span-2">
        <div class="flex items-center justify-between gap-2 mb-1.5">
            <label class="block text-xs font-medium text-stone-600">Paso</label>
            <label class="inline-flex items-center gap-1 text-[10px] text-stone-500 whitespace-nowrap">
                <input type="checkbox" class="paso-avanzado" @checked($pasoAvanzado)
                    class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                Avanzado
            </label>
        </div>
        <div class="paso-wrap {{ $pasoAvanzado ? '' : 'hidden' }}">
            <input type="number" name="presentaciones[{{ $i }}][paso]" value="{{ $pasoValor }}"
                step="0.001" min="0.001" required placeholder="Incremento mínimo"
                class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
    </div>

    <div class="md:col-span-1">
        <label class="block text-xs font-medium text-stone-600 mb-1.5">Activa</label>
        <label class="inline-flex items-center gap-1.5 h-[42px] text-xs text-stone-600">
            <input type="hidden" name="presentaciones[{{ $i }}][activo]" value="0">
            <input type="checkbox" name="presentaciones[{{ $i }}][activo]" value="1" @checked($pp?->activo ?? true)
                class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
            Sí
        </label>
    </div>

    <div class="md:col-span-1 flex justify-end pt-6">
        <button type="button" class="eliminar-presentacion text-red-400 hover:text-red-600 p-1.5 hover:bg-red-50 rounded-lg transition" title="Quitar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </button>
    </div>

    <input type="hidden" name="presentaciones[{{ $i }}][codigo_barras]" value="{{ $pp?->codigo_barras }}">
</div>

