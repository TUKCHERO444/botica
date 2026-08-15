<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-stone-800">Detalle de Auditoría</h2>
        <button type="button" onclick="AdminModal.close()" class="text-stone-400 hover:text-stone-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Info general --}}
    <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
        <div>
            <span class="text-stone-500 block text-xs uppercase tracking-wider">Usuario</span>
            <span class="font-medium text-stone-800">{{ $auditorium->user?->name ?? '—' }}</span>
            <span class="text-stone-400 block">{{ $auditorium->user?->email ?? '' }}</span>
        </div>
        <div>
            <span class="text-stone-500 block text-xs uppercase tracking-wider">Acción</span>
            <span class="font-medium text-stone-800">{{ ucfirst($auditorium->accion) }}</span>
        </div>
        <div>
            <span class="text-stone-500 block text-xs uppercase tracking-wider">Módulo</span>
            <span class="font-medium text-stone-800">{{ ucfirst($auditorium->modulo) }}</span>
        </div>
        <div>
            <span class="text-stone-500 block text-xs uppercase tracking-wider">Registro ID</span>
            <span class="font-medium text-stone-800 font-mono">{{ $auditorium->registro_id ?? '—' }}</span>
        </div>
        <div>
            <span class="text-stone-500 block text-xs uppercase tracking-wider">Fecha y hora</span>
            <span class="font-medium text-stone-800">{{ $auditorium->created_at?->format('d/m/Y H:i:s') ?? '—' }}</span>
        </div>
        <div>
            <span class="text-stone-500 block text-xs uppercase tracking-wider">Dirección IP</span>
            <span class="font-medium text-stone-800 font-mono">{{ $auditorium->ip_address ?? '—' }}</span>
        </div>
    </div>

    <div class="mb-6">
        <span class="text-stone-500 block text-xs uppercase tracking-wider mb-1">Descripción</span>
        <p class="text-stone-800 bg-stone-50 rounded-lg px-4 py-3 text-sm">{{ $auditorium->descripcion }}</p>
    </div>

    {{-- Cambios --}}
    @if (!empty($cambios))
        <div>
            <h3 class="text-sm font-semibold text-stone-700 mb-3">Cambios realizados</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-stone-50 text-stone-600 uppercase text-xs tracking-wider">
                            <th class="text-left px-4 py-2 font-semibold">Campo</th>
                            <th class="text-left px-4 py-2 font-semibold">Valor anterior</th>
                            <th class="text-left px-4 py-2 font-semibold">Valor nuevo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @foreach ($cambios as $cambio)
                            <tr class="hover:bg-stone-50 transition">
                                <td class="px-4 py-2 font-mono text-xs text-stone-600">{{ $cambio['campo'] }}</td>
                                <td class="px-4 py-2 text-stone-500 {{ $cambio['nuevo'] !== null ? 'line-through' : '' }}">
                                    {{ is_bool($cambio['anterior']) ? ($cambio['anterior'] ? 'true' : 'false') : ($cambio['anterior'] ?? '—') }}
                                </td>
                                <td class="px-4 py-2 text-stone-800 font-medium">
                                    {{ is_bool($cambio['nuevo']) ? ($cambio['nuevo'] ? 'true' : 'false') : ($cambio['nuevo'] ?? '—') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <p class="text-sm text-stone-400 italic">No hay datos de cambios disponibles para esta acción.</p>
    @endif

    <div class="flex justify-end mt-6 pt-4 border-t border-stone-200">
        <button type="button" onclick="AdminModal.close()"
            class="px-4 py-2 text-sm bg-stone-200 hover:bg-stone-300 text-stone-700 rounded-lg transition">Cerrar</button>
    </div>
</div>
