<?php

namespace App\Services;

use App\Models\AjusteInventario;
use App\Models\Atencion;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\DevolucionProveedor;
use App\Models\Ingreso;
use App\Models\KardexMovimiento;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReporteService
{
    public function ventas($desde, $hasta, $usuarioId = null, $clienteId = null)
    {
        $query = Venta::with('cliente', 'user', 'productos.unidadBase')
            ->where('estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$desde, Carbon::parse($hasta)->endOfDay()]);

        if ($usuarioId) {
            $query->where('user_id', $usuarioId);
        }
        if ($clienteId) {
            $query->where('cliente_id_cliente', $clienteId);
        }

        $ventas = $query->orderBy('created_at')->get();

        return [
            'ventas' => $ventas,
            'total_general' => $ventas->sum('total'),
            'cantidad' => $ventas->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function atenciones($desde, $hasta, $usuarioId = null, $clienteId = null)
    {
        $query = Atencion::with('cliente', 'user', 'productos.unidadBase', 'servicios')
            ->where('estado', Atencion::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$desde, Carbon::parse($hasta)->endOfDay()]);

        if ($usuarioId) {
            $query->where('user_id', $usuarioId);
        }
        if ($clienteId) {
            $query->where('cliente_id_cliente', $clienteId);
        }

        $atenciones = $query->orderBy('created_at')->get();

        return [
            'atenciones' => $atenciones,
            'total_general' => $atenciones->sum('total'),
            'cantidad' => $atenciones->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function ingresos($desde, $hasta, $proveedorId = null, $usuarioId = null, $pago = null)
    {
        $query = Ingreso::with('proveedor', 'user', 'detalles.producto.unidadBase', 'detalles.presentacion')
            ->where('estado', 'recibida')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta);

        if ($proveedorId) {
            $query->where('proveedor_id_proveedor', $proveedorId);
        }
        if ($usuarioId) {
            $query->where('user_id', $usuarioId);
        }
        if ($pago === 'caja') {
            $query->where('pagado_desde_caja', true);
        }
        if ($pago === 'externo') {
            $query->where('pagado_desde_caja', false);
        }

        $ingresos = $query->orderBy('fecha')->get();

        $totalProductos = 0;
        $totalCompras = 0;
        $totalCaja = 0;
        $totalExterno = 0;
        $cantidadCaja = 0;
        $cantidadExterno = 0;

        foreach ($ingresos as $ingreso) {
            $totalProductos += $ingreso->detalles->sum('cantidad_base');
            $total = (float) $ingreso->total;
            $totalCompras += $total;

            if ($ingreso->pagado_desde_caja) {
                $totalCaja += $total;
                $cantidadCaja++;
            } else {
                $totalExterno += $total;
                $cantidadExterno++;
            }
        }

        $devolucionesQuery = DevolucionProveedor::with('proveedor', 'user', 'detalles')
            ->where('estado', DevolucionProveedor::ESTADO_REGISTRADA)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta);

        if ($proveedorId) {
            $devolucionesQuery->where('proveedor_id_proveedor', $proveedorId);
        }
        if ($usuarioId) {
            $devolucionesQuery->where('user_id', $usuarioId);
        }

        $devoluciones = $devolucionesQuery->orderBy('fecha')->get();
        $totalDevoluciones = (float) $devoluciones->sum('total');

        $anuladasQuery = Ingreso::where('estado', Ingreso::ESTADO_ANULADA)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta);

        if ($proveedorId) {
            $anuladasQuery->where('proveedor_id_proveedor', $proveedorId);
        }
        if ($usuarioId) {
            $anuladasQuery->where('user_id', $usuarioId);
        }
        if ($pago === 'caja') {
            $anuladasQuery->where('pagado_desde_caja', true);
        }
        if ($pago === 'externo') {
            $anuladasQuery->where('pagado_desde_caja', false);
        }

        $anuladas = $anuladasQuery->orderBy('fecha')->get();
        $totalAnuladas = (float) $anuladas->sum('total');

        return [
            'ingresos' => $ingresos,
            'devoluciones' => $devoluciones,
            'anuladas' => $anuladas,
            'total_productos' => $totalProductos,
            'cantidad' => $ingresos->count(),
            'total_compras' => round($totalCompras, 2),
            'total_devoluciones' => round($totalDevoluciones, 2),
            'total_anuladas' => round($totalAnuladas, 2),
            'total_neto' => round($totalCompras - $totalDevoluciones - $totalAnuladas, 2),
            'cantidad_devoluciones' => $devoluciones->count(),
            'cantidad_anuladas' => $anuladas->count(),
            'total_caja' => round($totalCaja, 2),
            'total_externo' => round($totalExterno, 2),
            'cantidad_caja' => $cantidadCaja,
            'cantidad_externo' => $cantidadExterno,
            'pago' => $pago,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function caja($cajaId = null, $desde = null, $hasta = null)
    {
        $query = Caja::with('aperturaUser', 'cierreUser', 'arqueoUser', 'movimientos.user', 'movimientos.metodoPago');

        if ($cajaId) {
            $query->where('id_caja', $cajaId);
        } elseif ($desde && $hasta) {
            $query->whereBetween('apertura_at', [$desde, Carbon::parse($hasta)->endOfDay()]);
        }

        $cajas = $query->orderByDesc('apertura_at')->get();

        return [
            'cajas' => $cajas,
            'cantidad' => $cajas->count(),
        ];
    }

    public function inventario($categoriaId = null, $marcaId = null, $stockMenorQue = null, $soloActivos = true)
    {
        // Valor real a costo de capas; el reporte de lotes usa el mismo criterio.
        $valorCapas = DB::table('producto_lote as pl')
            ->join('lote_ingreso as li', 'li.producto_lote_id', '=', 'pl.id')
            ->where('pl.estado', '!=', 'baja')
            ->where('li.cantidad_disponible_base', '>', 0)
            ->select('pl.producto_id', DB::raw('SUM(li.cantidad_disponible_base * li.costo_unitario_base) as valor_costo'))
            ->groupBy('pl.producto_id');

        $query = Producto::query()
            ->with('categoria', 'marca', 'unidadBase')
            ->leftJoinSub($valorCapas, 'valor', fn ($join) => $join->on('valor.producto_id', '=', 'producto.id_producto'))
            ->select('producto.*', DB::raw('COALESCE(valor.valor_costo, 0) as valor_costo'));

        if ($soloActivos) {
            $query->where('producto.estado', 1);
        }
        if ($categoriaId) {
            $query->where('producto.categoria_id_categoria', $categoriaId);
        }
        if ($marcaId) {
            $query->where('producto.marca_id_marca', $marcaId);
        }
        if ($stockMenorQue !== null && $stockMenorQue !== '') {
            $query->where('producto.stock', '<=', $stockMenorQue);
        }

        $productos = $query->orderBy('producto.nombre')->get();

        $totalValorizado = $productos->sum(fn ($p) => (float) $p->valor_costo);
        $totalValorCompra = $productos->sum(fn ($p) => (float) $p->stock * (float) $p->precio_compra);
        $totalStock = $productos->sum(fn ($p) => (float) $p->stock);

        return [
            'productos' => $productos,
            'total_valorizado' => $totalValorizado,
            'total_valor_compra' => $totalValorCompra,
            'total_stock' => $totalStock,
            'cantidad' => $productos->count(),
            'solo_activos' => $soloActivos,
        ];
    }

    public function rentabilidad($desde, $hasta, $categoriaId = null, $marcaId = null)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $ventas = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id_venta', '=', 'dv.venta_id_venta')
            ->join('producto as p', 'p.id_producto', '=', 'dv.producto_id_producto')
            ->where('v.estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('v.created_at', [$desde, $finDelDia])
            ->when($categoriaId, fn ($q) => $q->where('p.categoria_id_categoria', $categoriaId))
            ->when($marcaId, fn ($q) => $q->where('p.marca_id_marca', $marcaId))
            ->select(
                'p.id_producto',
                'p.nombre',
                DB::raw('SUM(dv.cantidad_base) as cantidad_base'),
                DB::raw('SUM(dv.sub_total) as ingresos'),
                DB::raw('SUM(COALESCE(dv.costo_total, 0)) as costo')
            )
            ->groupBy('p.id_producto', 'p.nombre')
            ->get();

        $atenciones = DB::table('atencion_producto as ap')
            ->join('atencion as a', 'a.id_atencion', '=', 'ap.atencion_id_atencion')
            ->join('producto as p', 'p.id_producto', '=', 'ap.producto_id_producto')
            ->where('a.estado', Atencion::ESTADO_CONFIRMADA)
            ->whereBetween('a.created_at', [$desde, $finDelDia])
            ->when($categoriaId, fn ($q) => $q->where('p.categoria_id_categoria', $categoriaId))
            ->when($marcaId, fn ($q) => $q->where('p.marca_id_marca', $marcaId))
            ->select(
                'p.id_producto',
                'p.nombre',
                DB::raw('SUM(ap.cantidad_base) as cantidad_base'),
                DB::raw('SUM(ap.sub_total) as ingresos'),
                DB::raw('SUM(COALESCE(ap.costo_total, 0)) as costo')
            )
            ->groupBy('p.id_producto', 'p.nombre')
            ->get();

        $productos = [];

        foreach ([$ventas, $atenciones] as $filas) {
            foreach ($filas as $fila) {
                $id = $fila->id_producto;

                if (! isset($productos[$id])) {
                    $productos[$id] = (object) [
                        'id_producto' => $id,
                        'nombre' => $fila->nombre,
                        'cantidad_base' => 0,
                        'ingresos' => 0,
                        'costo' => 0,
                    ];
                }

                $productos[$id]->cantidad_base += (float) $fila->cantidad_base;
                $productos[$id]->ingresos += (float) $fila->ingresos;
                $productos[$id]->costo += (float) $fila->costo;
            }
        }

        $productos = collect($productos)
            ->map(function ($producto) {
                $producto->margen = $producto->ingresos - $producto->costo;
                $producto->margen_pct = $producto->ingresos > 0
                    ? ($producto->margen / $producto->ingresos) * 100
                    : 0;

                return $producto;
            })
            ->sortByDesc('margen')
            ->values();

        return [
            'productos' => $productos,
            'total_ingresos' => round($productos->sum('ingresos'), 2),
            'total_costo' => round($productos->sum('costo'), 2),
            'total_margen' => round($productos->sum('margen'), 2),
            'cantidad' => $productos->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function lotes($categoriaId = null, $marcaId = null, $diasVencimiento = null)
    {
        $query = ProductoLote::with('producto.categoria', 'producto.marca', 'producto.unidadBase', 'capas')
            ->where('cantidad_disponible_base', '>', 0)
            ->where('estado', '!=', 'baja');

        if ($categoriaId) {
            $query->whereHas('producto', fn ($q) => $q->where('categoria_id_categoria', $categoriaId));
        }
        if ($marcaId) {
            $query->whereHas('producto', fn ($q) => $q->where('marca_id_marca', $marcaId));
        }
        if ($diasVencimiento === 'vencidos') {
            $query->whereDate('fecha_vencimiento', '<', today());
        } elseif ($diasVencimiento) {
            $query->whereDate('fecha_vencimiento', '>=', today())
                ->whereDate('fecha_vencimiento', '<=', today()->addDays((int) $diasVencimiento));
        }

        $lotes = $query->orderBy('fecha_vencimiento')->orderBy('id')->get();

        foreach ($lotes as $lote) {
            $capas = $lote->capas->where('cantidad_disponible_base', '>', 0);
            $valor = $capas->sum(fn ($capa) => (float) $capa->cantidad_disponible_base * (float) $capa->costo_unitario_base);

            $lote->valor_disponible = $valor;
            $lote->costo_promedio = (float) $lote->cantidad_disponible_base > 0
                ? $valor / (float) $lote->cantidad_disponible_base
                : 0;
        }

        $totalValorVencido = $lotes
            ->filter(fn ($lote) => $lote->dias_para_vencer < 0)
            ->sum('valor_disponible');
        $totalValorRiesgo = $lotes
            ->filter(fn ($lote) => $lote->dias_para_vencer >= 0 && $lote->dias_para_vencer <= 90)
            ->sum('valor_disponible');

        return [
            'lotes' => $lotes,
            'total_lotes' => $lotes->count(),
            'total_disponible' => $lotes->sum('cantidad_disponible_base'),
            'total_valor' => round($lotes->sum('valor_disponible'), 2),
            'total_valor_vencido' => round($totalValorVencido, 2),
            'total_valor_riesgo' => round($totalValorRiesgo, 2),
            'dias_vencimiento' => $diasVencimiento,
        ];
    }

    public function movimientos($desde, $hasta, $tipo = null, $productoId = null, $usuarioId = null)
    {
        $query = KardexMovimiento::with('producto.unidadBase', 'usuario', 'productoPresentacion')
            ->whereBetween('created_at', [$desde, Carbon::parse($hasta)->endOfDay()]);

        if ($tipo) {
            $query->where('tipo_movimiento', $tipo);
        }
        if ($productoId) {
            $query->where('producto_id', $productoId);
        }
        if ($usuarioId) {
            $query->where('usuario_id', $usuarioId);
        }

        $movimientos = $query->orderBy('created_at')->get();

        $entradas = $movimientos->where('cantidad', '>', 0);
        $salidas = $movimientos->where('cantidad', '<', 0);

        return [
            'movimientos' => $movimientos,
            'cantidad' => $movimientos->count(),
            'cantidad_entradas' => $entradas->count(),
            'cantidad_salidas' => $salidas->count(),
            'total_entradas' => $entradas->sum('cantidad'),
            'total_salidas' => $salidas->sum('cantidad'),
            'total_costo' => round($movimientos->sum(
                fn ($movimiento) => abs((float) $movimiento->cantidad) * (float) ($movimiento->costo_unitario_base ?? 0)
            ), 2),
            'tipo' => $tipo,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function kardex($productoId, $desde = null, $hasta = null, $tipo = null)
    {
        $query = KardexMovimiento::with('producto.unidadBase', 'usuario')
            ->where('producto_id', $productoId);

        if ($desde) {
            $query->where('created_at', '>=', $desde);
        }
        if ($hasta) {
            $query->where('created_at', '<=', Carbon::parse($hasta)->endOfDay());
        }
        if ($tipo) {
            $query->where('tipo_movimiento', $tipo);
        }

        $movimientos = $query->orderBy('created_at')->get();

        $producto = Producto::find($productoId);

        return [
            'movimientos' => $movimientos,
            'producto' => $producto,
            'cantidad' => $movimientos->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function clientes($desde, $hasta, $limite = 20)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();
        $limite = max(1, min(100, (int) $limite));

        // Dos subconsultas para evitar el producto cartesiano de unir venta y atención a la vez.
        $ventas = DB::table('venta')
            ->select(
                'cliente_id_cliente',
                DB::raw('SUM(total) as total_ventas'),
                DB::raw('COUNT(*) as cantidad_ventas')
            )
            ->where('estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$desde, $finDelDia])
            ->groupBy('cliente_id_cliente');

        $atenciones = DB::table('atencion')
            ->select(
                'cliente_id_cliente',
                DB::raw('SUM(total) as total_atenciones'),
                DB::raw('COUNT(*) as cantidad_atenciones')
            )
            ->where('estado', Atencion::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$desde, $finDelDia])
            ->groupBy('cliente_id_cliente');

        $topClientes = Cliente::query()
            ->leftJoinSub($ventas, 'v', fn ($join) => $join->on('v.cliente_id_cliente', '=', 'cliente.id_cliente'))
            ->leftJoinSub($atenciones, 'a', fn ($join) => $join->on('a.cliente_id_cliente', '=', 'cliente.id_cliente'))
            ->select([
                'cliente.id_cliente',
                'cliente.dni',
                'cliente.nombre',
                'cliente.apellido_paterno',
                'cliente.apellido_materno',
                DB::raw('COALESCE(v.total_ventas, 0) + COALESCE(a.total_atenciones, 0) as total_gastado'),
                DB::raw('COALESCE(v.cantidad_ventas, 0) + COALESCE(a.cantidad_atenciones, 0) as total_transacciones'),
            ])
            ->whereRaw('(COALESCE(v.total_ventas, 0) + COALESCE(a.total_atenciones, 0)) > 0')
            ->orderByDesc('total_gastado')
            ->limit($limite)
            ->get();

        return [
            'clientes' => $topClientes,
            'limite' => $limite,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function pagos($desde, $hasta, $metodoPagoId = null, $tipo = null)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $query = DB::table('pago')
            ->join('metodo_pago', 'metodo_pago.id_metodo_pago', '=', 'pago.metodo_pago_id')
            ->leftJoin('venta', function ($join) {
                $join->on('venta.id_venta', '=', 'pago.referencia_id')
                    ->where('pago.referencia_tipo', Pago::TIPO_VENTA);
            })
            ->leftJoin('atencion', function ($join) {
                $join->on('atencion.id_atencion', '=', 'pago.referencia_id')
                    ->where('pago.referencia_tipo', Pago::TIPO_ATENCION);
            })
            ->where(function ($query) use ($desde, $finDelDia) {
                $query->whereBetween('venta.created_at', [$desde, $finDelDia])
                    ->orWhereBetween('atencion.created_at', [$desde, $finDelDia]);
            })
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('pago.referencia_tipo', Pago::TIPO_VENTA)
                        ->where('venta.estado', Venta::ESTADO_CONFIRMADA);
                })->orWhere(function ($query) {
                    $query->where('pago.referencia_tipo', Pago::TIPO_ATENCION)
                        ->where('atencion.estado', Atencion::ESTADO_CONFIRMADA);
                });
            });

        if ($metodoPagoId) {
            $query->where('pago.metodo_pago_id', $metodoPagoId);
        }
        if ($tipo === 'venta') {
            $query->where('pago.referencia_tipo', Pago::TIPO_VENTA);
        }
        if ($tipo === 'atencion') {
            $query->where('pago.referencia_tipo', Pago::TIPO_ATENCION);
        }

        $metodos = $query
            ->select(
                'metodo_pago.id_metodo_pago',
                'metodo_pago.nombre',
                DB::raw("SUM(CASE WHEN pago.referencia_tipo = 'Venta' THEN pago.monto ELSE 0 END) as total_ventas"),
                DB::raw("SUM(CASE WHEN pago.referencia_tipo = 'Atencion' THEN pago.monto ELSE 0 END) as total_atenciones"),
                DB::raw('SUM(pago.monto) as total'),
                DB::raw('COUNT(*) as pagos')
            )
            ->groupBy('metodo_pago.id_metodo_pago', 'metodo_pago.nombre')
            ->orderByDesc('total')
            ->get();

        return [
            'metodos' => $metodos,
            'total' => round((float) $metodos->sum('total'), 2),
            'total_ventas' => round((float) $metodos->sum('total_ventas'), 2),
            'total_atenciones' => round((float) $metodos->sum('total_atenciones'), 2),
            'cantidad_pagos' => (int) $metodos->sum('pagos'),
            'tipo' => $tipo,
            'metodo_pago_id' => $metodoPagoId,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function ventasProducto($desde, $hasta, $categoriaId = null, $marcaId = null)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $productos = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id_venta', '=', 'dv.venta_id_venta')
            ->join('producto as p', 'p.id_producto', '=', 'dv.producto_id_producto')
            ->leftJoin('producto_presentacion as pp', 'pp.id_producto_presentacion', '=', 'dv.producto_presentacion_id')
            ->leftJoin('presentacion as pr', 'pr.id_presentacion', '=', 'pp.presentacion_id')
            ->where('v.estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('v.created_at', [$desde, $finDelDia])
            ->when($categoriaId, fn ($q) => $q->where('p.categoria_id_categoria', $categoriaId))
            ->when($marcaId, fn ($q) => $q->where('p.marca_id_marca', $marcaId))
            ->select(
                'p.id_producto',
                'p.nombre as producto',
                DB::raw("COALESCE(pp.nombre, pr.nombre, 'Unidad') as presentacion"),
                DB::raw('SUM(dv.cantidad_base) as cantidad_base'),
                DB::raw('SUM(dv.sub_total) as ingresos'),
                DB::raw('SUM(COALESCE(dv.costo_total, 0)) as costo')
            )
            ->groupBy('p.id_producto', 'p.nombre', 'pp.nombre', 'pr.nombre')
            ->orderByDesc('ingresos')
            ->get();

        $totalIngresos = (float) $productos->sum('ingresos');
        $totalCosto = (float) $productos->sum('costo');

        foreach ($productos as $producto) {
            $producto->margen = (float) $producto->ingresos - (float) $producto->costo;
            $producto->margen_pct = (float) $producto->ingresos > 0
                ? ($producto->margen / (float) $producto->ingresos) * 100
                : 0;
            $producto->participacion = $totalIngresos > 0
                ? ((float) $producto->ingresos / $totalIngresos) * 100
                : 0;
        }

        return [
            'productos' => $productos,
            'total_ingresos' => round($totalIngresos, 2),
            'total_costo' => round($totalCosto, 2),
            'total_margen' => round($totalIngresos - $totalCosto, 2),
            'cantidad' => $productos->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function ventasDiario($desde, $hasta, $usuarioId = null)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $ventas = DB::table('venta')
            ->where('estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$desde, $finDelDia])
            ->when($usuarioId, fn ($q) => $q->where('user_id', $usuarioId))
            ->select(
                DB::raw('DATE(created_at) as fecha'),
                DB::raw('COUNT(*) as tickets'),
                DB::raw('SUM(total) as ingresos')
            )
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('fecha');

        $detalles = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id_venta', '=', 'dv.venta_id_venta')
            ->where('v.estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('v.created_at', [$desde, $finDelDia])
            ->when($usuarioId, fn ($q) => $q->where('v.user_id', $usuarioId))
            ->select(
                DB::raw('DATE(v.created_at) as fecha'),
                DB::raw('SUM(dv.cantidad_base) as unidades'),
                DB::raw('SUM(COALESCE(dv.costo_total, 0)) as costo')
            )
            ->groupByRaw('DATE(v.created_at)')
            ->get()
            ->keyBy('fecha');

        $fechas = collect($ventas->keys())->merge($detalles->keys())->unique()->sort()->values();

        $dias = $fechas->map(function ($fecha) use ($ventas, $detalles) {
            $venta = $ventas->get($fecha);
            $detalle = $detalles->get($fecha);
            $tickets = (int) ($venta->tickets ?? 0);
            $ingresos = (float) ($venta->ingresos ?? 0);
            $costo = (float) ($detalle->costo ?? 0);

            return (object) [
                'fecha' => $fecha,
                'tickets' => $tickets,
                'unidades' => (float) ($detalle->unidades ?? 0),
                'ingresos' => $ingresos,
                'costo' => $costo,
                'margen' => $ingresos - $costo,
                'ticket_promedio' => $tickets > 0 ? $ingresos / $tickets : 0,
            ];
        });

        $totalIngresos = (float) $dias->sum('ingresos');
        $totalCosto = (float) $dias->sum('costo');
        $totalTickets = (int) $dias->sum('tickets');

        return [
            'dias' => $dias,
            'total_ingresos' => round($totalIngresos, 2),
            'total_costo' => round($totalCosto, 2),
            'total_margen' => round($totalIngresos - $totalCosto, 2),
            'total_tickets' => $totalTickets,
            'ticket_promedio' => $totalTickets > 0 ? $totalIngresos / $totalTickets : 0,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function ventasVendedor($desde, $hasta)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $filas = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id_venta', '=', 'dv.venta_id_venta')
            ->join('users as u', 'u.id', '=', 'v.user_id')
            ->where('v.estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('v.created_at', [$desde, $finDelDia])
            ->select(
                'u.name as usuario',
                'v.id_venta',
                'v.created_at',
                'dv.sub_total',
                'dv.cantidad_base',
                'dv.costo_total'
            )
            ->get();

        $agrupado = [];

        foreach ($filas as $fila) {
            $hora = Carbon::parse($fila->created_at)->format('H');
            $llave = $fila->usuario.'|'.$hora;

            if (! isset($agrupado[$llave])) {
                $agrupado[$llave] = (object) [
                    'usuario' => $fila->usuario,
                    'hora' => $hora,
                    'tickets' => [],
                    'unidades' => 0,
                    'ingresos' => 0,
                    'costo' => 0,
                ];
            }

            $agrupado[$llave]->tickets[$fila->id_venta] = true;
            $agrupado[$llave]->unidades += (float) $fila->cantidad_base;
            $agrupado[$llave]->ingresos += (float) $fila->sub_total;
            $agrupado[$llave]->costo += (float) ($fila->costo_total ?? 0);
        }

        $filas = collect($agrupado)
            ->map(function ($fila) {
                $fila->tickets = count($fila->tickets);
                $fila->margen = $fila->ingresos - $fila->costo;
                $fila->ticket_promedio = $fila->tickets > 0 ? $fila->ingresos / $fila->tickets : 0;

                return $fila;
            })
            ->sortBy([['usuario', 'asc'], ['hora', 'asc']])
            ->values();

        return [
            'filas' => $filas,
            'total_ingresos' => round((float) $filas->sum('ingresos'), 2),
            'total_costo' => round((float) $filas->sum('costo'), 2),
            'total_margen' => round((float) $filas->sum('margen'), 2),
            'total_tickets' => (int) $filas->sum('tickets'),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function anulaciones($desde, $hasta, $usuarioId = null)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $anulaciones = Venta::with('cliente', 'user', 'anuladaPor')
            ->where('estado', Venta::ESTADO_ANULADA)
            ->whereNotNull('anulada_at')
            ->whereBetween('anulada_at', [$desde, $finDelDia])
            ->when($usuarioId, fn ($q) => $q->where('anulada_user_id', $usuarioId))
            ->orderByDesc('anulada_at')
            ->get();

        return [
            'anulaciones' => $anulaciones,
            'cantidad' => $anulaciones->count(),
            'total' => round((float) $anulaciones->sum('total'), 2),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function comprasPorProveedor($desde, $hasta)
    {
        $proveedores = DB::table('ingreso as i')
            ->join('proveedor as p', 'p.id_proveedor', '=', 'i.proveedor_id_proveedor')
            ->where('i.estado', Ingreso::ESTADO_RECIBIDA)
            ->whereDate('i.fecha', '>=', $desde)
            ->whereDate('i.fecha', '<=', $hasta)
            ->select(
                'p.id_proveedor',
                'p.razon_social',
                DB::raw('COUNT(*) as compras'),
                DB::raw('SUM(i.total) as total'),
                DB::raw('AVG(i.total) as promedio'),
                DB::raw('MAX(i.fecha) as ultima_compra'),
                DB::raw('SUM(CASE WHEN i.pagado_desde_caja = 1 THEN i.total ELSE 0 END) as total_caja'),
                DB::raw('SUM(CASE WHEN i.pagado_desde_caja = 0 THEN i.total ELSE 0 END) as total_externo')
            )
            ->groupBy('p.id_proveedor', 'p.razon_social')
            ->orderByDesc('total')
            ->get();

        $total = (float) $proveedores->sum('total');

        foreach ($proveedores as $proveedor) {
            $proveedor->participacion = $total > 0 ? ((float) $proveedor->total / $total) * 100 : 0;
        }

        return [
            'proveedores' => $proveedores,
            'total' => round($total, 2),
            'cantidad' => $proveedores->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function evolucionCostos($desde, $hasta, $proveedorId = null, $productoId = null)
    {
        $filas = DB::table('detalle_ingreso as di')
            ->join('ingreso as i', 'i.id_ingreso', '=', 'di.ingreso_id_ingreso')
            ->join('producto as p', 'p.id_producto', '=', 'di.producto_id_producto')
            ->leftJoin('proveedor as pr', 'pr.id_proveedor', '=', 'i.proveedor_id_proveedor')
            ->where('i.estado', Ingreso::ESTADO_RECIBIDA)
            ->whereDate('i.fecha', '>=', $desde)
            ->whereDate('i.fecha', '<=', $hasta)
            ->when($proveedorId, fn ($q) => $q->where('i.proveedor_id_proveedor', $proveedorId))
            ->when($productoId, fn ($q) => $q->where('di.producto_id_producto', $productoId))
            ->select(
                'p.id_producto',
                'p.nombre as producto',
                'pr.razon_social as proveedor',
                'i.fecha',
                'i.correlativo',
                'di.costo_unitario_base',
                'di.cantidad_base',
                'di.costo_total'
            )
            ->orderBy('p.nombre')
            ->orderBy('i.fecha')
            ->orderBy('i.id_ingreso')
            ->get();

        $anterior = [];

        foreach ($filas as $fila) {
            $llave = $fila->id_producto;
            $costo = $fila->costo_unitario_base !== null ? (float) $fila->costo_unitario_base : null;
            $previo = $anterior[$llave] ?? null;

            $fila->costo_anterior = $previo;
            $fila->variacion = ($costo !== null && $previo !== null) ? $costo - $previo : null;
            $fila->variacion_pct = ($costo !== null && $previo !== null && $previo != 0)
                ? (($costo - $previo) / $previo) * 100
                : null;

            if ($costo !== null) {
                $anterior[$llave] = $costo;
            }
        }

        return [
            'filas' => $filas,
            'cantidad' => $filas->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function devolucionesProveedor($desde, $hasta, $proveedorId = null)
    {
        $devoluciones = DevolucionProveedor::with('proveedor', 'user', 'detalles')
            ->where('estado', DevolucionProveedor::ESTADO_REGISTRADA)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($proveedorId, fn ($q) => $q->where('proveedor_id_proveedor', $proveedorId))
            ->orderByDesc('fecha')
            ->get();

        return [
            'devoluciones' => $devoluciones,
            'cantidad' => $devoluciones->count(),
            'total' => round((float) $devoluciones->sum('total'), 2),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function merma($desde, $hasta, $tipo = null)
    {
        $ajustes = AjusteInventario::with('detalles')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->get();

        $tipos = $ajustes
            ->groupBy('tipo')
            ->map(function ($grupo, $tipo) {
                return (object) [
                    'tipo' => $tipo,
                    'etiqueta' => AjusteInventario::TIPOS[$tipo] ?? ucfirst((string) $tipo),
                    'documentos' => $grupo->count(),
                    'lineas' => $grupo->sum(fn ($ajuste) => $ajuste->detalles->count()),
                    'productos' => $grupo->flatMap(fn ($ajuste) => $ajuste->detalles->pluck('producto_id_producto'))->unique()->count(),
                    'costo' => (float) $grupo->sum('total'),
                ];
            })
            ->sortByDesc('costo')
            ->values();

        return [
            'tipos' => $tipos,
            'total' => round((float) $ajustes->sum('total'), 2),
            'cantidad' => $ajustes->count(),
            'desde' => $desde,
            'hasta' => $hasta,
            'tipo' => $tipo,
        ];
    }

    public function rotacion($categoriaId = null, $marcaId = null, $dias = 90)
    {
        $dias = max(1, min(365, (int) $dias));
        $desdeRotacion = today()->subDays($dias)->startOfDay();

        $ventas = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id_venta', '=', 'dv.venta_id_venta')
            ->where('v.estado', Venta::ESTADO_CONFIRMADA)
            ->where('v.created_at', '>=', $desdeRotacion)
            ->select(
                'dv.producto_id_producto as producto_id',
                DB::raw('SUM(dv.cantidad_base) as vendidos'),
                DB::raw('MAX(v.created_at) as ultima_venta')
            )
            ->groupBy('dv.producto_id_producto');

        $atenciones = DB::table('atencion_producto as ap')
            ->join('atencion as a', 'a.id_atencion', '=', 'ap.atencion_id_atencion')
            ->where('a.estado', Atencion::ESTADO_CONFIRMADA)
            ->where('a.created_at', '>=', $desdeRotacion)
            ->select(
                'ap.producto_id_producto as producto_id',
                DB::raw('SUM(ap.cantidad_base) as vendidos'),
                DB::raw('MAX(a.created_at) as ultima_venta')
            )
            ->groupBy('ap.producto_id_producto');

        $valorCapas = DB::table('producto_lote as pl')
            ->join('lote_ingreso as li', 'li.producto_lote_id', '=', 'pl.id')
            ->where('pl.estado', '!=', 'baja')
            ->where('li.cantidad_disponible_base', '>', 0)
            ->select('pl.producto_id', DB::raw('SUM(li.cantidad_disponible_base * li.costo_unitario_base) as valor_costo'))
            ->groupBy('pl.producto_id');

        $productos = Producto::query()
            ->without('imagenes')
            ->leftJoinSub($ventas, 'ventas', fn ($join) => $join->on('ventas.producto_id', '=', 'producto.id_producto'))
            ->leftJoinSub($atenciones, 'atenciones', fn ($join) => $join->on('atenciones.producto_id', '=', 'producto.id_producto'))
            ->leftJoinSub($valorCapas, 'valor', fn ($join) => $join->on('valor.producto_id', '=', 'producto.id_producto'))
            ->where('producto.estado', 1)
            ->when($categoriaId, fn ($q) => $q->where('producto.categoria_id_categoria', $categoriaId))
            ->when($marcaId, fn ($q) => $q->where('producto.marca_id_marca', $marcaId))
            ->select(
                'producto.id_producto',
                'producto.nombre',
                'producto.stock',
                'producto.stock_minimo',
                DB::raw('COALESCE(ventas.vendidos, 0) as vendidos_ventas'),
                DB::raw('COALESCE(atenciones.vendidos, 0) as vendidos_atenciones'),
                DB::raw('ventas.ultima_venta as ultima_venta_v'),
                DB::raw('atenciones.ultima_venta as ultima_venta_a'),
                DB::raw('COALESCE(valor.valor_costo, 0) as valor_costo')
            )
            ->orderByRaw('(COALESCE(ventas.vendidos, 0) + COALESCE(atenciones.vendidos, 0)) DESC')
            ->orderBy('producto.nombre')
            ->get();

        foreach ($productos as $producto) {
            $producto->vendidos = (float) $producto->vendidos_ventas + (float) $producto->vendidos_atenciones;
            $producto->ultima_venta = collect([$producto->ultima_venta_v, $producto->ultima_venta_a])->filter()->max();

            $vendidos = (float) $producto->vendidos;
            $stock = (float) $producto->stock;
            $promedio = $vendidos / $dias;

            $producto->promedio_diario = $promedio;
            $producto->dias_cobertura = $promedio > 0 ? $stock / $promedio : null;
            $producto->dias_sin_venta = $producto->ultima_venta
                ? (int) Carbon::parse($producto->ultima_venta)->startOfDay()->diffInDays(today())
                : null;

            $producto->clasificacion = match (true) {
                $vendidos <= 0 => 'Sin movimiento',
                $producto->dias_cobertura !== null && $producto->dias_cobertura <= 15 => 'Alta rotación',
                $producto->dias_cobertura !== null && $producto->dias_cobertura <= 45 => 'Rotación normal',
                $producto->dias_cobertura !== null => 'Baja rotación',
                default => 'Sin datos',
            };
        }

        return [
            'productos' => $productos,
            'dias' => $dias,
            'total_sku' => $productos->count(),
            'total_valor' => round((float) $productos->sum('valor_costo'), 2),
            'sin_movimiento' => $productos->where('vendidos', '<=', 0)->count(),
            'desde' => $desdeRotacion->toDateString(),
            'hasta' => today()->toDateString(),
        ];
    }

    public function margenAgrupado($desde, $hasta, $agrupar = 'categoria')
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();
        $agrupar = in_array($agrupar, ['categoria', 'marca', 'vendedor'], true) ? $agrupar : 'categoria';

        $expresion = match ($agrupar) {
            'marca' => "COALESCE(m.nombre, 'Sin marca')",
            'vendedor' => "COALESCE(u.name, 'Sin usuario')",
            default => "COALESCE(c.nombre, 'Sin categoría')",
        };

        $ventas = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id_venta', '=', 'dv.venta_id_venta')
            ->join('producto as p', 'p.id_producto', '=', 'dv.producto_id_producto')
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->leftJoin('categoria as c', 'c.id_categoria', '=', 'p.categoria_id_categoria')
            ->leftJoin('marca as m', 'm.id_marca', '=', 'p.marca_id_marca')
            ->where('v.estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('v.created_at', [$desde, $finDelDia])
            ->select(
                DB::raw($expresion.' as nombre'),
                DB::raw('SUM(dv.sub_total) as ingresos'),
                DB::raw('SUM(COALESCE(dv.costo_total, 0)) as costo'),
                DB::raw('COUNT(DISTINCT v.id_venta) as operaciones')
            )
            ->groupByRaw($expresion)
            ->get();

        $atenciones = DB::table('atencion_producto as ap')
            ->join('atencion as a', 'a.id_atencion', '=', 'ap.atencion_id_atencion')
            ->join('producto as p', 'p.id_producto', '=', 'ap.producto_id_producto')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('categoria as c', 'c.id_categoria', '=', 'p.categoria_id_categoria')
            ->leftJoin('marca as m', 'm.id_marca', '=', 'p.marca_id_marca')
            ->where('a.estado', Atencion::ESTADO_CONFIRMADA)
            ->whereBetween('a.created_at', [$desde, $finDelDia])
            ->select(
                DB::raw($expresion.' as nombre'),
                DB::raw('SUM(ap.sub_total) as ingresos'),
                DB::raw('SUM(COALESCE(ap.costo_total, 0)) as costo'),
                DB::raw('COUNT(DISTINCT a.id_atencion) as operaciones')
            )
            ->groupByRaw($expresion)
            ->get();

        $agrupado = [];

        foreach ([$ventas, $atenciones] as $filas) {
            foreach ($filas as $fila) {
                $llave = $fila->nombre;

                if (! isset($agrupado[$llave])) {
                    $agrupado[$llave] = (object) [
                        'nombre' => $llave,
                        'ingresos' => 0,
                        'costo' => 0,
                        'operaciones' => 0,
                    ];
                }

                $agrupado[$llave]->ingresos += (float) $fila->ingresos;
                $agrupado[$llave]->costo += (float) $fila->costo;
                $agrupado[$llave]->operaciones += (int) $fila->operaciones;
            }
        }

        $filas = collect($agrupado)
            ->map(function ($fila) {
                $fila->margen = $fila->ingresos - $fila->costo;
                $fila->margen_pct = $fila->ingresos > 0 ? ($fila->margen / $fila->ingresos) * 100 : 0;

                return $fila;
            })
            ->sortByDesc('margen')
            ->values();

        $totalIngresos = (float) $filas->sum('ingresos');
        $totalCosto = (float) $filas->sum('costo');

        foreach ($filas as $fila) {
            $fila->participacion = $totalIngresos > 0 ? ($fila->ingresos / $totalIngresos) * 100 : 0;
        }

        return [
            'filas' => $filas,
            'agrupar' => $agrupar,
            'total_ingresos' => round($totalIngresos, 2),
            'total_costo' => round($totalCosto, 2),
            'total_margen' => round($totalIngresos - $totalCosto, 2),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function servicios($desde, $hasta)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $servicios = DB::table('atencion_servicio as aps')
            ->join('atencion as a', 'a.id_atencion', '=', 'aps.atencion_id_atencion')
            ->join('servicio as s', 's.id_servicio', '=', 'aps.servicio_id_servicio')
            ->where('a.estado', Atencion::ESTADO_CONFIRMADA)
            ->whereBetween('a.created_at', [$desde, $finDelDia])
            ->select(
                's.nombre',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM(aps.subtotal) as ingresos'),
                DB::raw('SUM(COALESCE(s.costo, 0)) as costo'),
                DB::raw('COUNT(DISTINCT a.id_atencion) as atenciones')
            )
            ->groupBy('s.id_servicio', 's.nombre')
            ->orderByDesc('ingresos')
            ->get();

        $total = (float) $servicios->sum('ingresos');
        $totalCosto = (float) $servicios->sum('costo');

        foreach ($servicios as $servicio) {
            $servicio->ticket_promedio = (int) $servicio->atenciones > 0
                ? (float) $servicio->ingresos / (int) $servicio->atenciones
                : 0;
            $servicio->participacion = $total > 0 ? ((float) $servicio->ingresos / $total) * 100 : 0;
            $servicio->margen = (float) $servicio->ingresos - (float) $servicio->costo;
            $servicio->margen_pct = (float) $servicio->ingresos > 0
                ? ($servicio->margen / (float) $servicio->ingresos) * 100
                : 0;
        }

        return [
            'servicios' => $servicios,
            'total' => round($total, 2),
            'total_costo' => round($totalCosto, 2),
            'total_margen' => round($total - $totalCosto, 2),
            'cantidad' => $servicios->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function clientesRfm($desde, $hasta, $limite = 100, $segmento = null)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();
        $limite = max(1, min(500, (int) $limite));

        $ventas = DB::table('venta')
            ->select(
                'cliente_id_cliente',
                DB::raw('COUNT(*) as frecuencia'),
                DB::raw('SUM(total) as monto'),
                DB::raw('MAX(created_at) as ultima'),
                DB::raw('MIN(created_at) as primera')
            )
            ->where('estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$desde, $finDelDia])
            ->groupBy('cliente_id_cliente');

        $atenciones = DB::table('atencion')
            ->select(
                'cliente_id_cliente',
                DB::raw('COUNT(*) as frecuencia'),
                DB::raw('SUM(total) as monto'),
                DB::raw('MAX(created_at) as ultima'),
                DB::raw('MIN(created_at) as primera')
            )
            ->where('estado', Atencion::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$desde, $finDelDia])
            ->groupBy('cliente_id_cliente');

        $clientes = Cliente::query()
            ->leftJoinSub($ventas, 'v', fn ($join) => $join->on('v.cliente_id_cliente', '=', 'cliente.id_cliente'))
            ->leftJoinSub($atenciones, 'a', fn ($join) => $join->on('a.cliente_id_cliente', '=', 'cliente.id_cliente'))
            ->select(
                'cliente.id_cliente',
                'cliente.dni',
                'cliente.nombre',
                'cliente.apellido_paterno',
                'cliente.apellido_materno',
                'v.frecuencia as frecuencia_v',
                'v.monto as monto_v',
                'v.ultima as ultima_v',
                'v.primera as primera_v',
                'a.frecuencia as frecuencia_a',
                'a.monto as monto_a',
                'a.ultima as ultima_a',
                'a.primera as primera_a'
            )
            ->whereRaw('(COALESCE(v.frecuencia, 0) + COALESCE(a.frecuencia, 0)) > 0')
            ->orderByRaw('(COALESCE(v.monto, 0) + COALESCE(a.monto, 0)) DESC')
            ->limit(500)
            ->get()
            ->map(function ($cliente) {
                $ultima = collect([$cliente->ultima_v, $cliente->ultima_a])->filter()->max();
                $primera = collect([$cliente->primera_v, $cliente->primera_a])->filter()->min();
                $frecuencia = (int) $cliente->frecuencia_v + (int) $cliente->frecuencia_a;
                $monto = (float) $cliente->monto_v + (float) $cliente->monto_a;
                $dias = $ultima ? (int) Carbon::parse($ultima)->startOfDay()->diffInDays(today()) : null;

                $segmentoCalculado = match (true) {
                    $primera !== null && Carbon::parse($primera)->diffInDays(today()) <= 30 => 'Nuevo',
                    $dias === null => 'Sin compras',
                    $dias <= 30 => 'Activo',
                    $dias <= 60 => 'En riesgo',
                    $dias <= 90 => 'Inactivo',
                    default => 'Perdido',
                };

                return (object) [
                    'id_cliente' => $cliente->id_cliente,
                    'dni' => $cliente->dni,
                    'nombre' => trim($cliente->nombre.' '.$cliente->apellido_paterno.' '.$cliente->apellido_materno),
                    'frecuencia' => $frecuencia,
                    'monto' => $monto,
                    'ticket_promedio' => $frecuencia > 0 ? $monto / $frecuencia : 0,
                    'ultima_compra' => $ultima,
                    'dias_sin_comprar' => $dias,
                    'segmento' => $segmentoCalculado,
                ];
            })
            ->when($segmento, fn ($coleccion) => $coleccion->where('segmento', $segmento))
            ->take($limite)
            ->values();

        return [
            'clientes' => $clientes,
            'cantidad' => $clientes->count(),
            'total_monto' => round((float) $clientes->sum('monto'), 2),
            'total_frecuencia' => (int) $clientes->sum('frecuencia'),
            'segmento' => $segmento,
            'limite' => $limite,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function clientesWeb($desde, $hasta)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $clientes = Cliente::whereBetween('created_at', [$desde, $finDelDia])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($cliente) => (object) [
                'id_cliente' => $cliente->id_cliente,
                'dni' => $cliente->dni,
                'nombre' => $cliente->nombre_completo,
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'registro_web' => (bool) $cliente->registro_web,
                'created_at' => $cliente->created_at,
                'ultimo_login' => $cliente->ultimo_login,
            ]);

        $total = $clientes->count();
        $web = $clientes->where('registro_web', true)->count();
        $activosWeb = Cliente::where('registro_web', true)->whereNotNull('ultimo_login')->count();

        return [
            'clientes' => $clientes,
            'total' => $total,
            'web' => $web,
            'presencial' => $total - $web,
            'activos_web' => $activosWeb,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function cajaPorOrigen($desde, $hasta, $cajaId = null)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $filas = CajaMovimiento::query()
            ->when($cajaId, fn ($q) => $q->where('caja_id_caja', $cajaId))
            ->whereBetween('created_at', [$desde, $finDelDia])
            ->select(
                'categoria',
                DB::raw("SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos"),
                DB::raw("SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) as egresos"),
                DB::raw('COUNT(*) as movimientos')
            )
            ->groupBy('categoria')
            ->orderByDesc('ingresos')
            ->get();

        foreach ($filas as $fila) {
            $fila->etiqueta = CajaMovimiento::labelCategoria($fila->categoria);
            $fila->neto = (float) $fila->ingresos - (float) $fila->egresos;
        }

        return [
            'filas' => $filas,
            'total_ingresos' => round((float) $filas->sum('ingresos'), 2),
            'total_egresos' => round((float) $filas->sum('egresos'), 2),
            'total_neto' => round((float) $filas->sum('neto'), 2),
            'caja_id' => $cajaId,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function sugeridoCompra($categoriaId = null, $marcaId = null, $dias = 90)
    {
        $dias = max(1, min(365, (int) $dias));
        $desde = today()->subDays($dias)->startOfDay();

        $ventas = DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id_venta', '=', 'dv.venta_id_venta')
            ->where('v.estado', Venta::ESTADO_CONFIRMADA)
            ->where('v.created_at', '>=', $desde)
            ->select('dv.producto_id_producto as producto_id', DB::raw('SUM(dv.cantidad_base) as cantidad'))
            ->groupBy('dv.producto_id_producto')
            ->pluck('cantidad', 'producto_id');

        $atenciones = DB::table('atencion_producto as ap')
            ->join('atencion as a', 'a.id_atencion', '=', 'ap.atencion_id_atencion')
            ->where('a.estado', Atencion::ESTADO_CONFIRMADA)
            ->where('a.created_at', '>=', $desde)
            ->select('ap.producto_id_producto as producto_id', DB::raw('SUM(ap.cantidad_base) as cantidad'))
            ->groupBy('ap.producto_id_producto')
            ->pluck('cantidad', 'producto_id');

        $ultimaCompra = DB::table('detalle_ingreso as di')
            ->join('ingreso as i', 'i.id_ingreso', '=', 'di.ingreso_id_ingreso')
            ->join('proveedor as p', 'p.id_proveedor', '=', 'i.proveedor_id_proveedor')
            ->where('i.estado', Ingreso::ESTADO_RECIBIDA)
            ->select(
                'di.producto_id_producto as producto_id',
                'p.razon_social as proveedor',
                'p.lead_time',
                'i.fecha as ultima_compra'
            )
            ->orderBy('i.fecha')
            ->orderBy('i.id_ingreso')
            ->get()
            ->keyBy('producto_id');

        $productos = Producto::query()
            ->without('imagenes')
            ->where('producto.estado', 1)
            ->when($categoriaId, fn ($q) => $q->where('producto.categoria_id_categoria', $categoriaId))
            ->when($marcaId, fn ($q) => $q->where('producto.marca_id_marca', $marcaId))
            ->orderBy('producto.nombre')
            ->get();

        foreach ($productos as $producto) {
            $vendidos = (float) ($ventas[$producto->id_producto] ?? 0)
                + (float) ($atenciones[$producto->id_producto] ?? 0);
            $promedio = $vendidos / $dias;
            $compra = $ultimaCompra->get($producto->id_producto);
            $leadTime = (int) ($compra?->lead_time ?? 7);
            $cobertura = 15;
            $stock = (float) $producto->stock;
            $minimo = (float) $producto->stock_minimo;
            $maximo = $producto->stock_maximo !== null ? (float) $producto->stock_maximo : null;

            $sugerido = $promedio > 0
                ? $promedio * ($leadTime + $cobertura) - $stock
                : $minimo - $stock;

            $sugerido = max(0, (float) ceil($sugerido));

            if ($maximo !== null) {
                $sugerido = min($sugerido, max(0, (float) ceil($maximo - $stock)));
            }

            $producto->vendidos = $vendidos;
            $producto->promedio_diario = $promedio;
            $producto->lead_time = $leadTime;
            $producto->proveedor = $compra?->proveedor;
            $producto->ultima_compra = $compra?->ultima_compra;
            $producto->dias_cobertura = $promedio > 0 ? $stock / $promedio : null;
            $producto->sugerido = $sugerido;
        }

        $productos = $productos->sortByDesc('sugerido')->values();

        return [
            'productos' => $productos,
            'dias' => $dias,
            'total_sugerido' => $productos->sum('sugerido'),
            'con_sugerido' => $productos->where('sugerido', '>', 0)->count(),
            'desde' => $desde->toDateString(),
            'hasta' => today()->toDateString(),
        ];
    }

    public function cumpleanos($dias = 30)
    {
        $dias = max(1, min(365, (int) $dias));
        $hoy = today()->startOfDay();

        $clientes = Cliente::whereNotNull('fecha_nacimiento')
            ->orderBy('fecha_nacimiento')
            ->get()
            ->map(function ($cliente) use ($hoy) {
                $nacimiento = $cliente->fecha_nacimiento->copy();
                $proximo = $nacimiento->copy()->year($hoy->year);

                if ($proximo->lt($hoy)) {
                    $proximo->addYear();
                }

                $diasFaltantes = (int) $hoy->diffInDays($proximo);
                $edad = (int) $nacimiento->diffInYears($proximo);

                return (object) [
                    'id_cliente' => $cliente->id_cliente,
                    'dni' => $cliente->dni,
                    'nombre' => $cliente->nombre_completo,
                    'telefono' => $cliente->telefono,
                    'email' => $cliente->email,
                    'direccion' => $cliente->direccion,
                    'fecha_nacimiento' => $nacimiento,
                    'proximo_cumpleanos' => $proximo,
                    'dias_faltantes' => $diasFaltantes,
                    'edad_cumple' => $edad,
                ];
            })
            ->filter(fn ($cliente) => $cliente->dias_faltantes <= $dias)
            ->sortBy('dias_faltantes')
            ->values();

        return [
            'clientes' => $clientes,
            'cantidad' => $clientes->count(),
            'dias' => $dias,
            'hasta' => $hoy->copy()->addDays($dias)->toDateString(),
        ];
    }

    public function resumenGerencial($desde, $hasta)
    {
        $ventas = $this->ventas($desde, $hasta);
        $atenciones = $this->atenciones($desde, $hasta);
        $rentabilidad = $this->rentabilidad($desde, $hasta);
        $compras = $this->ingresos($desde, $hasta);
        $merma = $this->merma($desde, $hasta);
        $caja = $this->cajaPorOrigen($desde, $hasta);
        $productos = $this->ventasProducto($desde, $hasta);
        $servicios = $this->servicios($desde, $hasta);
        $anulaciones = $this->anulaciones($desde, $hasta);
        $lotesVencidos = $this->lotes(null, null, 'vencidos');

        $clientesUnicos = DB::table('venta')
            ->where('estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$desde, Carbon::parse($hasta)->endOfDay()])
            ->select('cliente_id_cliente')
            ->union(
                DB::table('atencion')
                    ->where('estado', Atencion::ESTADO_CONFIRMADA)
                    ->whereBetween('created_at', [$desde, Carbon::parse($hasta)->endOfDay()])
                    ->select('cliente_id_cliente')
            )
            ->get()
            ->count();

        $transacciones = (int) $ventas['cantidad'] + (int) $atenciones['cantidad'];
        $ingresoTotal = (float) $ventas['total_general'] + (float) $atenciones['total_general'];
        $margenTotal = (float) $rentabilidad['total_margen'] + (float) $servicios['total_margen'];
        $stockCritico = Producto::where('estado', 1)
            ->conAlertaStock('inventario')
            ->count();

        return [
            'ventas' => $ventas,
            'atenciones' => $atenciones,
            'rentabilidad' => $rentabilidad,
            'compras' => $compras,
            'merma' => $merma,
            'caja' => $caja,
            'productos' => $productos,
            'servicios' => $servicios,
            'margen_total' => round($margenTotal, 2),
            'top_productos' => $productos['productos']->take(5),
            'anulaciones' => $anulaciones,
            'lotes_vencidos' => $lotesVencidos,
            'clientes_unicos' => $clientesUnicos,
            'transacciones' => $transacciones,
            'ingreso_total' => round($ingresoTotal, 2),
            'ticket_promedio' => $transacciones > 0 ? $ingresoTotal / $transacciones : 0,
            'stock_critico' => $stockCritico,
            'caja_abierta' => Caja::abierta()->exists(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function general($mes = null, $anio = null)
    {
        $mes = $mes ?: now()->month;
        $anio = $anio ?: now()->year;

        $inicioMes = Carbon::create($anio, $mes, 1)->startOfDay();
        $finMes = Carbon::create($anio, $mes, 1)->endOfMonth()->endOfDay();

        $ventas = Venta::where('estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$inicioMes, $finMes]);
        $atenciones = Atencion::where('estado', Atencion::ESTADO_CONFIRMADA)
            ->whereBetween('created_at', [$inicioMes, $finMes]);
        $ingresos = Ingreso::where('estado', 'recibida')
            ->whereDate('fecha', '>=', $inicioMes->format('Y-m-d'))
            ->whereDate('fecha', '<=', $finMes->format('Y-m-d'));

        $totalVentas = $ventas->sum('total');
        $totalAtenciones = $atenciones->sum('total');
        $cantVentas = $ventas->count();
        $cantAtenciones = $atenciones->count();
        $cantIngresos = $ingresos->count();
        $totalCompras = $ingresos->sum('total');

        $topProductos = DB::table('detalle_venta')
            ->join('producto', 'detalle_venta.producto_id_producto', '=', 'producto.id_producto')
            ->join('venta', 'detalle_venta.venta_id_venta', '=', 'venta.id_venta')
            ->where('venta.estado', Venta::ESTADO_CONFIRMADA)
            ->whereBetween('venta.created_at', [$inicioMes, $finMes])
            ->select('producto.nombre', DB::raw('SUM(detalle_venta.cantidad_base) as total_vendido'))
            ->groupBy('producto.id_producto', 'producto.nombre')
            ->orderByDesc('total_vendido')
            ->limit(10)
            ->get();

        $stockCritico = Producto::where('estado', 1)
            ->conAlertaStock('inventario')
            ->count();

        $cajas = Caja::whereBetween('apertura_at', [$inicioMes, $finMes])->get();
        $totalIngresosCaja = $cajas->sum('total_ingresos');
        $totalEgresosCaja = $cajas->sum('total_egresos');

        return [
            'mes' => $mes,
            'anio' => $anio,
            'nombre_mes' => ucfirst(Carbon::create($anio, $mes, 1)->isoFormat('MMMM')),
            'inicio_mes' => $inicioMes->format('Y-m-d'),
            'fin_mes' => $finMes->format('Y-m-d'),
            'total_ventas' => $totalVentas,
            'total_atenciones' => $totalAtenciones,
            'total_ingresos' => $totalVentas + $totalAtenciones,
            'cant_ventas' => $cantVentas,
            'cant_atenciones' => $cantAtenciones,
            'cant_ingresos' => $cantIngresos,
            'total_compras' => round((float) $totalCompras, 2),
            'top_productos' => $topProductos,
            'stock_critico' => $stockCritico,
            'total_ingresos_caja' => $totalIngresosCaja,
            'total_egresos_caja' => $totalEgresosCaja,
        ];
    }

    public function auditoria($modulo = null, $usuarioId = null, $accion = null, $desde = null, $hasta = null)
    {
        $query = Auditoria::with('user');

        if ($modulo) {
            $query->where('modulo', $modulo);
        }
        if ($usuarioId) {
            $query->where('user_id', $usuarioId);
        }
        if ($accion) {
            $query->where('accion', $accion);
        }
        if ($desde) {
            $query->where('created_at', '>=', $desde);
        }
        if ($hasta) {
            $query->where('created_at', '<=', Carbon::parse($hasta)->endOfDay());
        }

        $total = (clone $query)->count();
        $registros = $query->orderByDesc('created_at')->limit(500)->get();

        return [
            'registros' => $registros,
            'cantidad' => $registros->count(),
            'total' => $total,
            'truncado' => $total > 500,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }
}
