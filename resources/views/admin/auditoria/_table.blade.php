<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 uppercase text-xs tracking-wider">
                    <th class="text-left px-4 py-3 font-semibold">#</th>
                    <th class="text-left px-4 py-3 font-semibold">Usuario</th>
                    <th class="text-left px-4 py-3 font-semibold">Módulo</th>
                    <th class="text-left px-4 py-3 font-semibold">Acción</th>
                    <th class="text-left px-4 py-3 font-semibold">Descripción</th>
                    <th class="text-left px-4 py-3 font-semibold">Fecha</th>
                    <th class="text-center px-4 py-3 font-semibold">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($items as $item)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-500 font-mono text-xs">{{ $item->id_auditoria }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-stone-800">{{ $item->user?->name ?? '—' }}</span>
                            <span class="text-stone-400 text-xs block">{{ $item->user?->email ?? '' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                {{ ucfirst($item->modulo) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $badge = match ($item->accion) {
                                    'crear' => 'bg-blue-100 text-blue-700',
                                    'actualizar' => 'bg-amber-100 text-amber-700',
                                    'eliminar' => 'bg-red-100 text-red-700',
                                    'toggle-estado' => 'bg-purple-100 text-purple-700',
                                    default => 'bg-stone-100 text-stone-700',
                                };
                            @endphp
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                {{ __(ucfirst($item->accion)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-stone-700 max-w-xs truncate">{{ $item->descripcion }}</td>
                        <td class="px-4 py-3 text-stone-500 text-xs whitespace-nowrap">
                            {{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="#" data-panel="{{ route('admin.auditoria.show', $item) }}"
                                class="text-emerald-600 hover:text-emerald-800 transition text-sm font-medium">
                                Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-stone-400">
                            No se encontraron registros de auditoría.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('admin.partials.pagination', ['items' => $items])
</div>
