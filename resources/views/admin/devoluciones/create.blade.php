<form data-modal-size="3xl" action="{{ route('admin.devoluciones.store') }}" method="POST" class="p-6">
    @csrf
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Nueva devolución a proveedor</h2>
            <p class="text-xs text-stone-500 mt-1">
                Se descuenta del lote específico de la compra. No aplica FEFO.
            </p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Compra <span class="text-red-500">*</span></label>
            <select id="devolucion-ingreso" name="ingreso_id_ingreso" required
                data-create-url="{{ route('admin.devoluciones.create') }}"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Seleccione una compra recibida</option>
                @foreach ($ingresos as $compra)
                    <option value="{{ $compra->id_ingreso }}" @selected($ingreso?->id_ingreso == $compra->id_ingreso)>
                        {{ $compra->correlativo }} — {{ $compra->proveedor->razon_social }} — {{ $compra->numero_documento }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Fecha</label>
            <input type="date" name="fecha" value="{{ old('fecha', now()->toDateString()) }}"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
    </div>

    @if ($ingreso)
        <div class="bg-stone-50 border border-stone-200 rounded-lg p-4 mb-4 grid grid-cols-1 sm:grid-cols-3 gap-2">
            <div>
                <div class="text-xs text-stone-500">Proveedor</div>
                <div class="text-sm font-medium text-stone-800">{{ $ingreso->proveedor->razon_social }}</div>
            </div>
            <div>
                <div class="text-xs text-stone-500">Documento</div>
                <div class="text-sm text-stone-700">{{ $ingreso->tipo_documento_label }} {{ $ingreso->numero_documento }}</div>
            </div>
            <div>
                <div class="text-xs text-stone-500">Fecha de compra</div>
                <div class="text-sm text-stone-700">{{ $ingreso->fecha?->format('d/m/Y') }}</div>
            </div>
        </div>

        <div class="mb-4">
            <h3 class="text-sm font-semibold text-stone-700 mb-2">Lotes disponibles de esta compra</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-stone-100 text-stone-600 text-left">
                            <th class="px-3 py-2 font-medium">Producto</th>
                            <th class="px-3 py-2 font-medium">Presentación</th>
                            <th class="px-3 py-2 font-medium">Lote</th>
                            <th class="px-3 py-2 font-medium">Vence</th>
                            <th class="px-3 py-2 font-medium text-right">Disponible</th>
                            <th class="px-3 py-2 font-medium text-right">Costo unit.</th>
                            <th class="px-3 py-2 font-medium text-center">Cantidad a devolver</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @php $hayLineas = false; @endphp
                        @foreach ($ingreso->detalles as $i => $detalle)
                            @php
                                $capa = $detalle->loteIngreso;
                                $disponibleBase = (float) ($capa?->cantidad_disponible_base ?? 0);
                                $factor = (float) $detalle->factor;
                                $disponiblePresentacion = $factor > 0 ? $disponibleBase / $factor : 0;
                                $paso = (float) ($detalle->presentacion?->paso ?? 1);
                                $unidadBase = $detalle->producto?->unidadBase?->codigo;
                                $maximoPresentacion = $detalle->presentacion
                                    ? \App\Helpers\PresentacionStockHelper::maximoPorPresentacion(
                                        $detalle->presentacion,
                                        $disponibleBase,
                                        $unidadBase
                                    )
                                    : null;
                            @endphp

                            <tr>
                                <td class="px-3 py-2 text-stone-700">{{ $detalle->producto?->nombre }}</td>
                                <td class="px-3 py-2 text-stone-600">{{ $detalle->presentacion?->nombre_completo }}</td>
                                <td class="px-3 py-2 font-mono text-stone-600">{{ $detalle->lote?->codigo_lote ?? $detalle->lote_codigo }}</td>
                                <td class="px-3 py-2 text-stone-600">{{ $detalle->fecha_vencimiento?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-stone-600 text-right">
                                    @if ($disponibleBase > 0 && $maximoPresentacion)
                                        <div class="font-medium text-stone-700">{{ $maximoPresentacion['texto'] }}</div>
                                        <div class="text-[11px] text-stone-400">
                                            {{ \App\Helpers\CantidadHelper::formatear($disponibleBase) }} {{ $unidadBase }} en base
                                        </div>
                                    @else
                                        <div class="font-medium text-stone-400">Sin disponible</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-stone-600 text-right">S/ {{ number_format((float) $detalle->costo_unitario, 2) }}</td>
                                <td class="px-3 py-2">
                                    @if ($disponibleBase > 0)
                                        @php $hayLineas = true; @endphp
                                        <input type="hidden" name="lineas[{{ $i }}][detalle_ingreso_id]" value="{{ $detalle->id_detalle_ingreso }}">
                                        <input type="number" name="lineas[{{ $i }}][cantidad]" value="0"
                                            min="0" max="{{ $disponiblePresentacion }}" step="{{ $paso }}"
                                            data-costo="{{ (float) $detalle->costo_unitario }}"
                                            data-factor="{{ $factor }}"
                                            data-unidad="{{ $unidadBase }}"
                                            class="linea-cantidad w-full rounded-lg border border-stone-300 px-3 py-2 text-sm text-right focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                                        <p class="linea-equivalencia text-[10px] leading-tight text-stone-500 mt-1 hidden"></p>
                                    @else
                                        <span class="text-xs text-stone-400">Sin disponible</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (! $hayLineas)
                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 mt-2">
                    Esta compra no tiene lotes con cantidad disponible. Puede que ya se haya vendido o devuelto.
                </p>
            @endif
        </div>
    @else
        <div class="bg-stone-50 border border-dashed border-stone-300 rounded-lg p-6 mb-4 text-center">
            <p class="text-sm text-stone-500">Selecciona una compra para cargar sus lotes y cantidades disponibles.</p>
        </div>
    @endif

    <div class="mb-4">
        <label class="block text-sm font-medium text-stone-700 mb-1">Motivo <span class="text-red-500">*</span></label>
        <textarea name="motivo" rows="2" maxlength="255" required
            class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
            placeholder="Ej.: producto dañado, vencido, error de pedido, retiro acordado">{{ old('motivo') }}</textarea>
    </div>

    <div class="bg-stone-50 border border-stone-200 rounded-lg p-4 mb-5">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-stone-700">
                <input type="hidden" name="reembolsado_a_caja" value="0">
                <input type="checkbox" name="reembolsado_a_caja" value="1" @checked(old('reembolsado_a_caja'))
                    class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                ¿El reembolso ingresa a caja?
            </label>
            <div class="flex-1">
                <select name="metodo_pago"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                    <option value="">Método de reembolso</option>
                    @foreach (\App\Models\Ingreso::METODOS_PAGO as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('metodo_pago') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <input type="text" name="referencia_pago" value="{{ old('referencia_pago') }}" maxlength="100"
                    placeholder="Nro. de operación o nota (opcional)"
                    class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            </div>
        </div>
        @if ($cajaAbierta)
            <p class="text-xs text-emerald-700 mt-2">
                Caja abierta: #{{ $cajaAbierta->id_caja }} — disponible S/ {{ number_format($cajaAbierta->monto_actual, 2) }}.
            </p>
        @else
            <p class="text-xs text-amber-700 mt-2">
                No hay caja abierta. Puedes registrar la devolución sin reembolso a caja.
            </p>
        @endif
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-stone-200">
        <div class="text-sm text-stone-600">
            Total devuelto: <span class="font-bold text-stone-800">S/ <span id="devolucion-total">0.00</span></span>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" data-close-modal
                class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
            <button type="submit"
                class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Registrar devolución</button>
        </div>
    </div>
</form>

@vite('resources/js/admin/devoluciones/create.js')
