<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Conciliaciones de inventario</h1>
        <p class="text-sm text-stone-500 mt-1">
            Compara el stock en caché con la suma de lotes. No corrige nada: deja evidencia.
        </p>
    </div>
    <form method="POST" action="{{ route('admin.conciliaciones.ejecutar') }}" data-conciliar-form class="self-start sm:self-auto">
        @csrf
        <button type="submit"
            class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition">
            Ejecutar conciliación
        </button>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">Fecha</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Usuario</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Origen</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium text-right">Productos</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium text-right">Con diferencia</th>
                    <th class="hidden xl:table-cell px-4 py-3 font-medium text-right">Lotes</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium text-right">Diferencia</th>
                    <th class="px-4 py-3 font-medium">Estado</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($conciliaciones as $conciliacion)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-700 whitespace-nowrap">
                            {{ $conciliacion->fecha?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">
                            {{ $conciliacion->user?->name ?? 'Sistema' }}
                        </td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">{{ $conciliacion->origen_label }}</td>
                        <td class="hidden lg:table-cell px-4 py-3 text-stone-600 text-right">{{ $conciliacion->total_productos }}</td>
                        <td class="hidden lg:table-cell px-4 py-3 text-right font-medium {{ $conciliacion->productos_con_diferencia > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                            {{ $conciliacion->productos_con_diferencia }}
                        </td>
                        <td class="hidden xl:table-cell px-4 py-3 text-stone-600 text-right">{{ $conciliacion->lotes_inconsistentes }}</td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-600 text-right">
                            {{ \App\Helpers\CantidadHelper::formatear($conciliacion->diferencia_total) }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center text-xs font-medium px-2.5 py-0.5 rounded-full {{ $conciliacion->estado_clase }}">
                                {{ $conciliacion->estado_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" data-modal="{{ route('admin.conciliaciones.show', $conciliacion->id_conciliacion_inventario) }}"
                                class="text-sky-600 hover:text-sky-800 p-1.5 hover:bg-sky-50 rounded-lg transition" title="Ver detalle">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-stone-400">
                            No hay conciliaciones registradas. Ejecuta la primera desde el botón superior o por consola.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@vite('resources/js/admin/conciliaciones/index.js')


@include('admin.partials.pagination', ['items' => $conciliaciones])
