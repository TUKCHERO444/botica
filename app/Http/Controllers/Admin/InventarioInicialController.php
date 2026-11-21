<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CantidadHelper;
use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInventarioInicialRequest;
use App\Models\InventarioInicial;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Services\InventarioInicialService;
use Illuminate\Http\Request;

class InventarioInicialController extends Controller
{
    use Audita, CacheaPanel;

    public function __construct(
        private readonly InventarioInicialService $inventarioInicial
    ) {}

    public function index()
    {
        $cargar = fn () => InventarioInicial::with('user')
            ->withCount('detalles')
            ->orderByDesc('id_inventario_inicial')
            ->paginate(10)
            ->withQueryString();

        if ($this->esAjax(request())) {
            return $this->getIndex('inventario-inicial', function () use ($cargar) {
                $inventarios = $cargar();

                return view('admin.inventario-inicial.index', compact('inventarios'))->render();
            });
        }

        $inventarios = $cargar();

        return view('admin.inventario-inicial.full', compact('inventarios'));
    }

    public function create()
    {
        if (! $this->esAjax(request())) {
            return redirect()->route('admin.inventario-inicial.index');
        }

        return view('admin.inventario-inicial.create');
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
                $stock = (string) ($producto?->stock ?? 0);
                $factor = (string) $presentacion->factor;
                $costoBase = (string) ($producto?->precio_compra ?? 0);
                $costoPresentacion = CantidadHelper::redondear(
                    CantidadHelper::multiplicar($costoBase, $factor, 4),
                    2
                );
                $lotes = $producto
                    ? $producto->lotes()->where('cantidad_disponible_base', '>', 0)->count()
                    : 0;
                $elegible = CantidadHelper::comparar($stock, 0, 3) === 0 && $lotes === 0;

                return [
                    'id' => $presentacion->id_producto_presentacion,
                    'label' => trim(($producto?->nombre ?? '').' — '.$presentacion->nombre_completo, ' —'),
                    'producto' => $producto?->nombre,
                    'presentacion' => $presentacion->nombre_completo,
                    'unidad' => $producto?->unidadBase?->codigo,
                    'factor' => (float) $factor,
                    'es_base' => (bool) $presentacion->es_base,
                    'precio' => (float) $costoPresentacion,
                    'precio_formateado' => 'Costo S/ '.number_format((float) $costoPresentacion, 2),
                    'precio_venta' => (float) $presentacion->precio_venta,
                    'precio_venta_formateado' => 'S/ '.number_format((float) $presentacion->precio_venta, 2),
                    'ventaLabel' => 'S/ '.number_format((float) $presentacion->precio_venta, 2),
                    'stock' => (float) $stock,
                    'stock_formateado' => $producto?->stock_formateado ?? '',
                    'stockLabel' => $producto?->stock_formateado ?? '',
                    'lotes' => $lotes,
                    'elegible' => $elegible,
                    'paso' => 1,
                ];
            });

        return response()->json($resultados);
    }

    public function store(StoreInventarioInicialRequest $request)
    {
        try {
            $inventario = $this->inventarioInicial->crearBorrador(
                $request->validated(),
                auth()->id()
            );
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('inventario-inicial');
        $this->auditar(
            'inventario-inicial',
            'crear',
            $inventario->id_inventario_inicial,
            "Creó el inventario inicial {$inventario->correlativo}"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => "Inventario inicial {$inventario->correlativo} guardado como borrador.",
                'inventario' => $inventario,
            ]);
        }

        return redirect()->route('admin.inventario-inicial.index')
            ->with('success', 'Inventario inicial guardado como borrador.');
    }

    public function edit(InventarioInicial $inventario)
    {
        if ($inventario->estaConfirmado()) {
            return $this->error(request(), 'No se puede editar un inventario inicial confirmado.');
        }

        if (! $this->esAjax(request())) {
            return redirect()->route('admin.inventario-inicial.index');
        }

        $inventario->load([
            'detalles.producto.unidadBase',
            'detalles.presentacion.producto',
            'detalles.presentacion.presentacion',
        ]);

        return view('admin.inventario-inicial.edit', compact('inventario'));
    }

    public function update(StoreInventarioInicialRequest $request, InventarioInicial $inventario)
    {
        try {
            $inventario = $this->inventarioInicial->actualizarBorrador(
                $inventario,
                $request->validated()
            );
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('inventario-inicial');
        $this->auditar(
            'inventario-inicial',
            'actualizar',
            $inventario->id_inventario_inicial,
            "Actualizó el inventario inicial {$inventario->correlativo}"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => "Inventario inicial {$inventario->correlativo} actualizado.",
                'inventario' => $inventario,
            ]);
        }

        return redirect()->route('admin.inventario-inicial.index')
            ->with('success', 'Inventario inicial actualizado.');
    }

    public function show(InventarioInicial $inventario)
    {
        if (! $this->esAjax(request())) {
            return redirect()->route('admin.inventario-inicial.index');
        }

        $inventario->load([
            'user',
            'detalles.producto.unidadBase',
            'detalles.presentacion.presentacion',
            'detalles.lote',
        ]);

        return view('admin.inventario-inicial.show', compact('inventario'));
    }

    public function confirmar(Request $request, InventarioInicial $inventario)
    {
        try {
            $inventario = $this->inventarioInicial->confirmar($inventario, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('inventario-inicial');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('lotes');
        $this->olvidaIndex('kardex');
        $this->auditar(
            'inventario-inicial',
            'confirmar',
            $inventario->id_inventario_inicial,
            "Confirmó el inventario inicial {$inventario->correlativo}"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => "Inventario inicial {$inventario->correlativo} confirmado.",
                'inventario' => $inventario,
            ]);
        }

        return redirect()->route('admin.inventario-inicial.index')
            ->with('success', 'Inventario inicial confirmado.');
    }

    public function destroy(InventarioInicial $inventario)
    {
        if ($inventario->estaConfirmado()) {
            return $this->error(
                request(),
                'No se puede eliminar un inventario inicial confirmado. Usa un ajuste.'
            );
        }

        $inventario->delete();

        $this->olvidaIndex('inventario-inicial');
        $this->auditar(
            'inventario-inicial',
            'eliminar',
            $inventario->id_inventario_inicial,
            "Eliminó el inventario inicial {$inventario->correlativo}"
        );

        if ($this->esAjax(request())) {
            return response()->json([
                'success' => true,
                'message' => 'Inventario inicial eliminado.',
            ]);
        }

        return redirect()->route('admin.inventario-inicial.index')
            ->with('success', 'Inventario inicial eliminado.');
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        try {
            $resultado = $this->inventarioInicial->importarCsv(
                $request->file('archivo'),
                $request->boolean('dry_run'),
                auth()->id()
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errores' => [],
            ], 422);
        }

        if ($resultado['errores'] !== []) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo tiene errores. Corrige las filas indicadas.',
                'errores' => $resultado['errores'],
                'advertencias' => $resultado['advertencias'],
                'resumen' => $resultado['resumen'],
            ], 422);
        }

        if ($request->boolean('dry_run')) {
            return response()->json([
                'success' => true,
                'dry_run' => true,
                'message' => 'Validación correcta. No se guardó nada.',
                'errores' => [],
                'advertencias' => $resultado['advertencias'],
                'resumen' => $resultado['resumen'],
            ]);
        }

        $inventario = $resultado['inventario'];

        $this->olvidaIndex('inventario-inicial');
        $this->auditar(
            'inventario-inicial',
            'importar',
            $inventario->id_inventario_inicial,
            "Importó el inventario inicial {$inventario->correlativo} desde CSV"
        );

        return response()->json([
            'success' => true,
            'dry_run' => false,
            'message' => "Importación guardada como borrador {$inventario->correlativo}.",
            'errores' => [],
            'advertencias' => $resultado['advertencias'],
            'resumen' => $resultado['resumen'],
            'inventario' => $inventario,
        ]);
    }

    public function plantilla()
    {
        return response()->streamDownload(
            function () {
                echo $this->inventarioInicial->plantillaCsv();
            },
            'plantilla-inventario-inicial.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function exportarProductos()
    {
        $productos = Producto::with(['unidadBase', 'presentacionBase', 'lotes'])
            ->orderBy('nombre')
            ->get();

        return response()->streamDownload(function () use ($productos) {
            $salida = fopen('php://output', 'w');
            fputcsv($salida, [
                'nombre',
                'unidad_base',
                'presentacion_base',
                'stock_actual',
                'lotes',
                'stock_minimo',
                'precio_venta',
                'precio_compra',
            ]);

            foreach ($productos as $producto) {
                fputcsv($salida, [
                    $producto->nombre,
                    $producto->unidadBase?->codigo,
                    $producto->presentacionBase?->nombre_completo,
                    $producto->stock,
                    $producto->lotes->count(),
                    $producto->stock_minimo,
                    $producto->precio_venta,
                    $producto->precio_compra,
                ]);
            }

            fclose($salida);
        }, 'productos-para-conteo.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function error(Request $request, string $mensaje)
    {
        if ($this->esAjax($request)) {
            return response()->json([
                'success' => false,
                'message' => $mensaje,
            ], 422);
        }

        return redirect()->route('admin.inventario-inicial.index')->with('error', $mensaje);
    }

    private function esAjax(Request $request): bool
    {
        return $request->ajax() || $request->expectsJson();
    }
}
