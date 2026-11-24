<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Atenciones</h1>
            <span class="text-xs bg-sky-100 text-sky-700 px-2.5 py-1 rounded-full font-medium">Solo mis atenciones de hoy</span>
            <span class="text-xs text-stone-500">{{ $hoy->format('d/m/Y') }}</span>
            @if(!$cajaAbierta)
                <span class="text-xs bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full font-medium">Caja cerrada</span>
            @endif
        </div>
        <p class="text-sm text-stone-500 mt-1">Las atenciones de días anteriores están en el historial.</p>
        <a href="{{ route('admin.atenciones.historial') }}" data-panel="{{ route('admin.atenciones.historial') }}"
            class="inline-block text-sm text-emerald-700 hover:text-emerald-900 font-medium mt-1">
            Ver historial completo →
        </a>
    </div>
    @if($cajaAbierta)
        <button type="button" data-modal="{{ route('admin.atenciones.create') }}"
            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
            + Nueva atención
        </button>
    @else
        <button type="button" onclick="AdminToast.show('Debe abrir una caja primero para registrar atenciones.', 'warning')"
            class="bg-stone-400 cursor-not-allowed text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
            + Nueva atención
        </button>
    @endif
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 px-4 py-3 mb-4 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
    <span class="text-stone-600">
        Hoy: <strong class="text-stone-800">{{ (int) $resumen->cantidad }}</strong>
        {{ (int) $resumen->cantidad === 1 ? 'atención confirmada' : 'atenciones confirmadas' }}
    </span>
    <span class="text-stone-600">
        Total: <strong class="text-stone-800">S/ {{ number_format((float) $resumen->total, 2) }}</strong>
    </span>
</div>

@include('admin.atenciones._tabla', ['atenciones' => $atenciones, 'modo' => 'dia'])
