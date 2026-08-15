<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\ReporteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function __construct(
        private ReporteService $reporteService
    ) {}

    public function index()
    {
        $data = [
            'productos' => Producto::select('id_producto', 'nombre')->orderBy('nombre')->get(),
            'clientes' => Cliente::select('id_cliente', 'nombre', 'apellido_paterno', 'apellido_materno')->orderBy('nombre')->get(),
            'proveedores' => Proveedor::select('id_proveedor', 'razon_social')->where('estado', 1)->orderBy('razon_social')->get(),
            'usuarios' => User::select('id', 'name')->orderBy('name')->get(),
            'categorias' => Categoria::select('id_categoria', 'nombre')->orderBy('nombre')->get(),
            'marcas' => Marca::select('id_marca', 'nombre')->orderBy('nombre')->get(),
        ];

        if (request()->ajax()) {
            return view('admin.reportes.index', $data);
        }

        return view('admin.reportes.full', $data);
    }

    public function generate(Request $request)
    {
        $tipo = $request->get('tipo', 'ventas');
        $viewData = $this->getReportData($request);
        $viewData['titulo'] = $this->getTitulo($tipo);

        $pdf = Pdf::loadView("admin.reportes.pdf.{$tipo}", $viewData);
        $pdf->setPaper('a4', 'landscape');

        $nombre = "reporte-{$tipo}-" . now()->format('Y-m-d-His') . '.pdf';

        return $pdf->download($nombre);
    }

    public function preview(Request $request)
    {
        $tipo = $request->get('tipo', 'ventas');
        $viewData = $this->getReportData($request);
        $viewData['titulo'] = $this->getTitulo($tipo);
        $viewData['preview'] = true;

        return view("admin.reportes.pdf.{$tipo}", $viewData);
    }

    private function getReportData(Request $request): array
    {
        $tipo = $request->get('tipo', 'ventas');
        $desde = $request->get('desde', now()->startOfMonth()->format('Y-m-d'));
        $hasta = $request->get('hasta', now()->format('Y-m-d'));

        $usuarioId = $request->get('usuario_id');
        $clienteId = $request->get('cliente_id');
        $proveedorId = $request->get('proveedor_id');
        $productoId = $request->get('producto_id');
        $categoriaId = $request->get('categoria_id');
        $marcaId = $request->get('marca_id');
        $stockMenorQue = $request->get('stock_menor_que');
        $tipoMovimiento = $request->get('tipo_movimiento');
        $cajaId = $request->get('caja_id');
        $diasVencimiento = $request->get('dias_vencimiento', 30);
        $limiteClientes = $request->get('limite_clientes', 20);
        $modulo = $request->get('modulo');
        $accion = $request->get('accion');

        $mes = $request->get('mes', now()->month);
        $anio = $request->get('anio', now()->year);

        return match ($tipo) {
            'ventas' => array_merge(
                $this->reporteService->ventas($desde, $hasta, $usuarioId, $clienteId),
                compact('usuarioId', 'clienteId')
            ),
            'atenciones' => array_merge(
                $this->reporteService->atenciones($desde, $hasta, $usuarioId, $clienteId),
                compact('usuarioId', 'clienteId')
            ),
            'ingresos' => array_merge(
                $this->reporteService->ingresos($desde, $hasta, $proveedorId, $usuarioId),
                compact('proveedorId', 'usuarioId')
            ),
            'caja' => $this->reporteService->caja($cajaId, $desde, $hasta),
            'inventario' => $this->reporteService->inventario($categoriaId, $marcaId, $stockMenorQue),
            'kardex' => $this->reporteService->kardex($productoId, $desde, $hasta, $tipoMovimiento),
            'vencimientos' => $this->reporteService->vencimientos($productoId, $diasVencimiento),
            'clientes' => $this->reporteService->clientes($desde, $hasta, $limiteClientes),
            'general' => $this->reporteService->general($mes, $anio),
            'auditoria' => $this->reporteService->auditoria($modulo, $usuarioId, $accion, $desde, $hasta),
            default => $this->reporteService->ventas($desde, $hasta),
        };
    }

    private function getTitulo(string $tipo): string
    {
        return match ($tipo) {
            'ventas' => 'Reporte de Ventas',
            'atenciones' => 'Reporte de Atenciones',
            'ingresos' => 'Reporte de Ingresos',
            'caja' => 'Reporte de Caja',
            'inventario' => 'Reporte de Inventario',
            'kardex' => 'Reporte de Kardex',
            'vencimientos' => 'Reporte de Vencimientos',
            'clientes' => 'Reporte de Clientes',
            'general' => 'Reporte General del Mes',
            'auditoria' => 'Reporte de Auditoría',
            default => 'Reporte',
        };
    }
}
