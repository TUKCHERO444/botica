<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atencion;
use App\Models\Producto;
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
        $ingresosVentasHoy = Venta::whereDate('created_at', $hoy)->sum('total');
        $ingresosAtencionesHoy = Atencion::whereDate('created_at', $hoy)->sum('total');
        $totalIngresosHoy = $ingresosVentasHoy + $ingresosAtencionesHoy;

        $ventasHoy = Venta::whereDate('created_at', $hoy)->count();
        $atencionesHoy = Atencion::whereDate('created_at', $hoy)->count();
        $stockBajo = Producto::where('stock', '<=', 5)->count();

        // --- Charts: Últimos 7 días (ventas vs atenciones) ---
        $dias = collect();
        for ($i = 6; $i >= 0; $i--) {
            $dias->push(Carbon::today()->subDays($i)->format('Y-m-d'));
        }

        $ventasSemana = Venta::select(
            DB::raw('DATE(created_at) as fecha'),
            DB::raw('SUM(total) as total')
        )
            ->where('created_at', '>=', Carbon::today()->subDays(6))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'fecha');

        $atencionesSemana = Atencion::select(
            DB::raw('DATE(created_at) as fecha'),
            DB::raw('SUM(total) as total')
        )
            ->where('created_at', '>=', Carbon::today()->subDays(6))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'fecha');

        $etiquetasSemana = $dias->map(fn($d) => Carbon::parse($d)->isoFormat('ddd D/M'));
        $ventasData = $dias->map(fn($d) => (float)($ventasSemana[$d] ?? 0));
        $atencionesData = $dias->map(fn($d) => (float)($atencionesSemana[$d] ?? 0));

        // --- Charts: Distribución del mes (ventas vs atenciones) ---
        $inicioMes = Carbon::today()->startOfMonth();
        $totalVentasMes = Venta::where('created_at', '>=', $inicioMes)->sum('total');
        $totalAtencionesMes = Atencion::where('created_at', '>=', $inicioMes)->sum('total');

        // --- Productos con riesgo de stock (stock ≤ 20% del inventario) ---
        $productosRiesgo = Producto::where('inventario', '>', 0)
            ->whereColumn('stock', '<=', DB::raw('inventario * 0.20'))
            ->select(
                'id_producto',
                'nombre',
                'stock',
                'inventario',
                DB::raw('ROUND((1 - stock / CAST(inventario AS DECIMAL(10,2))) * 100, 1) as porcentaje_agotado')
            )
            ->orderByDesc('porcentaje_agotado')
            ->limit(10)
            ->get();

        // --- Top 5 productos más vendidos del mes ---
        $topProductos = DB::table('detalle_venta')
            ->join('producto', 'detalle_venta.producto_id_producto', '=', 'producto.id_producto')
            ->join('venta', 'detalle_venta.venta_id_venta', '=', 'venta.id_venta')
            ->where('venta.created_at', '>=', $inicioMes)
            ->select(
                'producto.nombre',
                DB::raw('SUM(detalle_venta.cantidad) as total_vendido')
            )
            ->groupBy('producto.id_producto', 'producto.nombre')
            ->orderByDesc('total_vendido')
            ->limit(5)
            ->get();

        return [
            'totalIngresosHoy'   => $totalIngresosHoy,
            'ventasHoy'          => $ventasHoy,
            'atencionesHoy'      => $atencionesHoy,
            'stockBajo'          => $stockBajo,
            'chartLabels'        => $etiquetasSemana,
            'chartVentas'        => $ventasData,
            'chartAtenciones'    => $atencionesData,
            'totalVentasMes'     => $totalVentasMes,
            'totalAtencionesMes' => $totalAtencionesMes,
            'topProductos'       => $topProductos,
            'productosRiesgo'    => $productosRiesgo,
        ];
    }
}
