<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Ventas</h1>
            <span class="text-xs bg-sky-100 text-sky-700 px-2.5 py-1 rounded-full font-medium">Solo mis ventas de hoy</span>
            <span class="text-xs text-stone-500">{{ $hoy->format('d/m/Y') }}</span>
            @if(!$cajaAbierta)
                <span class="text-xs bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full font-medium">Caja cerrada</span>
            @endif
        </div>
        <p class="text-sm text-stone-500 mt-1">Las ventas de días anteriores están en el historial.</p>
        <a href="{{ route('admin.ventas.historial') }}" data-panel="{{ route('admin.ventas.historial') }}"
            class="inline-block text-sm text-emerald-700 hover:text-emerald-900 font-medium mt-1">
            Ver historial completo →
        </a>
    </div>
    @if($cajaAbierta)
        <button type="button" data-modal="{{ route('admin.ventas.create') }}"
            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
            + Nueva venta
        </button>
    @else
        <button type="button" onclick="AdminToast.show('Debe abrir una caja primero para registrar ventas.', 'warning')"
            class="bg-stone-400 cursor-not-allowed text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
            + Nueva venta
        </button>
    @endif
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 px-4 py-3 mb-4 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
    <span class="text-stone-600">
        Hoy: <strong class="text-stone-800">{{ (int) $resumen->cantidad }}</strong>
        {{ (int) $resumen->cantidad === 1 ? 'venta confirmada' : 'ventas confirmadas' }}
    </span>
    <span class="text-stone-600">
        Total: <strong class="text-stone-800">S/ {{ number_format((float) $resumen->total, 2) }}</strong>
    </span>
</div>

@include('admin.ventas._tabla', ['ventas' => $ventas, 'modo' => 'dia'])
