<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Promociones — Portada</h1>
    <button type="button" id="btn-crear-promocion"
        class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
        + Nueva promoción
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium w-10"></th>
                    <th class="px-4 py-3 font-medium w-24">Imagen</th>
                    <th class="px-4 py-3 font-medium">Título</th>
                    <th class="px-4 py-3 font-medium">Subtítulo</th>
                    <th class="px-4 py-3 font-medium w-28">Tipo Prom.</th>
                    <th class="px-4 py-3 font-medium w-24">Layout</th>
                    <th class="px-4 py-3 font-medium w-16">Clicks</th>
                    <th class="px-4 py-3 font-medium w-20">Estado</th>
                    <th class="px-4 py-3 font-medium w-24 text-right">Acción</th>
                </tr>
            </thead>
            <tbody id="tabla-promociones">
                @forelse ($promociones as $promocion)
                    <tr class="border-b border-stone-100 hover:bg-stone-50 transition" data-promocion-id="{{ $promocion->id_promocion }}">
                        <td class="px-4 py-3 text-stone-400 cursor-grab drag-handle">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
                            </svg>
                        </td>
                        <td class="px-4 py-3">
                            <img src="{{ $promocion->imagen_url }}" alt="{{ $promocion->titulo ?? '' }}"
                                class="w-20 h-12 object-cover rounded-lg border border-stone-200">
                        </td>
                        <td class="px-4 py-3 text-stone-800 font-medium">{{ $promocion->titulo ?? '—' }}</td>
                        <td class="px-4 py-3 text-stone-500">{{ Str::limit($promocion->subtitulo, 40) ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($promocion->tipo_promocion === 'descuento')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                    {{ ucfirst($promocion->ambito) }} {{ $promocion->descuento_valor }}{{ $promocion->descuento_tipo === 'porcentaje' ? '%' : '' }}
                                </span>
                            @elseif($promocion->tipo_promocion === 'combo')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Combo</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-stone-100 text-stone-600">Simple</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($promocion->tipo_layout === 'principal')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Principal</span>
                            @elseif($promocion->tipo_layout === 'terciaria')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Terciaria</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800">Secundaria</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 text-sm font-medium text-stone-700">
                                <svg class="w-4 h-4 text-stone-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                {{ $promocion->clicks ?? 0 }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($promocion->activo)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Activo</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-stone-100 text-stone-600">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" data-promocion-id="{{ $promocion->id_promocion }}"
                                class="btn-editar text-emerald-600 hover:text-emerald-800 text-xs font-medium transition mr-3">
                                Editar
                            </button>
                            <button type="button" data-promocion-id="{{ $promocion->id_promocion }}"
                                class="btn-eliminar text-red-600 hover:text-red-800 text-xs font-medium transition">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-12 text-center text-sm text-stone-400">
                            No hay promociones. Crea la primera.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@vite('resources/js/admin/promociones/index.js')
