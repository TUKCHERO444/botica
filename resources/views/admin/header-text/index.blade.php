<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Textos del Header — Portada</h1>
    <button type="button" id="btn-crear-header-text"
        class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
        + Nuevo texto
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium w-10"></th>
                    <th class="px-4 py-3 font-medium">Texto</th>
                    <th class="px-4 py-3 font-medium w-20">Estado</th>
                    <th class="px-4 py-3 font-medium w-24 text-right">Acción</th>
                </tr>
            </thead>
            <tbody id="tabla-header-texts">
                @forelse ($headerTexts as $headerText)
                    <tr class="border-b border-stone-100 hover:bg-stone-50 transition" data-header-text-id="{{ $headerText->id_header_text }}">
                        <td class="px-4 py-3 text-stone-400 cursor-grab drag-handle">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
                            </svg>
                        </td>
                        <td class="px-4 py-3 text-stone-800 font-medium">{{ $headerText->texto }}</td>
                        <td class="px-4 py-3">
                            @if($headerText->activo)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Activo</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-stone-100 text-stone-600">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" data-header-text-id="{{ $headerText->id_header_text }}"
                                class="btn-editar text-emerald-600 hover:text-emerald-800 text-xs font-medium transition mr-3">
                                Editar
                            </button>
                            <button type="button" data-header-text-id="{{ $headerText->id_header_text }}"
                                class="btn-eliminar text-red-600 hover:text-red-800 text-xs font-medium transition">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-12 text-center text-sm text-stone-400">
                            No hay textos. Crea el primero.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@vite('resources/js/admin/header-text/index.js')
