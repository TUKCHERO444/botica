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
            $cajaAbierta = Caja::abierta()->first();

            return view('admin.ingresos.create', compact('proveedores', 'cajaAbierta'));
        }

        return redirect()->route('admin.ingresos.index');
    }

    public function searchPresentaciones(Request $request)
    {
        $q = trim((string) $request->get('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $resultados = ProductoPresentacion::query()
            ->with(['producto.unidadBase', 'presentacion'])
            ->where('producto_presentacion.activo', true)
            ->whereHas('producto', fn ($query) => $query->where('estado', 1))
            ->where(function ($query) use ($q) {
                $query->where('producto_presentacion.nombre', 'like', "%{$q}%")
                    ->orWhere('producto_presentacion.codigo_barras', 'like', "%{$q}%")
                    ->orWhereHas('producto', fn ($producto) => $producto->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('presentacion', fn ($presentacion) => $presentacion->where('nombre', 'like', "%{$q}%"));
            })
            ->join('producto', 'producto.id_producto', '=', 'producto_presentacion.producto_id_producto')
            ->orderBy('producto.nombre')
            ->orderBy('producto_presentacion.orden')
            ->limit(15)
            ->select('producto_presentacion.*')
            ->get()
            ->map(function (ProductoPresentacion $presentacion) {
                $producto = $presentacion->producto;
                $precioVenta = (float) $presentacion->precio_venta;
                $stock = (float) ($producto?->stock ?? 0);

                return [
                    'id' => $presentacion->id_producto_presentacion,
                    'label' => trim(($producto?->nombre ?? '').' — '.$presentacion->nombre_completo, ' —'),
                    'producto' => $producto?->nombre,
                    'presentacion' => $presentacion->nombre_completo,
                    'codigo_barras' => $presentacion->codigo_barras,
                    'unidad' => $producto?->unidadBase?->codigo,
                    'factor' => (float) $presentacion->factor,
                    'paso' => (float) ($presentacion->paso ?: 1),
                    'precio' => $precioVenta,
                    'precio_formateado' => 'S/ '.number_format($precioVenta, 2),
                    'precio_venta' => $precioVenta,
                    'precio_venta_formateado' => 'S/ '.number_format($precioVenta, 2),
                    'ventaLabel' => 'S/ '.number_format($precioVenta, 2),
                    'stock' => $stock,
                    'stock_formateado' => $producto?->stock_formateado ?? '',
                    'stockLabel' => $producto?->stock_formateado ?? '',
                ];
            });

        return response()->json($resultados);
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
            $cajaAbierta = Caja::abierta()->first();
            $ingreso->load(['proveedor', 'detalles.presentacion.presentacion', 'detalles.producto.unidadBase']);

            return view('admin.ingresos.edit', compact(
                'ingreso',
                'proveedores',
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
