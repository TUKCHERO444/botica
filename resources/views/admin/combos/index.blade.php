<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Combos — Productos</h1>
    <button type="button" id="btn-crear-combo"
        class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
        + Nuevo combo
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="px-4 py-3 font-medium w-24">Productos</th>
                    <th class="px-4 py-3 font-medium w-36">Precio Sugerido</th>
                    <th class="px-4 py-3 font-medium w-20">Estado</th>
                    <th class="px-4 py-3 font-medium w-24 text-right">Acción</th>
                </tr>
            </thead>
            <tbody id="tabla-combos">
                @forelse ($combos as $combo)
                    <tr class="border-b border-stone-100 hover:bg-stone-50 transition" data-combo-id="{{ $combo->id_combo }}">
                        <td class="px-4 py-3 text-stone-800 font-medium">{{ $combo->nombre }}</td>
                        <td class="px-4 py-3 text-stone-500">{{ $combo->productos_count }}</td>
                        <td class="px-4 py-3 text-stone-700 font-medium">S/ {{ number_format($combo->precio_sugerido, 2) }}</td>
                        <td class="px-4 py-3">
                            @if($combo->activo)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Activo</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-stone-100 text-stone-600">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" data-combo-id="{{ $combo->id_combo }}"
                                class="btn-editar text-emerald-600 hover:text-emerald-800 text-xs font-medium transition mr-3">
                                Editar
                            </button>
                            <button type="button" data-combo-id="{{ $combo->id_combo }}"
                                class="btn-eliminar text-red-600 hover:text-red-800 text-xs font-medium transition">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center text-sm text-stone-400">
                            No hay combos. Crea el primero.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@vite('resources/js/admin/combos/index.js')


@include('admin.partials.pagination', ['items' => $combos])
