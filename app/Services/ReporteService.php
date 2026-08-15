<?php

namespace App\Services;

use App\Models\Atencion;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\KardexMovimiento;
use App\Models\LoteProducto;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReporteService
{
    public function ventas($desde, $hasta, $usuarioId = null, $clienteId = null)
    {
        $query = Venta::with('cliente', 'user', 'productos')
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
        $query = Atencion::with('cliente', 'user', 'productos', 'servicios')
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

    public function ingresos($desde, $hasta, $proveedorId = null, $usuarioId = null)
    {
        $query = Ingreso::with('proveedor', 'user', 'productos')
            ->whereBetween('fecha', [$desde, $hasta]);

        if ($proveedorId) {
            $query->where('proveedor_id_proveedor', $proveedorId);
        }
        if ($usuarioId) {
            $query->where('user_id', $usuarioId);
        }

        $ingresos = $query->orderBy('fecha')->get();

        $totalProductos = 0;
        foreach ($ingresos as $ingreso) {
            $totalProductos += $ingreso->productos->sum('pivot.cantidad');
        }

        return [
            'ingresos' => $ingresos,
            'total_productos' => $totalProductos,
            'cantidad' => $ingresos->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    public function caja($cajaId = null, $desde = null, $hasta = null)
    {
        $query = Caja::with('aperturaUser', 'cierreUser', 'movimientos.user');

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

    public function inventario($categoriaId = null, $marcaId = null, $stockMenorQue = null)
    {
        $query = Producto::with('categoria', 'marca', 'imagenes');

        if ($categoriaId) {
            $query->where('categoria_id_categoria', $categoriaId);
        }
        if ($marcaId) {
            $query->where('marca_id_marca', $marcaId);
        }
        if ($stockMenorQue) {
            $query->where('stock', '<=', $stockMenorQue);
        }

        $productos = $query->orderBy('nombre')->get();

        $totalValorizado = $productos->sum(fn($p) => $p->stock * $p->precio_compra);
        $totalStock = $productos->sum('stock');

        return [
            'productos' => $productos,
            'total_valorizado' => $totalValorizado,
            'total_stock' => $totalStock,
            'cantidad' => $productos->count(),
        ];
    }

    public function kardex($productoId, $desde = null, $hasta = null, $tipo = null)
    {
        $query = KardexMovimiento::with('producto', 'usuario')
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

    public function vencimientos($productoId = null, $dias = null)
    {
        $query = LoteProducto::with('producto');

        if ($productoId) {
            $query->where('producto_id_producto', $productoId);
        }

        $lotes = $query->orderBy('fecha_vencimiento')->get();

        $ahora = now();
        $limite = $dias ?: 30;

        $vencidos = $lotes->filter(fn($l) => $l->fecha_vencimiento <= $ahora && $l->cantidad > 0);
        $criticos = $lotes->filter(fn($l) => $l->fecha_vencimiento > $ahora && $l->fecha_vencimiento <= $ahora->copy()->addDays(7) && $l->cantidad > 0);
        $proximos = $lotes->filter(fn($l) => $l->fecha_vencimiento > $ahora->copy()->addDays(7) && $l->fecha_vencimiento <= $ahora->copy()->addDays($limite) && $l->cantidad > 0);
        $vigentes = $lotes->filter(fn($l) => $l->fecha_vencimiento > $ahora->copy()->addDays($limite) && $l->cantidad > 0);
        $sinStock = $lotes->filter(fn($l) => $l->cantidad <= 0);

        return [
            'vencidos' => $vencidos,
            'criticos' => $criticos,
            'proximos' => $proximos,
            'vigentes' => $vigentes,
            'sin_stock' => $sinStock,
            'total_lotes' => $lotes->count(),
        ];
    }

    public function clientes($desde, $hasta, $limite = 20)
    {
        $finDelDia = Carbon::parse($hasta)->endOfDay();

        $topClientes = Cliente::select([
            'cliente.id_cliente',
            'cliente.dni',
            'cliente.nombre',
            'cliente.apellido_paterno',
            'cliente.apellido_materno',
            DB::raw('COALESCE(SUM(venta.total), 0) + COALESCE(SUM(atencion.total), 0) as total_gastado'),
            DB::raw('COUNT(DISTINCT venta.id_venta) + COUNT(DISTINCT atencion.id_atencion) as total_transacciones'),
        ])
            ->leftJoin('venta', function ($join) use ($desde, $finDelDia) {
                $join->on('cliente.id_cliente', '=', 'venta.cliente_id_cliente')
                    ->whereBetween('venta.created_at', [$desde, $finDelDia]);
            })
            ->leftJoin('atencion', function ($join) use ($desde, $finDelDia) {
                $join->on('cliente.id_cliente', '=', 'atencion.cliente_id_cliente')
                    ->whereBetween('atencion.created_at', [$desde, $finDelDia]);
            })
            ->groupBy('cliente.id_cliente', 'cliente.dni', 'cliente.nombre', 'cliente.apellido_paterno', 'cliente.apellido_materno')
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

    public function general($mes = null, $anio = null)
    {
        $mes = $mes ?: now()->month;
        $anio = $anio ?: now()->year;

        $inicioMes = Carbon::create($anio, $mes, 1)->startOfDay();
        $finMes = Carbon::create($anio, $mes, 1)->endOfMonth()->endOfDay();

        $ventas = Venta::whereBetween('created_at', [$inicioMes, $finMes]);
        $atenciones = Atencion::whereBetween('created_at', [$inicioMes, $finMes]);
        $ingresos = Ingreso::whereBetween('fecha', [$inicioMes->format('Y-m-d'), $finMes->format('Y-m-d')]);

        $totalVentas = $ventas->sum('total');
        $totalAtenciones = $atenciones->sum('total');
        $cantVentas = $ventas->count();
        $cantAtenciones = $atenciones->count();
        $cantIngresos = $ingresos->count();

        $topProductos = DB::table('detalle_venta')
            ->join('producto', 'detalle_venta.producto_id_producto', '=', 'producto.id_producto')
            ->join('venta', 'detalle_venta.venta_id_venta', '=', 'venta.id_venta')
            ->whereBetween('venta.created_at', [$inicioMes, $finMes])
            ->select('producto.nombre', DB::raw('SUM(detalle_venta.cantidad) as total_vendido'))
            ->groupBy('producto.id_producto', 'producto.nombre')
            ->orderByDesc('total_vendido')
            ->limit(10)
            ->get();

        $stockCritico = Producto::where('inventario', '>', 0)
            ->whereColumn('stock', '<=', DB::raw('inventario * 0.20'))
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

        $registros = $query->orderByDesc('created_at')->limit(500)->get();

        return [
            'registros' => $registros,
            'cantidad' => $registros->count(),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }
}
