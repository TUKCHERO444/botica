<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAjusteInventarioRequest;
use App\Models\AjusteInventario;
use App\Models\ProductoLote;
use App\Services\AjusteInventarioService;
use Illuminate\Http\Request;

class AjusteInventarioController extends Controller
{
    use Audita, CacheaPanel;

    public function __construct(
        private readonly AjusteInventarioService $ajustes
    ) {}

    public function index()
    {
        $cargar = fn () => AjusteInventario::with('user')
            ->withCount('detalles')
            ->orderByDesc('id_ajuste_inventario')
            ->paginate(10)
            ->withQueryString();

        if ($this->esAjax(request())) {
            return $this->getIndex('ajustes', function () use ($cargar) {
                $ajustes = $cargar();

                return view('admin.ajustes.index', compact('ajustes'))->render();
            });
        }

        $ajustes = $cargar();

        return view('admin.ajustes.full', compact('ajustes'));
    }

    public function create()
    {
        if (! $this->esAjax(request())) {
            return redirect()->route('admin.ajustes.index');
        }

        $lotes = ProductoLote::with(['producto.unidadBase', 'producto.presentaciones.presentacion'])
            ->orderBy('producto_id')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->get();

        return view('admin.ajustes.create', compact('lotes'));
    }

    public function store(StoreAjusteInventarioRequest $request)
    {
        try {
            $ajuste = $this->ajustes->crear($request->validated());
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('ajustes');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('lotes');
        $this->olvidaIndex('kardex');
        $this->auditarCrear(
            $ajuste,
            'ajustes',
            "Registró el ajuste {$ajuste->correlativo} ({$ajuste->tipo_label})"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => "Ajuste registrado correctamente ({$ajuste->correlativo}).",
                'ajuste' => $ajuste,
            ]);
        }

        return redirect()->route('admin.ajustes.index')
            ->with('success', 'Ajuste registrado correctamente.');
    }

    public function show(AjusteInventario $ajuste)
    {
        $ajuste->load([
            'user',
            'detalles.producto.unidadBase',
            'detalles.producto.presentaciones.presentacion',
            'detalles.lote',
            'detalles.presentacion',
        ]);

        if ($this->esAjax(request())) {
            return view('admin.ajustes.show', compact('ajuste'));
        }

        return redirect()->route('admin.ajustes.index');
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
