<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnularDevolucionProveedorRequest;
use App\Http\Requests\Admin\StoreDevolucionProveedorRequest;
use App\Models\Caja;
use App\Models\DevolucionProveedor;
use App\Models\Ingreso;
use App\Services\DevolucionProveedorService;
use Illuminate\Http\Request;

class DevolucionProveedorController extends Controller
{
    use Audita, CacheaPanel;

    public function __construct(
        private readonly DevolucionProveedorService $devoluciones
    ) {}

    public function index()
    {
        $cargar = fn () => DevolucionProveedor::with('proveedor', 'ingreso', 'user')
            ->withCount('detalles')
            ->orderByDesc('id_devolucion_proveedor')
            ->paginate(10)
            ->withQueryString();

        if ($this->esAjax(request())) {
            return $this->getIndex('devoluciones', function () use ($cargar) {
                $devoluciones = $cargar();

                return view('admin.devoluciones.index', compact('devoluciones'))->render();
            });
        }

        $devoluciones = $cargar();

        return view('admin.devoluciones.full', compact('devoluciones'));
    }

    public function create(Request $request)
    {
        if (! $this->esAjax($request)) {
            return redirect()->route('admin.devoluciones.index');
        }

        $ingresos = Ingreso::with('proveedor')
            ->where('estado', Ingreso::ESTADO_RECIBIDA)
            ->orderByDesc('fecha')
            ->orderByDesc('id_ingreso')
            ->get();

        $ingreso = null;

        if ($request->filled('ingreso_id')) {
            $ingreso = Ingreso::with([
                'proveedor',
                'detalles.producto.unidadBase',
                'detalles.producto.presentaciones.presentacion',
                'detalles.presentacion.presentacion',
                'detalles.loteIngreso',
                'detalles.lote',
            ])
                ->where('estado', Ingreso::ESTADO_RECIBIDA)
                ->findOrFail($request->get('ingreso_id'));
        }

        $cajaAbierta = Caja::abierta()->first();

        return view('admin.devoluciones.create', compact('ingresos', 'ingreso', 'cajaAbierta'));
    }

    public function store(StoreDevolucionProveedorRequest $request)
    {
        try {
            $devolucion = $this->devoluciones->crear($request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('devoluciones');
        $this->olvidaIndex('ingresos');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarCrear(
            $devolucion,
            'devoluciones',
            "Registró la devolución {$devolucion->correlativo} al proveedor {$devolucion->proveedor->razon_social}"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => "Devolución registrada correctamente ({$devolucion->correlativo}).",
                'devolucion' => $devolucion,
            ]);
        }

        return redirect()->route('admin.devoluciones.index')
            ->with('success', 'Devolución registrada correctamente.');
    }

    public function show(DevolucionProveedor $devolucion)
    {
        $devolucion->load([
            'proveedor',
            'ingreso',
            'user',
            'detalles.producto',
            'detalles.presentacion.presentacion',
            'detalles.lote',
        ]);

        if ($this->esAjax(request())) {
            return view('admin.devoluciones.show', compact('devolucion'));
        }

        return redirect()->route('admin.devoluciones.index');
    }

    public function formAnular(DevolucionProveedor $devolucion)
    {
        if (! $devolucion->estaRegistrada()) {
            return $this->error(request(), 'La devolución ya fue anulada.');
        }

        if (! $this->esAjax(request())) {
            return redirect()->route('admin.devoluciones.index');
        }

        $devolucion->load('proveedor', 'ingreso');

        return view('admin.devoluciones.anular', compact('devolucion'));
    }

    public function anular(AnularDevolucionProveedorRequest $request, DevolucionProveedor $devolucion)
    {
        try {
            $devolucion = $this->devoluciones->anular(
                $devolucion,
                $request->validated()['motivo_anulacion']
            );
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('devoluciones');
        $this->olvidaIndex('ingresos');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarActualizar(
            $devolucion,
            'devoluciones',
            "Anuló la devolución {$devolucion->correlativo}: {$devolucion->motivo_anulacion}"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Devolución anulada y stock restaurado correctamente.',
                'devolucion' => $devolucion,
            ]);
        }

        return redirect()->route('admin.devoluciones.index')
            ->with('success', 'Devolución anulada correctamente.');
    }

    private function error(Request $request, string $message, int $status = 422)
    {
        if ($this->esAjax($request)) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return redirect()->back()->with('error', $message)->withInput();
    }

    private function esAjax(Request $request): bool
    {
        return $request->ajax() || $request->expectsJson();
    }
}
