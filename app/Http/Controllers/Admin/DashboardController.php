<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atencion;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $data = $this->getDashboardData();

        if (request()->ajax()) {
            return view('admin.dashboard.index', $data);
        }

        return view('admin.dashboard.full', $data);
    }

    private function getDashboardData(): array
    {
        $hoy = Carbon::today();

        // --- Cards ---
        $ingresosVentasHoy = Venta::where('estado', Venta::ESTADO_CONFIRMADA)
            ->whereDate('created_at', $hoy)
            ->sum('total');
        $ingresosAtencionesHoy = Atencion::where('estado', Atencion::ESTADO_CONFIRMADA)
            ->whereDate('created_at', $hoy)
            ->sum('total');
        $totalIngresosHoy = $ingresosVentasHoy + $ingresosAtencionesHoy;

        $ventasHoy = Venta::where('estado', Venta::ESTADO_CONFIRMADA)->whereDate('created_at', $hoy)->count();
        $atencionesHoy = Atencion::where('estado', Atencion::ESTADO_CONFIRMADA)
            ->whereDate('created_at', $hoy)
            ->count();

        // --- Inventario: alertas sobre stock vendible (lotes no vencidos) ---
        $productosInventario = Producto::query()
            ->where('estado', 1)
            ->conStockVendibleCalculado()
            ->withCount('lotes')
            ->with('unidadBase')
            ->get();

        $productosInventario->each(function ($producto) {
            $producto->stock_vendible = (string) $producto->stock_vendible_calc;
            $minimo = (float) $producto->stock_minimo;
            $producto->porcentaje_agotado = $minimo > 0
                ? round(max(0, (1 - (float) $producto->stock_vendible / $minimo) * 100), 1)
                : 100;
        });

        // Solo productos con historial de lotes: evita alertar altas que aún
        // no pasaron por inventario inicial o una compra.
        $agotados = $productosInventario->filter(fn ($producto) => $producto->lotes_count > 0
            && (float) $producto->stock_vendible <= 0);

        $bajos = $productosInventario->filter(fn ($producto) => $producto->lotes_count > 0
            && (float) $producto->stock_minimo > 0
            && (float) $producto->stock_vendible > 0
            && (float) $producto->stock_vendible <= (float) $producto->stock_minimo);

        $stockAgotado = $agotados->count();
        $stockBajo = $bajos->count();

        $productosRiesgo = $agotados->concat($bajos)
            ->sortBy(fn ($producto) => (float) $producto->stock_minimo > 0
                ? (float) $producto->stock_vendible / (float) $producto->stock_minimo
                : -1)
            ->take(10)
            ->values();

        // --- Vencimientos por lote ---
        $lotesPorVencer = ProductoLote::query()
            ->where('estado', '!=', 'baja')
            ->where('cantidad_disponible_base', '>', 0)
            ->whereDate('fecha_vencimiento', '>=', $hoy->toDateString())
            ->whereDate('fecha_vencimiento', '<=', $hoy->copy()->addDays(30)->toDateString())
            ->count();

        $lotesVencidos = ProductoLote::query()
            ->where('estado', '!=', 'baja')
            ->where('cantidad_disponible_base', '>', 0)
            ->whereDate('fecha_vencimiento', '<', $hoy->toDateString())
            ->count();

        $lotesProximos = ProductoLote::with('producto.unidadBase')
            ->where('estado', '!=', 'baja')
            ->where('cantidad_disponible_base', '>', 0)
            ->whereDate('fecha_vencimiento', '>=', $hoy->toDateString())
            ->whereDate('fecha_vencimiento', '<=', $hoy->copy()->addDays(30)->toDateString())
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->limit(6)
            ->get();

        // --- Charts: Últimos 7 días (ventas vs atenciones) ---
        $dias = collect();
        for ($i = 6; $i >= 0; $i--) {
            $dias->push(Carbon::today()->subDays($i)->format('Y-m-d'));
        }

        $ventasSemana = Venta::select(
            DB::raw('DATE(created_at) as fecha'),
            DB::raw('SUM(total) as total')
        )
            ->where('estado', Venta::ESTADO_CONFIRMADA)
            ->where('created_at', '>=', Carbon::today()->subDays(6))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'fecha');

        $atencionesSemana = Atencion::select(
            DB::raw('DATE(created_at) as fecha'),
            DB::raw('SUM(total) as total')
        )
            ->where('estado', Atencion::ESTADO_CONFIRMADA)
            ->where('created_at', '>=', Carbon::today()->subDays(6))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'fecha');

        $etiquetasSemana = $dias->map(fn ($d) => Carbon::parse($d)->isoFormat('ddd D/M'));
        $ventasData = $dias->map(fn ($d) => (float) ($ventasSemana[$d] ?? 0));
        $atencionesData = $dias->map(fn ($d) => (float) ($atencionesSemana[$d] ?? 0));

        // --- Charts: Distribución del mes (ventas vs atenciones) ---
        $inicioMes = Carbon::today()->startOfMonth();
        $totalVentasMes = Venta::where('estado', Venta::ESTADO_CONFIRMADA)
            ->where('created_at', '>=', $inicioMes)
            ->sum('total');
        $totalAtencionesMes = Atencion::where('estado', Atencion::ESTADO_CONFIRMADA)
            ->where('created_at', '>=', $inicioMes)
            ->sum('total');

        // --- Top 5 productos más vendidos del mes ---
        $topProductos = DB::table('detalle_venta')
            ->join('producto', 'detalle_venta.producto_id_producto', '=', 'producto.id_producto')
            ->join('venta', 'detalle_venta.venta_id_venta', '=', 'venta.id_venta')
            ->where('venta.estado', Venta::ESTADO_CONFIRMADA)
            ->where('venta.created_at', '>=', $inicioMes)
            ->select(
                'producto.nombre',
                DB::raw('SUM(detalle_venta.cantidad_base) as total_vendido')
            )
            ->groupBy('producto.id_producto', 'producto.nombre')
            ->orderByDesc('total_vendido')
            ->limit(5)
            ->get();

        return [
            'totalIngresosHoy' => $totalIngresosHoy,
            'ventasHoy' => $ventasHoy,
            'atencionesHoy' => $atencionesHoy,
            'stockBajo' => $stockBajo,
            'stockAgotado' => $stockAgotado,
            'lotesPorVencer' => $lotesPorVencer,
            'lotesVencidos' => $lotesVencidos,
            'lotesProximos' => $lotesProximos,
            'chartLabels' => $etiquetasSemana,
            'chartVentas' => $ventasData,
            'chartAtenciones' => $atencionesData,
            'totalVentasMes' => $totalVentasMes,
            'totalAtencionesMes' => $totalAtencionesMes,
            'topProductos' => $topProductos,
            'productosRiesgo' => $productosRiesgo,
        ];
    }
}
