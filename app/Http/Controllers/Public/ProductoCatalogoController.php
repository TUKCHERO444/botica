<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;

class ProductoCatalogoController extends Controller
{
    public function index()
    {
        $categorias = Categoria::where('estado', 1)
            ->orderBy('nombre')
            ->get()
            ->each(function ($cat) {
                $cat->setAttribute('productos_count', $cat->productos()->visible()->count());
            });

        $marcas = Marca::where('estado', 1)
            ->orderBy('nombre')
            ->get()
            ->each(function ($marca) {
                $marca->setAttribute('productos_count', $marca->productos()->visible()->count());
            });

        $selectedCategorias = request()->input('categorias', []);
        $selectedMarcas = request()->input('marcas', []);
        $selectedPrecioMax = request()->input('precio_max');

        $precios = Producto::visible()
            ->selectRaw('MIN(precio_venta) as precio_min, MAX(precio_venta) as precio_max')
            ->first();

        $precioMin = (int) floor($precios->precio_min);
        $precioMax = (int) ceil($precios->precio_max);

        $productos = Producto::visible()
            ->when($selectedCategorias, function ($q, $cats) {
                $q->whereIn('categoria_id_categoria', $cats);
            })
            ->when($selectedMarcas, function ($q, $marcas) {
                $q->whereIn('marca_id_marca', $marcas);
            })
            ->when($selectedPrecioMax, function ($q, $max) {
                $q->where('precio_venta', '<=', (float) $max);
            })
            ->with('categoria', 'marca', 'presentaciones')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        if (request()->ajax()) {
            return view('public.productos._grid', compact('productos'));
        }

        return view('public.productos.index', compact(
            'productos', 'categorias', 'marcas',
            'selectedCategorias', 'selectedMarcas', 'selectedPrecioMax',
            'precioMin', 'precioMax'
        ));
    }

    public function buscar()
    {
        $q = request()->input('q');

        if (! $q || strlen(trim($q)) < 2) {
            return response()->json([]);
        }

        $productos = Producto::visible()
            ->where('nombre', 'like', "%{$q}%")
            ->with('categoria', 'presentaciones')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id_producto,
                    'nombre' => $p->nombre,
                    'precio' => (float) $p->precio_desde,
                    'foto_url' => $p->foto_url,
                    'categoria' => $p->categoria?->nombre,
                    'url' => route('public.productos.show', $p),
                ];
            });

        return response()->json($productos);
    }

    public function show(Producto $producto)
    {
        abort_unless(
            $producto->estado === 1,
            404,
            'Producto no disponible'
        );

        $producto->load('categoria', 'marca', 'presentaciones.presentacion');

        $relacionados = Producto::visible()
            ->where('id_producto', '!=', $producto->id_producto)
            ->where('categoria_id_categoria', $producto->categoria_id_categoria)
            ->with('presentaciones')
            ->inRandomOrder()
            ->limit(8)
            ->get();

        return view('public.productos.show', compact('producto', 'relacionados'));
    }
}
