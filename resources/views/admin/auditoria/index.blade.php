<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-stone-800">Auditoría</h1>
</div>

{{-- Filtros --}}
<form id="auditoria-filters" class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3" autocomplete="off">
    <select name="modulo" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        <option value="">Todos los módulos</option>
        @foreach ($modulos as $m)
            <option value="{{ $m }}" {{ request('modulo') === $m ? 'selected' : '' }}>{{ ucfirst($m) }}</option>
        @endforeach
    </select>

    <select name="user_id" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        <option value="">Todos los usuarios</option>
        @foreach ($usuarios as $u)
            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
        @endforeach
    </select>

    <select name="accion" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        <option value="">Todas las acciones</option>
        <option value="crear" {{ request('accion') === 'crear' ? 'selected' : '' }}>Crear</option>
        <option value="actualizar" {{ request('accion') === 'actualizar' ? 'selected' : '' }}>Actualizar</option>
        <option value="eliminar" {{ request('accion') === 'eliminar' ? 'selected' : '' }}>Eliminar</option>
        <option value="toggle-estado" {{ request('accion') === 'toggle-estado' ? 'selected' : '' }}>Toggle estado</option>
    </select>

    <input type="date" name="desde" value="{{ request('desde') }}" placeholder="Desde"
        class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">

    <input type="date" name="hasta" value="{{ request('hasta') }}" placeholder="Hasta"
        class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">

    <div class="lg:col-span-5 flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar en descripción..."
            class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm transition">Filtrar</button>
        <a href="{{ route('admin.auditoria.index') }}" class="px-4 py-2 bg-stone-200 hover:bg-stone-300 text-stone-700 rounded-lg text-sm transition">Limpiar</a>
    </div>
</form>

{{-- Tabla --}}
<div id="auditoria-table">
    @include('admin.auditoria._table')
</div>

@vite('resources/js/admin/auditoria/index.js')
