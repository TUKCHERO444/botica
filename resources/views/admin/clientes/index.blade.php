<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Clientes</h1>
    <button type="button" data-modal="{{ route('admin.clientes.create') }}"
        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto">
        + Nuevo cliente
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium">ID</th>
                    <th class="px-4 py-3 font-medium">DNI</th>
                    <th class="px-4 py-3 font-medium">Nombre</th>
                    <th class="hidden sm:table-cell px-4 py-3 font-medium">Apellido Paterno</th>
                    <th class="hidden md:table-cell px-4 py-3 font-medium">Apellido Materno</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Email</th>
                    <th class="hidden lg:table-cell px-4 py-3 font-medium">Teléfono</th>
                    <th class="px-4 py-3 font-medium">Registro</th>
                    <th class="px-4 py-3 font-medium text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse ($clientes as $c)
                    <tr class="hover:bg-stone-50 transition">
                        <td class="px-4 py-3 text-stone-500">{{ $c->id_cliente }}</td>
                        <td class="px-4 py-3 font-mono text-stone-800">{{ $c->dni ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium text-stone-800">{{ $c->nombre }}</td>
                        <td class="hidden sm:table-cell px-4 py-3 text-stone-500">{{ $c->apellido_paterno ?? '—' }}</td>
                        <td class="hidden md:table-cell px-4 py-3 text-stone-500">{{ $c->apellido_materno ?? '—' }}</td>
                        <td class="hidden lg:table-cell px-4 py-3 text-stone-500">{{ $c->email ?? '—' }}</td>
                        <td class="hidden lg:table-cell px-4 py-3 text-stone-500">{{ $c->telefono ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($c->registro_web)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    Registrado
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-stone-100 text-stone-500">
                                    Admin
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" data-modal="{{ route('admin.clientes.edit', $c->id_cliente) }}"
                                    class="text-indigo-600 hover:text-indigo-800 p-1.5 hover:bg-indigo-50 rounded-lg transition" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                <button type="button" data-delete="{{ route('admin.clientes.destroy', $c->id_cliente) }}"
                                    class="text-red-500 hover:text-red-700 p-1.5 hover:bg-red-50 rounded-lg transition" title="Eliminar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-stone-400">No hay clientes registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
