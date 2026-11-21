<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnularCompraRequest;
use App\Http\Requests\Admin\RecibirCompraRequest;
use App\Http\Requests\Admin\StoreIngresoRequest;
use App\Http\Requests\Admin\UpdateIngresoRequest;
use App\Models\Caja;
use App\Models\Ingreso;
use App\Models\ProductoPresentacion;
use App\Models\Proveedor;
use App\Services\CompraService;
use Illuminate\Http\Request;

class IngresoController extends Controller
{
    use Audita, CacheaPanel;

    public function __construct(
        private readonly CompraService $compras
    ) {}

    public function index()
    {
        $cargar = fn () => Ingreso::with('proveedor', 'user')
            ->withCount('detalles')
            ->orderBy('id_ingreso', 'desc')
            ->paginate(10)
            ->withQueryString();

        if ($this->esAjax(request())) {
            return $this->getIndex('ingresos', function () use ($cargar) {
                $ingresos = $cargar();

                return view('admin.ingresos.index', compact('ingresos'))->render();
            });
        }

        $ingresos = $cargar();

        return view('admin.ingresos.full', compact('ingresos'));
    }

    public function show(Ingreso $ingreso)
    {
        $ingreso->load([
            'proveedor',
            'user',
            'recepcionUser',
            'detalles.producto.unidadBase',
            'detalles.presentacion.presentacion',
        ]);

        if ($this->esAjax(request())) {
            return view('admin.ingresos.show', compact('ingreso'));
        }

        return redirect()->route('admin.ingresos.index');
    }

    public function create()
    {
        if ($this->esAjax(request())) {
            $proveedores = Proveedor::where('estado', 1)->orderBy('razon_social')->get();
            $presentaciones = $this->presentacionesDisponibles();
            $cajaAbierta = Caja::abierta()->first();

            return view('admin.ingresos.create', compact('proveedores', 'presentaciones', 'cajaAbierta'));
        }

        return redirect()->route('admin.ingresos.index');
    }

    public function store(StoreIngresoRequest $request)
    {
        try {
            $ingreso = $this->compras->crear($request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('ingresos');
        $this->auditarCrear($ingreso, 'ingresos', "Guardó la compra {$ingreso->numero_documento} como borrador");

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Compra guardada como borrador. Recíbela para ingresar el stock.',
                'ingreso' => $ingreso,
            ]);
        }

        return redirect()->route('admin.ingresos.index')
            ->with('success', 'Compra guardada como borrador.');
    }

    public function edit(Ingreso $ingreso)
    {
        if (! $ingreso->estaEnBorrador()) {
            return $this->error(request(), 'Solo se puede editar una compra en estado borrador.');
        }

        if ($this->esAjax(request())) {
            $proveedores = Proveedor::where('estado', 1)->orderBy('razon_social')->get();
            $presentaciones = $this->presentacionesDisponibles(false);
            $cajaAbierta = Caja::abierta()->first();
            $ingreso->load(['proveedor', 'detalles.presentacion.presentacion', 'detalles.producto.unidadBase']);

            return view('admin.ingresos.edit', compact(
                'ingreso',
                'proveedores',
                'presentaciones',
                'cajaAbierta'
            ));
        }

        return redirect()->route('admin.ingresos.index');
    }

    public function update(UpdateIngresoRequest $request, Ingreso $ingreso)
    {
        try {
            $ingreso = $this->compras->actualizar($ingreso, $request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('ingresos');
        $this->auditarActualizar($ingreso, 'ingresos', "Actualizó la compra {$ingreso->numero_documento}");

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Compra actualizada correctamente.',
                'ingreso' => $ingreso,
            ]);
        }

        return redirect()->route('admin.ingresos.index')
            ->with('success', 'Compra actualizada correctamente.');
    }

    public function destroy(Ingreso $ingreso)
    {
        try {
            $this->compras->eliminar($ingreso);
        } catch (\RuntimeException $e) {
            return $this->error(request(), $e->getMessage());
        }

        $this->olvidaIndex('ingresos');
        $this->auditarEliminar($ingreso, 'ingresos', "Eliminó el borrador de compra {$ingreso->numero_documento}");

        if ($this->esAjax(request())) {
            return response()->json([
                'success' => true,
                'message' => 'Borrador de compra eliminado correctamente.',
            ]);
        }

        return redirect()->route('admin.ingresos.index')
            ->with('success', 'Borrador de compra eliminado correctamente.');
    }

    public function formRecibir(Ingreso $ingreso)
    {
        if (! $ingreso->estaEnBorrador()) {
            return $this->error(request(), 'Solo se puede recibir una compra en estado borrador.');
        }

        if (! $this->esAjax(request())) {
            return redirect()->route('admin.ingresos.index');
        }

        $ingreso->load(['proveedor', 'detalles.producto.unidadBase', 'detalles.presentacion.presentacion']);
        $cajaAbierta = Caja::abierta()->first();

        return view('admin.ingresos.recibir', compact('ingreso', 'cajaAbierta'));
    }

    public function recibir(RecibirCompraRequest $request, Ingreso $ingreso)
    {
        try {
            $ingreso = $this->compras->recibir($ingreso, $request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('ingresos');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarActualizar(
            $ingreso,
            'ingresos',
            "Recibió la compra {$ingreso->correlativo} e ingresó el stock"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => "Compra recibida correctamente ({$ingreso->correlativo}).",
                'ingreso' => $ingreso,
            ]);
        }

        return redirect()->route('admin.ingresos.index')
            ->with('success', 'Compra recibida correctamente.');
    }

    public function formAnular(Ingreso $ingreso)
    {
        if (! $ingreso->estaRecibida()) {
            return $this->error(request(), 'Solo se puede anular una compra recibida.');
        }

        if (! $this->esAjax(request())) {
            return redirect()->route('admin.ingresos.index');
        }

        $ingreso->load('proveedor');

        return view('admin.ingresos.anular', compact('ingreso'));
    }

    public function anular(AnularCompraRequest $request, Ingreso $ingreso)
    {
        try {
            $ingreso = $this->compras->anular($ingreso, $request->validated()['motivo_anulacion']);
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('ingresos');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarActualizar(
            $ingreso,
            'ingresos',
            "Anuló la compra {$ingreso->correlativo}: {$ingreso->motivo_anulacion}"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Compra anulada y stock revertido correctamente.',
                'ingreso' => $ingreso,
            ]);
        }

        return redirect()->route('admin.ingresos.index')
            ->with('success', 'Compra anulada correctamente.');
    }

    private function presentacionesDisponibles(bool $soloConProductosActivos = true)
    {
        return ProductoPresentacion::with(['producto.unidadBase', 'presentacion'])
            ->where('activo', true)
            ->when($soloConProductosActivos, function ($q) {
                $q->whereHas('producto', fn ($p) => $p->where('estado', 1));
            })
            ->join('producto', 'producto.id_producto', '=', 'producto_presentacion.producto_id_producto')
            ->orderBy('producto.nombre')
            ->select('producto_presentacion.*')
            ->get();
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
