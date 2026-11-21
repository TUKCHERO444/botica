<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Banners — Portada</h1>
    <button type="button" id="btn-crear-banner"
        class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
        + Nuevo banner
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-stone-50 text-stone-600 text-left">
                    <th class="px-4 py-3 font-medium w-16">Orden</th>
                    <th class="px-4 py-3 font-medium w-24">Imagen</th>
                    <th class="px-4 py-3 font-medium">Título</th>
                    <th class="px-4 py-3 font-medium">Subtítulo</th>
                    <th class="px-4 py-3 font-medium w-20">Estado</th>
                    <th class="px-4 py-3 font-medium">Enlace</th>
                    <th class="px-4 py-3 font-medium w-24 text-right">Acción</th>
                </tr>
            </thead>
            <tbody id="tabla-banners">
                @forelse ($banners as $banner)
                    <tr class="border-b border-stone-100 hover:bg-stone-50 transition" data-banner-id="{{ $banner->id_banner }}">
                        <td class="px-4 py-3">
                            <input type="number" value="{{ $banner->orden }}" min="0"
                                data-banner-id="{{ $banner->id_banner }}"
                                class="input-orden w-14 text-center rounded border border-stone-300 px-1 py-1 text-xs focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        </td>
                        <td class="px-4 py-3">
                            <img src="{{ $banner->imagen_url }}" alt="{{ $banner->titulo ?? '' }}"
                                class="w-20 h-12 object-cover rounded-lg border border-stone-200">
                        </td>
                        <td class="px-4 py-3 text-stone-800 font-medium">{{ $banner->titulo ?? '—' }}</td>
                        <td class="px-4 py-3 text-stone-500">{{ Str::limit($banner->subtitulo, 40) ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($banner->activo)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Activo</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-stone-100 text-stone-600">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-stone-500 max-w-40 truncate">{{ $banner->enlace ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" data-banner-id="{{ $banner->id_banner }}"
                                class="btn-editar text-emerald-600 hover:text-emerald-800 text-xs font-medium transition mr-3">
                                Editar
                            </button>
                            <button type="button" data-banner-id="{{ $banner->id_banner }}"
                                class="btn-eliminar text-red-600 hover:text-red-800 text-xs font-medium transition">
                                Eliminar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-sm text-stone-400">
                            No hay banners. Crea el primero.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@vite('resources/js/admin/banners/index.js')


@include('admin.partials.pagination', ['items' => $banners])
