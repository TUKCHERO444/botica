<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AsignarSliderRequest;
use App\Models\Producto;
use App\Models\SliderProducto;

class SliderController extends Controller
{
    use Audita;

    public function index()
    {
        if (request()->ajax()) {
            return view('admin.sliders.index');
        }

        return view('admin.sliders.index');
    }

    public function listar(string $tipo)
    {
        $items = SliderProducto::where('slider_tipo', $tipo)
            ->orderBy('orden')
            ->with('producto.categoria')
            ->get();

        return view('admin.sliders._lista', compact('items', 'tipo'));
    }

    public function asignar(AsignarSliderRequest $request, string $tipo)
    {
        $existe = SliderProducto::where('slider_tipo', $tipo)
            ->where('producto_id_producto', $request->producto_id)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'El producto ya está asignado a este slider',
            ], 422);
        }

        $maxOrden = SliderProducto::where('slider_tipo', $tipo)->max('orden') ?? 0;

        SliderProducto::create([
            'slider_tipo'          => $tipo,
            'producto_id_producto' => $request->producto_id,
            'orden'                => $maxOrden + 1,
        ]);

        $this->auditar('sliders', 'crear', $request->producto_id, "Asignó producto #{$request->producto_id} al slider '{$tipo}'");

        return response()->json(['success' => true, 'message' => 'Producto asignado correctamente']);
    }

    public function quitar(string $tipo, Producto $producto)
    {
        SliderProducto::where('slider_tipo', $tipo)
            ->where('producto_id_producto', $producto->id_producto)
            ->delete();

        $this->auditar('sliders', 'eliminar', $producto->id_producto, "Removió producto #{$producto->id_producto} del slider '{$tipo}'");

        return response()->json(['success' => true, 'message' => 'Producto removido del slider']);
    }

    public function reordenar(string $tipo)
    {
        $items = request()->validate([
            'items'    => 'required|array',
            'items.*'  => 'required|array',
            'items.*.producto_id' => 'required|integer|exists:producto,id_producto',
            'items.*.orden'       => 'required|integer|min:0',
        ]);

        foreach ($items['items'] as $item) {
            SliderProducto::where('slider_tipo', $tipo)
                ->where('producto_id_producto', $item['producto_id'])
                ->update(['orden' => $item['orden']]);
        }

        return response()->json(['success' => true, 'message' => 'Orden actualizado correctamente']);
    }

    public function buscarProductos()
    {
        $q = request()->get('q', '');

        $productos = Producto::where('estado', 1)
            ->where('nombre', 'like', "%{$q}%")
            ->with('categoria')
            ->limit(20)
            ->get()
            ->map(fn($p) => [
                'id_producto'  => $p->id_producto,
                'nombre'       => $p->nombre,
                'precio_venta' => $p->precio_venta,
                'foto_url'     => $p->foto_url,
                'categoria'    => $p->categoria?->nombre,
            ]);

        return response()->json($productos);
    }
}
