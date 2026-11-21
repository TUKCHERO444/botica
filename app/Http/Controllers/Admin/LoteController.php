<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Controller;
use App\Models\ProductoLote;
use App\Services\InventarioService;

class LoteController extends Controller
{
    use Audita;

    public function __construct(
        private readonly InventarioService $inventario
    ) {}

    public function index()
    {
        $query = ProductoLote::with('producto.unidadBase')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id');

        if ($estado = request('estado')) {
            if ($estado === 'vencido') {
                $query->whereDate('fecha_vencimiento', '<', today())
                    ->where('cantidad_disponible_base', '>', 0);
            } elseif ($estado === 'critico') {
                $query->whereBetween('fecha_vencimiento', [today(), today()->addDays(7)])
                    ->where('cantidad_disponible_base', '>', 0);
            } elseif ($estado === 'proximo') {
                $query->whereBetween('fecha_vencimiento', [today()->addDays(8), today()->addDays(30)])
                    ->where('cantidad_disponible_base', '>', 0);
            } elseif ($estado === 'por_vencer') {
                $query->whereBetween('fecha_vencimiento', [today(), today()->addDays(30)])
                    ->where('cantidad_disponible_base', '>', 0);
            } else {
                $query->where('estado', $estado);
            }
        }

        if ($q = request('q')) {
            $query->where(function ($query) use ($q) {
                $query->where('codigo_lote', 'like', "%{$q}%")
                    ->orWhere('correlativo', 'like', "%{$q}%");
            });
        }

        $lotes = $query->paginate(10)->withQueryString();

        if (request()->ajax()) {
            return view('admin.lotes.index', compact('lotes'));
        }

        return view('admin.lotes.full', compact('lotes'));
    }

    public function baja(ProductoLote $productoLote)
    {
        try {
            $this->inventario->darDeBaja($productoLote, 'Baja por vencimiento/ajuste manual');
        } catch (\RuntimeException $e) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->auditar('lotes', 'baja', $productoLote->id, "Dio de baja el lote '{$productoLote->codigo_lote}'");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Lote dado de baja correctamente',
            ]);
        }

        return redirect()->route('admin.lotes.index')->with('success', 'Lote dado de baja correctamente');
    }
}
