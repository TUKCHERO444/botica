<div class="max-w-3xl">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-stone-800 lg:text-2xl">Datos del sistema</h1>
        <p class="mt-1 text-sm text-stone-500">
            Información general del negocio que se muestra en el panel y en los documentos.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <form id="datos-sistema-form" action="{{ route('admin.datos-sistema.update') }}" method="POST"
            class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 9h.01M15 9h.01M9 12h.01M15 12h.01"/>
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <label for="nombre_negocio" class="mb-1 block text-sm font-medium text-stone-700">
                        Nombre de la empresa o negocio <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nombre_negocio" name="nombre_negocio" required maxlength="100"
                        value="{{ old('nombre_negocio', $configuracion->nombre_negocio) }}"
                        placeholder="Ej.: Botica San José"
                        class="w-full rounded-lg border border-stone-300 px-4 py-2 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500 outline-none">
                    <p class="mt-1 text-xs text-stone-500">
                        Reemplaza el texto del sidebar y la cabecera de los tickets.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-end border-t border-stone-200 pt-4">
                <button type="submit"
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white transition hover:bg-emerald-700">
                    Guardar cambios
                </button>
            </div>
        </form>

        <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Vista previa</p>

            <div class="mt-3 rounded-lg bg-emerald-800 px-3 py-2 text-white">
                <p class="flex items-center gap-2 text-sm font-bold">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 8h6"/>
                    </svg>
                    <span data-brand-preview data-brand-name class="truncate">
                        {{ $configuracion->nombre_negocio }}
                    </span>
                </p>
            </div>
            <p class="mt-2 text-xs text-stone-400">Sidebar del panel administrativo.</p>

            <div class="mt-4 rounded-lg border border-dashed border-stone-300 bg-stone-50 px-3 py-2 text-center">
                <p class="text-sm font-bold text-stone-800" data-brand-name>{{ $configuracion->nombre_negocio }}</p>
                <p class="text-[11px] uppercase tracking-wide text-stone-400">Cabecera del ticket</p>
            </div>
        </div>
    </div>
</div>

@vite('resources/js/admin/datos-sistema/index.js')
