@php($producto = $producto ?? null)

<div class="border-t border-stone-200 pt-5">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
        <div>
            <h3 class="text-sm font-semibold text-stone-800">Presentaciones adicionales</h3>
            <p class="text-xs text-stone-400 mt-0.5">
                La presentación base (factor 1) se crea automáticamente con la unidad y el precio de venta del producto.
            </p>
        </div>
        <button type="button" id="agregar-presentacion"
            class="text-xs bg-emerald-100 text-emerald-700 hover:bg-emerald-200 px-3 py-1.5 rounded-lg transition font-medium self-start">
            + Agregar presentación
        </button>
    </div>

    @if ($producto?->presentacionBase)
        <div class="mb-3 flex flex-wrap items-center gap-2 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 text-xs text-emerald-800">
            <span class="font-semibold">Base:</span>
            <span>{{ $producto->presentacionBase->nombre_completo }}</span>
            <span class="text-emerald-600">factor 1</span>
            <span class="font-semibold">S/ {{ number_format($producto->presentacionBase->precio_venta, 2) }}</span>
        </div>
    @endif

    <div id="presentaciones-container" class="space-y-2">
        @if ($producto)
            @foreach ($producto->presentaciones->where('es_base', false) as $pp)
                @include('admin.productos._presentacion-row', [
                    'i' => $loop->index,
                    'pp' => $pp,
                    'presentaciones' => $presentaciones,
                ])
            @endforeach
        @endif
    </div>

    <template id="presentacion-template">
        @include('admin.productos._presentacion-row', [
            'i' => '__INDEX__',
            'pp' => null,
            'presentaciones' => $presentaciones,
        ])
    </template>
</div>
