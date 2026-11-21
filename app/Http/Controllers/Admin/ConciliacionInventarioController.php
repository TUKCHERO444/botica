<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Models\ConciliacionInventario;
use App\Services\ConciliacionInventarioService;
use Illuminate\Http\Request;

class ConciliacionInventarioController extends Controller
{
    use Audita, CacheaPanel;

    public function __construct(
        private readonly ConciliacionInventarioService $conciliaciones
    ) {}

    public function index()
    {
        $cargar = fn () => ConciliacionInventario::with('user')
            ->withCount('detalles')
            ->orderByDesc('id_conciliacion_inventario')
            ->paginate(10)
            ->withQueryString();

        if ($this->esAjax(request())) {
            return $this->getIndex('conciliaciones', function () use ($cargar) {
                $conciliaciones = $cargar();

                return view('admin.conciliaciones.index', compact('conciliaciones'))->render();
            });
        }

        $conciliaciones = $cargar();

        return view('admin.conciliaciones.full', compact('conciliaciones'));
    }

    public function show(ConciliacionInventario $conciliacion)
    {
        $conciliacion->load([
            'user',
            'detalles.producto',
            'detalles.detallesLote.lote',
        ]);

        if ($this->esAjax(request())) {
            return view('admin.conciliaciones.show', compact('conciliacion'));
        }

        return redirect()->route('admin.conciliaciones.index');
    }

    public function ejecutar(Request $request)
    {
        try {
            $resultado = $this->conciliaciones->ejecutar(
                auth()->id(),
                ConciliacionInventario::ORIGEN_MANUAL,
                true
            );
        } catch (\RuntimeException $e) {
            if ($this->esAjax($request)) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->route('admin.conciliaciones.index')->with('error', $e->getMessage());
        }

        $conciliacion = $resultado['conciliacion'];

        $this->olvidaIndex('conciliaciones');
        $this->auditar(
            'conciliaciones',
            'crear',
            $conciliacion->id_conciliacion_inventario,
            "Ejecutó una conciliación de inventario ({$conciliacion->estado})"
        );

        $conDiferencia = (int) $resultado['productos_con_diferencia'];
        $mensaje = $resultado['estado'] === ConciliacionInventario::ESTADO_OK
            ? 'Conciliación completada sin diferencias.'
            : 'Conciliación completada: '.$conDiferencia.' '.($conDiferencia === 1 ? 'producto con diferencia.' : 'productos con diferencia.');

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'conciliacion' => $conciliacion,
            ]);
        }

        return redirect()->route('admin.conciliaciones.index')->with('success', $mensaje);
    }

    private function esAjax(Request $request): bool
    {
        return $request->ajax() || $request->expectsJson();
    }
}
