<h1 class="text-xl lg:text-2xl font-bold text-stone-800 mb-6">Reportes</h1>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Tipo de reporte</label>
            <select id="reporte-tipo" name="tipo" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <optgroup label="Operaciones">
                    <option value="ventas">Ventas</option>
                    <option value="atenciones">Atenciones</option>
                    <option value="ingresos">Ingresos</option>
                </optgroup>
                <optgroup label="Inventario">
                    <option value="inventario">Inventario</option>
                    <option value="kardex">Kardex</option>
                    <option value="vencimientos">Vencimientos</option>
                </optgroup>
                <optgroup label="Finanzas">
                    <option value="caja">Caja</option>
                </optgroup>
                <optgroup label="Clientes">
                    <option value="clientes">Clientes</option>
                </optgroup>
                <optgroup label="General">
                    <option value="general">Reporte General</option>
                </optgroup>
                <optgroup label="Sistema">
                    <option value="auditoria">Auditoría</option>
                </optgroup>
            </select>
        </div>

        <div class="filtro-fecha" data-reporte-show="ventas,atenciones,ingresos,caja,kardex,clientes,auditoria,general">
            <label class="block text-sm font-medium text-stone-700 mb-1">Desde</label>
            <input type="date" id="reporte-desde" name="desde" value="{{ now()->startOfMonth()->format('Y-m-d') }}"
                class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div class="filtro-fecha" data-reporte-show="ventas,atenciones,ingresos,caja,kardex,clientes,auditoria,general">
            <label class="block text-sm font-medium text-stone-700 mb-1">Hasta</label>
            <input type="date" id="reporte-hasta" name="hasta" value="{{ now()->format('Y-m-d') }}"
                class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="ventas,atenciones,auditoria,ingresos,caja">
            <label class="block text-sm font-medium text-stone-700 mb-1" id="filtro-usuario-label">Usuario</label>
            <select id="reporte-usuario" name="usuario_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                @foreach ($usuarios as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
        <div class="filtro-contextual hidden" data-reporte-show="ventas,atenciones">
            <label class="block text-sm font-medium text-stone-700 mb-1">Cliente</label>
            <select id="reporte-cliente" name="cliente_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                @foreach ($clientes as $cli)
                    <option value="{{ $cli->id_cliente }}">{{ $cli->nombre }} {{ $cli->apellido_paterno }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="ingresos">
            <label class="block text-sm font-medium text-stone-700 mb-1">Proveedor</label>
            <select id="reporte-proveedor" name="proveedor_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                @foreach ($proveedores as $prov)
                    <option value="{{ $prov->id_proveedor }}">{{ $prov->razon_social }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="inventario">
            <label class="block text-sm font-medium text-stone-700 mb-1">Categoría</label>
            <select id="reporte-categoria" name="categoria_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todas</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat->id_categoria }}">{{ $cat->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="inventario">
            <label class="block text-sm font-medium text-stone-700 mb-1">Marca</label>
            <select id="reporte-marca" name="marca_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todas</option>
                @foreach ($marcas as $marca)
                    <option value="{{ $marca->id_marca }}">{{ $marca->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
        <div class="filtro-contextual hidden" data-reporte-show="inventario">
            <label class="block text-sm font-medium text-stone-700 mb-1">Stock menor o igual a</label>
            <input type="number" id="reporte-stock-menor" name="stock_menor_que" min="0" placeholder="Ej: 10"
                class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="kardex">
            <label class="block text-sm font-medium text-stone-700 mb-1">Producto</label>
            <select id="reporte-producto" name="producto_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Seleccione</option>
                @foreach ($productos as $prod)
                    <option value="{{ $prod->id_producto }}">{{ $prod->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="kardex">
            <label class="block text-sm font-medium text-stone-700 mb-1">Tipo movimiento</label>
            <select id="reporte-tipo-movimiento" name="tipo_movimiento" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                <option value="entrada">Entrada</option>
                <option value="salida">Salida</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="vencimientos">
            <label class="block text-sm font-medium text-stone-700 mb-1">Producto</label>
            <select id="reporte-producto-vencimiento" name="producto_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                @foreach ($productos as $prod)
                    <option value="{{ $prod->id_producto }}">{{ $prod->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
        <div class="filtro-contextual hidden" data-reporte-show="vencimientos">
            <label class="block text-sm font-medium text-stone-700 mb-1">Rango de días (próximos)</label>
            <input type="number" id="reporte-dias-vencimiento" name="dias_vencimiento" value="30" min="1"
                class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="clientes">
            <label class="block text-sm font-medium text-stone-700 mb-1">Top N clientes</label>
            <input type="number" id="reporte-limite-clientes" name="limite_clientes" value="20" min="1" max="100"
                class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="general">
            <label class="block text-sm font-medium text-stone-700 mb-1">Mes</label>
            <select id="reporte-mes" name="mes" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @foreach (range(1, 12) as $m)
                    <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create()->month($m)->isoFormat('MMMM') }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="general">
            <label class="block text-sm font-medium text-stone-700 mb-1">Año</label>
            <select id="reporte-anio" name="anio" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @foreach (range(now()->year - 2, now()->year + 1) as $y)
                    <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="auditoria">
            <label class="block text-sm font-medium text-stone-700 mb-1">Acción</label>
            <select id="reporte-accion" name="accion" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todas</option>
                <option value="crear">Crear</option>
                <option value="actualizar">Actualizar</option>
                <option value="eliminar">Eliminar</option>
            </select>
        </div>
    </div>

    <div class="flex items-center gap-3 mt-6 pt-4 border-t border-stone-200">
        <button id="btn-preview" class="px-6 py-2 bg-white border border-emerald-600 text-emerald-700 rounded-lg hover:bg-emerald-50 transition text-sm font-medium">
            Vista previa
        </button>
        <button id="btn-generate" class="px-6 py-2 bg-emerald-700 text-white rounded-lg hover:bg-emerald-800 transition text-sm font-medium">
            Generar PDF
        </button>
    </div>
</div>

<div id="reporte-preview" class="hidden"></div>

@vite('resources/js/admin/reportes/index.js')
