<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReporteRequest;
use App\Models\AjusteInventario;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\ReporteCsvService;
use App\Services\ReporteService;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    public function __construct(
        private ReporteService $reporteService,
        private ReporteCsvService $reporteCsv
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
            'metodosPago' => MetodoPago::orderBy('nombre')->get(),
            'tiposAjuste' => AjusteInventario::TIPOS,
        ];

        if (request()->ajax()) {
            return view('admin.reportes.index', $data);
        }

        return view('admin.reportes.full', $data);
    }

    public function generate(ReporteRequest $request)
    {
        $tipo = $request->validated('tipo');
        $viewData = $this->getReportData($request);
        $viewData['titulo'] = $this->getTitulo($tipo);

        if ($request->input('formato') === 'csv') {
            return $this->descargarCsv($tipo, $viewData);
        }

        $pdf = Pdf::loadView("admin.reportes.pdf.{$tipo}", $viewData);
        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isPhpEnabled', true);

        $nombre = "reporte-{$tipo}-".now()->format('Y-m-d-His').'.pdf';

        return $pdf->download($nombre);
    }

    private function descargarCsv(string $tipo, array $viewData)
    {
        [$encabezados, $filas] = $this->reporteCsv->generar($tipo, $viewData);

        $nombre = "reporte-{$tipo}-".now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($encabezados, $filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $encabezados);
            foreach ($filas as $fila) {
                fputcsv($salida, $fila);
            }
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(ReporteRequest $request)
    {
        $tipo = $request->validated('tipo');
        $viewData = $this->getReportData($request);
        $viewData['titulo'] = $this->getTitulo($tipo);
        $viewData['preview'] = true;

        return view("admin.reportes.pdf.{$tipo}", $viewData);
    }

    private function getReportData(ReporteRequest $request): array
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
        $tipoEspecial = $request->get('tipo_especial');
        $diasVencimiento = $request->get('dias_vencimiento');
        $cajaId = $request->get('caja_id');
        $limiteClientes = $request->get('limite_clientes', 20);
        $modulo = $request->get('modulo');
        $accion = $request->get('accion');
        $pago = $request->get('pago');
        $soloActivos = $request->boolean('solo_activos', true);
        $metodoPagoId = $request->get('metodo_pago_id');
        $tipoPago = $request->get('tipo_pago');
        $agrupar = $request->get('agrupar', 'categoria');
        $segmento = $request->get('segmento');
        $limiteRfm = $request->get('limite_rfm', 100);
        $diasRotacion = $request->get('dias_rotacion', 90);
        $diasCompra = $request->get('dias_compra', 90);
        $diasCumpleanos = $request->get('dias_cumpleanos', 30);
        $tipoAjuste = $request->get('tipo_ajuste');

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
                $this->reporteService->ingresos($desde, $hasta, $proveedorId, $usuarioId, $pago),
                compact('proveedorId', 'usuarioId', 'pago')
            ),
            'caja' => $this->reporteService->caja($cajaId, $desde, $hasta),
            'inventario' => $this->reporteService->inventario($categoriaId, $marcaId, $stockMenorQue, $soloActivos),
            'lotes' => array_merge(
                $this->reporteService->lotes($categoriaId, $marcaId, $diasVencimiento),
                compact('categoriaId', 'marcaId', 'diasVencimiento')
            ),
            'movimientos' => array_merge(
                $this->reporteService->movimientos($desde, $hasta, $tipoEspecial, $productoId, $usuarioId),
                compact('tipoEspecial', 'productoId', 'usuarioId')
            ),
            'rentabilidad' => array_merge(
                $this->reporteService->rentabilidad($desde, $hasta, $categoriaId, $marcaId),
                compact('categoriaId', 'marcaId')
            ),
            'kardex' => $this->reporteService->kardex($productoId, $desde, $hasta, $tipoMovimiento),
            'clientes' => $this->reporteService->clientes($desde, $hasta, $limiteClientes),
            'pagos' => array_merge(
                $this->reporteService->pagos($desde, $hasta, $metodoPagoId, $tipoPago),
                compact('metodoPagoId', 'tipoPago')
            ),
            'ventas_producto' => array_merge(
                $this->reporteService->ventasProducto($desde, $hasta, $categoriaId, $marcaId),
                compact('categoriaId', 'marcaId')
            ),
            'ventas_diario' => array_merge(
                $this->reporteService->ventasDiario($desde, $hasta, $usuarioId),
                compact('usuarioId')
            ),
            'ventas_vendedor' => $this->reporteService->ventasVendedor($desde, $hasta),
            'anulaciones' => array_merge(
                $this->reporteService->anulaciones($desde, $hasta, $usuarioId),
                compact('usuarioId')
            ),
            'compras_proveedor' => $this->reporteService->comprasPorProveedor($desde, $hasta),
            'costos_producto' => array_merge(
                $this->reporteService->evolucionCostos($desde, $hasta, $proveedorId, $productoId),
                compact('proveedorId', 'productoId')
            ),
            'devoluciones_proveedor' => array_merge(
                $this->reporteService->devolucionesProveedor($desde, $hasta, $proveedorId),
                compact('proveedorId')
            ),
            'merma' => array_merge(
                $this->reporteService->merma($desde, $hasta, $tipoAjuste),
                compact('tipoAjuste')
            ),
            'rotacion' => array_merge(
                $this->reporteService->rotacion($categoriaId, $marcaId, $diasRotacion),
                compact('categoriaId', 'marcaId', 'diasRotacion')
            ),
            'margen_agrupado' => array_merge(
                $this->reporteService->margenAgrupado($desde, $hasta, $agrupar),
                compact('agrupar')
            ),
            'servicios' => $this->reporteService->servicios($desde, $hasta),
            'clientes_rfm' => array_merge(
                $this->reporteService->clientesRfm($desde, $hasta, $limiteRfm, $segmento),
                compact('limiteRfm', 'segmento')
            ),
            'clientes_web' => $this->reporteService->clientesWeb($desde, $hasta),
            'cumpleanos' => $this->reporteService->cumpleanos($diasCumpleanos),
            'sugerido_compra' => array_merge(
                $this->reporteService->sugeridoCompra($categoriaId, $marcaId, $diasCompra),
                compact('categoriaId', 'marcaId', 'diasCompra')
            ),
            'caja_origen' => $this->reporteService->cajaPorOrigen($desde, $hasta, $cajaId),
            'resumen_gerencial' => $this->reporteService->resumenGerencial($desde, $hasta),
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
            'ingresos' => 'Reporte de Compras',
            'caja' => 'Reporte de Caja',
            'inventario' => 'Reporte de Inventario',
            'lotes' => 'Reporte de Lotes y Costos',
            'movimientos' => 'Reporte de Movimientos de Inventario',
            'rentabilidad' => 'Reporte de Rentabilidad',
            'kardex' => 'Reporte de Kardex',
            'clientes' => 'Reporte de Clientes',
            'pagos' => 'Reporte de Cobros por Método de Pago',
            'ventas_producto' => 'Reporte de Ventas por Producto',
            'ventas_diario' => 'Reporte Diario de Ventas',
            'ventas_vendedor' => 'Reporte de Ventas por Vendedor y Hora',
            'anulaciones' => 'Reporte de Anulaciones de Venta',
            'compras_proveedor' => 'Reporte de Compras por Proveedor',
            'costos_producto' => 'Reporte de Evolución de Costos',
            'devoluciones_proveedor' => 'Reporte de Devoluciones a Proveedor',
            'merma' => 'Reporte de Ajustes y Merma',
            'rotacion' => 'Reporte de Rotación y Cobertura',
            'margen_agrupado' => 'Reporte de Margen Agrupado',
            'servicios' => 'Reporte de Servicios',
            'clientes_rfm' => 'Reporte de Clientes RFM',
            'clientes_web' => 'Reporte de Clientes Nuevos y Adopción Web',
            'cumpleanos' => 'Reporte de Cumpleaños',
            'sugerido_compra' => 'Reporte de Sugerido de Compra',
            'caja_origen' => 'Reporte de Caja por Origen',
            'resumen_gerencial' => 'Resumen Gerencial',
            'general' => 'Reporte General del Mes',
            'auditoria' => 'Reporte de Auditoría',
            default => 'Reporte',
        };
    }
}
