<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl lg:text-2xl font-bold text-stone-800">Historial de atenciones</h1>
        <p class="text-sm text-stone-500 mt-1">Listado completo de atenciones, con filtros por fecha y usuario.</p>
    </div>
    <a href="{{ route('admin.atenciones.index') }}" data-panel="{{ route('admin.atenciones.index') }}"
        class="text-sm text-stone-600 hover:text-stone-800 px-3 py-2 rounded-lg border border-stone-300 hover:border-stone-400 transition self-start sm:self-auto">
        ← Volver a mis atenciones de hoy
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 mb-4">
    <div class="flex flex-wrap items-center gap-2 mb-4 text-xs">
        <span class="text-stone-500">Rápido:</span>
        <a href="{{ route('admin.atenciones.historial', ['fecha' => today()->toDateString()]) }}"
            data-panel="{{ route('admin.atenciones.historial', ['fecha' => today()->toDateString()]) }}"
            class="px-2.5 py-1 rounded-full border border-stone-300 text-stone-600 hover:bg-stone-50 transition">Hoy</a>
        <a href="{{ route('admin.atenciones.historial', ['fecha' => today()->subDay()->toDateString()]) }}"
            data-panel="{{ route('admin.atenciones.historial', ['fecha' => today()->subDay()->toDateString()]) }}"
            class="px-2.5 py-1 rounded-full border border-stone-300 text-stone-600 hover:bg-stone-50 transition">Ayer</a>
        <a href="{{ route('admin.atenciones.historial', ['desde' => today()->subDays(6)->toDateString(), 'hasta' => today()->toDateString()]) }}"
            data-panel="{{ route('admin.atenciones.historial', ['desde' => today()->subDays(6)->toDateString(), 'hasta' => today()->toDateString()]) }}"
            class="px-2.5 py-1 rounded-full border border-stone-300 text-stone-600 hover:bg-stone-50 transition">Últimos 7 días</a>
        <a href="{{ route('admin.atenciones.historial', ['desde' => today()->startOfMonth()->toDateString(), 'hasta' => today()->toDateString()]) }}"
            data-panel="{{ route('admin.atenciones.historial', ['desde' => today()->startOfMonth()->toDateString(), 'hasta' => today()->toDateString()]) }}"
            class="px-2.5 py-1 rounded-full border border-stone-300 text-stone-600 hover:bg-stone-50 transition">Este mes</a>
        <a href="{{ route('admin.atenciones.historial') }}" data-panel="{{ route('admin.atenciones.historial') }}"
            class="px-2.5 py-1 rounded-full border border-stone-300 text-stone-600 hover:bg-stone-50 transition">Todo</a>
    </div>

    <form id="atenciones-historial-filtros" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3" autocomplete="off">
        <div>
            <label for="atenciones-fecha" class="block text-xs font-medium text-stone-600 mb-1">Fecha</label>
            <input type="date" id="atenciones-fecha" name="fecha" value="{{ $filtros['fecha']?->format('Y-m-d') }}"
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label for="atenciones-desde" class="block text-xs font-medium text-stone-600 mb-1">Desde</label>
            <input type="date" id="atenciones-desde" name="desde" value="{{ $filtros['desde']?->format('Y-m-d') }}"
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label for="atenciones-hasta" class="block text-xs font-medium text-stone-600 mb-1">Hasta</label>
            <input type="date" id="atenciones-hasta" name="hasta" value="{{ $filtros['hasta']?->format('Y-m-d') }}"
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div>
            <label for="atenciones-usuario" class="block text-xs font-medium text-stone-600 mb-1">Usuario</label>
            <select id="atenciones-usuario" name="user_id"
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Todos</option>
                @foreach ($usuarios as $u)
                    <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="atenciones-estado" class="block text-xs font-medium text-stone-600 mb-1">Estado</label>
            <select id="atenciones-estado" name="estado"
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="">Todos</option>
                <option value="confirmada" @selected(request('estado') === 'confirmada')>Confirmadas</option>
                <option value="anulada" @selected(request('estado') === 'anulada')>Anuladas</option>
            </select>
        </div>
        <div>
            <label for="atenciones-q" class="block text-xs font-medium text-stone-600 mb-1">Buscar</label>
            <input type="text" id="atenciones-q" name="q" value="{{ request('q') }}" placeholder="Correlativo o cliente"
                class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        </div>
        <div class="lg:col-span-6 flex flex-wrap items-center gap-2">
            <button type="submit"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm transition">Filtrar</button>
            <a href="{{ route('admin.atenciones.historial') }}" data-panel="{{ route('admin.atenciones.historial') }}"
                class="px-4 py-2 bg-stone-200 hover:bg-stone-300 text-stone-700 rounded-lg text-sm transition">Limpiar</a>
            <span class="text-xs text-stone-500">
                Si indicas una fecha exacta, se ignora el rango.
            </span>
        </div>
    </form>
</div>

<div id="atenciones-historial-tabla">
    <div class="bg-white rounded-xl shadow-sm border border-stone-200 px-4 py-3 mb-4 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
        <span class="text-stone-600">
            Resultado: <strong class="text-stone-800">{{ (int) $resumen->cantidad }}</strong>
            {{ (int) $resumen->cantidad === 1 ? 'atención confirmada' : 'atenciones confirmadas' }}
        </span>
        <span class="text-stone-600">
            Total: <strong class="text-stone-800">S/ {{ number_format((float) $resumen->total, 2) }}</strong>
        </span>
    </div>

    @include('admin.atenciones._tabla', ['atenciones' => $atenciones, 'modo' => 'historial'])
</div>

@vite('resources/js/admin/atenciones/historial.js')
