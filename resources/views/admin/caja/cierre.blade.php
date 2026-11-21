<form id="form-cierre-caja" data-modal-size="2xl" data-esperado="{{ (float) $efectivoEsperado }}"
    action="{{ route('admin.caja.cierre') }}" method="POST" class="p-6">
    @csrf

    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-bold text-stone-800">Cerrar caja y arquear</h2>
            <p class="text-xs text-stone-500 mt-1">
                Caja #{{ $caja->id_caja }} · Apertura {{ $caja->apertura_at->format('d/m/Y H:i') }}
            </p>
        </div>
        <button type="button" data-close-modal class="text-stone-400 hover:text-stone-600 p-1">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- PASO 1: CONTEO --}}
    <div id="cierre-paso-conteo">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                <p class="text-[11px] text-stone-500 uppercase tracking-wide">Fondo inicial</p>
                <p class="text-base font-bold text-stone-800">S/ {{ number_format((float) $caja->monto_inicial, 2) }}</p>
            </div>
            <div class="rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                <p class="text-[11px] text-stone-500 uppercase tracking-wide">Ingresos efectivo</p>
                <p class="text-base font-bold text-emerald-600">S/ {{ number_format((float) $resumen['ingresos_efectivo'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                <p class="text-[11px] text-stone-500 uppercase tracking-wide">Egresos efectivo</p>
                <p class="text-base font-bold text-red-600">S/ {{ number_format((float) $resumen['egresos_efectivo'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-[11px] text-emerald-700 uppercase tracking-wide">Efectivo esperado</p>
                <p class="text-base font-bold text-emerald-800">S/ {{ number_format((float) $efectivoEsperado, 2) }}</p>
            </div>
        </div>

        <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
            El efectivo esperado ya está calculado. Cuente el cajón y compare; si hay diferencia, se justificará en el siguiente paso.
        </div>

        <div class="mb-4">
            <p class="text-sm font-medium text-stone-700 mb-2">¿Cómo quieres contar?</p>
            <div class="flex flex-wrap gap-2">
                <label class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 cursor-pointer">
                    <input type="radio" name="modo_conteo" value="rapido" checked class="text-emerald-600 focus:ring-emerald-500">
                    Conteo rápido
                </label>
                <label class="inline-flex items-center gap-2 rounded-lg border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 cursor-pointer">
                    <input type="radio" name="modo_conteo" value="detallado" class="text-emerald-600 focus:ring-emerald-500">
                    Detallado por denominación
                </label>
            </div>
        </div>

        <div id="bloque-conteo-rapido" class="rounded-lg border border-stone-200 p-4 mb-4">
            <label class="block text-sm font-medium text-stone-700 mb-1">Efectivo contado (S/) <span class="text-red-500">*</span></label>
            <input type="number" id="conteo-rapido" step="0.01" min="0" inputmode="decimal" placeholder="0.00"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-lg font-bold text-stone-800 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
            <p class="text-xs text-stone-400 mt-1">Ingresa el total contado. Si necesitas detallar billetes y monedas, cambia a conteo detallado.</p>
        </div>

        <div id="bloque-conteo-detallado" class="hidden rounded-lg border border-stone-200 p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <h3 class="text-xs font-semibold text-stone-500 uppercase tracking-wide mb-2">Billetes</h3>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-stone-500 text-left">
                                <th class="pb-1 font-medium">Denominación</th>
                                <th class="pb-1 font-medium">Cantidad</th>
                                <th class="pb-1 font-medium text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach (['200', '100', '50', '20', '10'] as $valor)
                                <tr>
                                    <td class="py-1.5 text-stone-700">S/ {{ number_format((float) $valor, 2) }}</td>
                                    <td class="py-1.5">
                                        <input type="number" name="denominaciones[{{ $valor }}]" min="0" max="100000" step="1"
                                            data-denominacion="{{ $valor }}" placeholder="0"
                                            class="w-24 rounded-lg border border-stone-300 px-2 py-1 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                                    </td>
                                    <td class="py-1.5 text-right text-stone-600" data-subtotal="{{ $valor }}">S/ 0.00</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    <h3 class="text-xs font-semibold text-stone-500 uppercase tracking-wide mb-2">Monedas</h3>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-stone-500 text-left">
                                <th class="pb-1 font-medium">Denominación</th>
                                <th class="pb-1 font-medium">Cantidad</th>
                                <th class="pb-1 font-medium text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach (['5', '2', '1', '0.5', '0.2', '0.1', '0.05'] as $valor)
                                <tr>
                                    <td class="py-1.5 text-stone-700">S/ {{ number_format((float) $valor, 2) }}</td>
                                    <td class="py-1.5">
                                        <input type="number" name="denominaciones[{{ $valor }}]" min="0" max="100000" step="1"
                                            data-denominacion="{{ $valor }}" placeholder="0"
                                            class="w-24 rounded-lg border border-stone-300 px-2 py-1 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                                    </td>
                                    <td class="py-1.5 text-right text-stone-600" data-subtotal="{{ $valor }}">S/ 0.00</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3 flex justify-end">
                <button type="button" id="btn-limpiar-conteo"
                    class="text-xs text-stone-500 hover:text-stone-700 underline">Limpiar conteo detallado</button>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 mb-5">
            <div>
                <p class="text-xs text-emerald-700 uppercase tracking-wide">Total contado</p>
                <p class="text-xs text-emerald-700 mt-0.5">Se actualiza según el conteo seleccionado.</p>
            </div>
            <p id="total-contado" class="text-2xl font-bold text-emerald-800">S/ 0.00</p>
        </div>

        <input type="hidden" name="efectivo_contado" id="efectivo-contado" value="0.00">

        <div class="flex justify-end gap-3 pt-4 border-t border-stone-200">
            <button type="button" data-close-modal
                class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Cancelar</button>
            <button type="button" id="btn-revisar-cierre"
                class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition font-medium">
                Revisar cierre
            </button>
        </div>
    </div>

    {{-- PASO 2: REVISIÓN --}}
    <div id="cierre-paso-revision" class="hidden">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                <p class="text-[11px] text-stone-500 uppercase tracking-wide">Efectivo esperado</p>
                <p id="revision-esperado" class="text-lg font-bold text-stone-800">S/ 0.00</p>
            </div>
            <div class="rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                <p class="text-[11px] text-stone-500 uppercase tracking-wide">Efectivo contado</p>
                <p id="revision-contado" class="text-lg font-bold text-stone-800">S/ 0.00</p>
            </div>
            <div class="rounded-lg border border-stone-200 bg-stone-50 px-4 py-3">
                <p class="text-[11px] text-stone-500 uppercase tracking-wide">Diferencia</p>
                <p id="revision-diferencia" class="text-lg font-bold text-emerald-600">S/ 0.00</p>
                <p id="revision-diferencia-label" class="text-xs text-stone-500 mt-0.5">Cuadra</p>
            </div>
        </div>

        <div id="bloque-diferencia" class="hidden rounded-lg border border-amber-200 bg-amber-50 p-4 mb-4">
            <p class="text-sm font-medium text-amber-900 mb-3">Hay una diferencia. Para cerrar, indica el motivo y una observación.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 mb-1">Motivo de la diferencia <span class="text-red-500">*</span></label>
                    <select name="motivo_diferencia" id="motivo-diferencia"
                        class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <option value="">Seleccione un motivo</option>
                        @foreach ($motivosDiferencia as $clave => $etiqueta)
                            <option value="{{ $clave }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label id="label-observacion" class="block text-sm font-medium text-stone-700 mb-1">Observación del cierre (opcional)</label>
            <textarea name="observacion_cierre" id="observacion-cierre" rows="3" maxlength="1000"
                class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                placeholder="Opcional: alguna nota del turno."></textarea>
            <p id="ayuda-observacion" class="text-xs text-stone-400 mt-1">Si hay una diferencia, la observación será obligatoria.</p>
        </div>

        <div id="bloque-cero" class="hidden rounded-lg border border-amber-300 bg-amber-50 p-4 mb-4">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="confirmo_cero" id="confirmo-cero" value="1"
                    class="mt-0.5 rounded border-stone-300 text-amber-600 focus:ring-amber-500">
                <span class="text-sm text-amber-900">
                    Confirmo que el cajón quedó en <strong>S/ 0.00</strong>. Entiendo que el cierre se registrará con efectivo contado cero.
                </span>
            </label>
        </div>

        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 mb-4">
            Al cerrar la caja no podrás registrar ventas ni atenciones hasta abrir una nueva.
        </div>

        <div class="flex flex-wrap justify-end gap-3 pt-4 border-t border-stone-200">
            <button type="button" id="btn-volver-conteo"
                class="px-4 py-2 text-sm text-stone-600 hover:text-stone-800 transition">Volver al conteo</button>
            <button type="submit" id="btn-confirmar-cierre"
                class="px-4 py-2 text-sm bg-stone-700 hover:bg-stone-800 text-white rounded-lg transition font-medium">
                Confirmar cierre
            </button>
        </div>
    </div>
</form>

@vite('resources/js/admin/caja/cierre.js')
