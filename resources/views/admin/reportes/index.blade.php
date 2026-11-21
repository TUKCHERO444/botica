<h1 class="text-xl lg:text-2xl font-bold text-stone-800 mb-6">Reportes</h1>

<div class="bg-white rounded-xl shadow-sm border border-stone-200 p-4 lg:p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-stone-700 mb-1">Tipo de reporte</label>
            <select id="reporte-tipo" name="tipo" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <optgroup label="Ventas">
                    <option value="ventas">Ventas (listado)</option>
                    <option value="ventas_producto">Ventas por producto</option>
                    <option value="ventas_diario">Resumen diario</option>
                    <option value="ventas_vendedor">Por vendedor y hora</option>
                    <option value="anulaciones">Anulaciones</option>
                    <option value="atenciones">Atenciones</option>
                    <option value="servicios">Servicios</option>
                </optgroup>
                <optgroup label="Compras">
                    <option value="ingresos">Compras (listado)</option>
                    <option value="compras_proveedor">Compras por proveedor</option>
                    <option value="costos_producto">Evolución de costos</option>
                    <option value="devoluciones_proveedor">Devoluciones a proveedor</option>
                    <option value="sugerido_compra">Sugerido de compra</option>
                </optgroup>
                <optgroup label="Inventario">
                    <option value="inventario">Inventario</option>
                    <option value="lotes">Lotes y costos</option>
                    <option value="kardex">Kardex</option>
                    <option value="movimientos">Movimientos especiales</option>
                    <option value="merma">Ajustes y merma</option>
                    <option value="rotacion">Rotación y cobertura</option>
                </optgroup>
                <optgroup label="Finanzas">
                    <option value="caja">Caja (listado)</option>
                    <option value="caja_origen">Caja por origen</option>
                    <option value="pagos">Cobros por método de pago</option>
                    <option value="rentabilidad">Rentabilidad por producto</option>
                    <option value="margen_agrupado">Margen agrupado</option>
                </optgroup>
                <optgroup label="Clientes">
                    <option value="clientes">Clientes (top)</option>
                    <option value="clientes_rfm">Clientes RFM</option>
                    <option value="clientes_web">Clientes nuevos y adopción web</option>
                    <option value="cumpleanos">Cumpleaños próximos</option>
                </optgroup>
                <optgroup label="General">
                    <option value="resumen_gerencial">Resumen gerencial</option>
                    <option value="general">Reporte General mensual</option>
                </optgroup>
                <optgroup label="Sistema">
                    <option value="auditoria">Auditoría</option>
                </optgroup>
            </select>
        </div>

        <div class="filtro-fecha" data-reporte-show="ventas,atenciones,ingresos,caja,kardex,clientes,auditoria,general,movimientos,rentabilidad,pagos,ventas_producto,ventas_diario,ventas_vendedor,anulaciones,compras_proveedor,costos_producto,devoluciones_proveedor,merma,margen_agrupado,servicios,clientes_rfm,caja_origen,resumen_gerencial">
            <label class="block text-sm font-medium text-stone-700 mb-1">Desde</label>
            <input type="date" id="reporte-desde" name="desde" value="{{ now()->startOfMonth()->format('Y-m-d') }}"
                class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div class="filtro-fecha" data-reporte-show="ventas,atenciones,ingresos,caja,kardex,clientes,auditoria,general,movimientos,rentabilidad,pagos,ventas_producto,ventas_diario,ventas_vendedor,anulaciones,compras_proveedor,costos_producto,devoluciones_proveedor,merma,margen_agrupado,servicios,clientes_rfm,caja_origen,resumen_gerencial">
            <label class="block text-sm font-medium text-stone-700 mb-1">Hasta</label>
            <input type="date" id="reporte-hasta" name="hasta" value="{{ now()->format('Y-m-d') }}"
                class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="ventas,atenciones,auditoria,ingresos,caja,movimientos,ventas_diario,ventas_vendedor,anulaciones">
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

        <div class="filtro-contextual hidden" data-reporte-show="ingresos,costos_producto,devoluciones_proveedor">
            <label class="block text-sm font-medium text-stone-700 mb-1">Proveedor</label>
            <select id="reporte-proveedor" name="proveedor_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                @foreach ($proveedores as $prov)
                    <option value="{{ $prov->id_proveedor }}">{{ $prov->razon_social }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="ingresos">
            <label class="block text-sm font-medium text-stone-700 mb-1">Forma de pago</label>
            <select id="reporte-pago" name="pago" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todas</option>
                <option value="caja">Pagadas desde caja</option>
                <option value="externo">Pagadas fuera de caja</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="inventario,lotes,rentabilidad,ventas_producto,rotacion,sugerido_compra">
            <label class="block text-sm font-medium text-stone-700 mb-1">Categoría</label>
            <select id="reporte-categoria" name="categoria_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todas</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat->id_categoria }}">{{ $cat->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="inventario,lotes,rentabilidad,ventas_producto,rotacion,sugerido_compra">
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

        <div class="filtro-contextual hidden" data-reporte-show="inventario">
            <label class="block text-sm font-medium text-stone-700 mb-1">Productos</label>
            <select id="reporte-solo-activos" name="solo_activos" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="1">Solo activos</option>
                <option value="0">Todos (incluye inactivos)</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="kardex,costos_producto">
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
                <option value="INGRESO">Ingreso</option>
                <option value="VENTA">Venta</option>
                <option value="ATENCION">Atención</option>
                <option value="AJUSTE">Ajuste</option>
                <option value="BAJA">Baja</option>
                <option value="DEVOLUCION">Devolución</option>
                <option value="REVERSION">Reversión</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="lotes">
            <label class="block text-sm font-medium text-stone-700 mb-1">Vencimiento</label>
            <select id="reporte-dias-vencimiento" name="dias_vencimiento" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                <option value="vencidos">Vencidos con stock</option>
                <option value="30">Próximos 30 días</option>
                <option value="90">Próximos 90 días</option>
                <option value="180">Próximos 180 días</option>
                <option value="365">Próximos 365 días</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="movimientos">
            <label class="block text-sm font-medium text-stone-700 mb-1">Producto</label>
            <select id="reporte-producto-movimiento" name="producto_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                @foreach ($productos as $prod)
                    <option value="{{ $prod->id_producto }}">{{ $prod->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="movimientos">
            <label class="block text-sm font-medium text-stone-700 mb-1">Tipo especial</label>
            <select id="reporte-tipo-especial" name="tipo_especial" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                <option value="REVERSION">Reversión</option>
                <option value="AJUSTE">Ajuste</option>
                <option value="BAJA">Baja</option>
                <option value="DEVOLUCION">Devolución</option>
                <option value="INGRESO">Ingreso</option>
                <option value="VENTA">Venta</option>
                <option value="ATENCION">Atención</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="pagos">
            <label class="block text-sm font-medium text-stone-700 mb-1">Método de pago</label>
            <select id="reporte-metodo-pago" name="metodo_pago_id" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                @foreach ($metodosPago as $metodo)
                    <option value="{{ $metodo->id_metodo_pago }}">{{ $metodo->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="pagos">
            <label class="block text-sm font-medium text-stone-700 mb-1">Origen del cobro</label>
            <select id="reporte-tipo-pago" name="tipo_pago" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Ventas y atenciones</option>
                <option value="venta">Solo ventas</option>
                <option value="atencion">Solo atenciones</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="margen_agrupado">
            <label class="block text-sm font-medium text-stone-700 mb-1">Agrupar por</label>
            <select id="reporte-agrupar" name="agrupar" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="categoria">Categoría</option>
                <option value="marca">Marca</option>
                <option value="vendedor">Vendedor</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="rotacion">
            <label class="block text-sm font-medium text-stone-700 mb-1">Ventana de ventas</label>
            <select id="reporte-dias-rotacion" name="dias_rotacion" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="30">Últimos 30 días</option>
                <option value="60">Últimos 60 días</option>
                <option value="90" selected>Últimos 90 días</option>
                <option value="180">Últimos 180 días</option>
                <option value="365">Últimos 365 días</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="sugerido_compra">
            <label class="block text-sm font-medium text-stone-700 mb-1">Ventana de consumo</label>
            <select id="reporte-dias-compra" name="dias_compra" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="30">Últimos 30 días</option>
                <option value="60">Últimos 60 días</option>
                <option value="90" selected>Últimos 90 días</option>
                <option value="180">Últimos 180 días</option>
                <option value="365">Últimos 365 días</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="cumpleanos">
            <label class="block text-sm font-medium text-stone-700 mb-1">Próximos</label>
            <select id="reporte-dias-cumpleanos" name="dias_cumpleanos" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="7">7 días</option>
                <option value="30" selected>30 días</option>
                <option value="60">60 días</option>
                <option value="90">90 días</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="merma">
            <label class="block text-sm font-medium text-stone-700 mb-1">Tipo de ajuste</label>
            <select id="reporte-tipo-ajuste" name="tipo_ajuste" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                @foreach ($tiposAjuste as $clave => $etiqueta)
                    <option value="{{ $clave }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="clientes_rfm">
            <label class="block text-sm font-medium text-stone-700 mb-1">Segmento</label>
            <select id="reporte-segmento" name="segmento" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todos</option>
                <option value="Nuevo">Nuevo</option>
                <option value="Activo">Activo</option>
                <option value="En riesgo">En riesgo</option>
                <option value="Inactivo">Inactivo</option>
                <option value="Perdido">Perdido</option>
            </select>
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="clientes_rfm">
            <label class="block text-sm font-medium text-stone-700 mb-1">Top N clientes</label>
            <input type="number" id="reporte-limite-rfm" name="limite_rfm" value="100" min="1" max="500"
                class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
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
            <label class="block text-sm font-medium text-stone-700 mb-1">Módulo</label>
            <input type="text" id="reporte-modulo" name="modulo" maxlength="50" placeholder="Ej: ventas"
                class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <div class="filtro-contextual hidden" data-reporte-show="auditoria">
            <label class="block text-sm font-medium text-stone-700 mb-1">Acción</label>
            <select id="reporte-accion" name="accion" class="w-full rounded-lg border-stone-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <option value="">Todas</option>
                <option value="crear">Crear</option>
                <option value="actualizar">Actualizar</option>
                <option value="eliminar">Eliminar</option>
                <option value="baja">Baja</option>
                <option value="toggle-estado">Cambio de estado</option>
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
        <button id="btn-csv" class="px-6 py-2 bg-white border border-stone-300 text-stone-700 rounded-lg hover:bg-stone-50 transition text-sm font-medium">
            Descargar CSV
        </button>
    </div>
</div>

<div id="reporte-preview" class="hidden"></div>

@vite('resources/js/admin/reportes/index.js')
