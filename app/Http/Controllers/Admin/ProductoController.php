<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductoRequest;
use App\Http\Requests\Admin\UpdateProductoRequest;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    use CacheaPanel, Audita;

    public function __construct(
        private readonly CloudinaryService $cloudinary
    ) {}

    public function index()
    {
        $search = request('q');

        $query = Producto::with('categoria', 'marca')->orderBy('id_producto', 'desc');

        if ($search) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        if (request()->ajax()) {
            return $this->getIndex('productos', function () use ($query, $search) {
                $productos = $query->get();
                return view('admin.productos.index', compact('productos', 'search'))->render();
            });
        }

        $productos = $query->get();
        return view('admin.productos.full', compact('productos', 'search'));
    }

    public function search(Request $request)
    {
        $q = $request->get('q');
        if (!$q || strlen(trim($q)) < 2) {
            return response()->json([]);
        }

        $results = Producto::with('categoria', 'marca')
            ->where('nombre', 'like', "%{$q}%")
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id'        => $p->id_producto,
                'nombre'    => $p->nombre,
                'categoria' => $p->categoria?->nombre,
                'marca'     => $p->marca?->nombre,
                'foto_url'  => $p->foto_url,
                'edit_url'  => route('admin.productos.edit', $p),
            ]);

        return response()->json($results);
    }

    public function create()
    {
        if (request()->ajax()) {
            $categorias = Categoria::where('estado', 1)->orderBy('nombre')->get();
            $marcas = Marca::where('estado', 1)->orderBy('nombre')->get();
            return view('admin.productos.create', compact('categorias', 'marcas'));
        }

        return redirect()->route('admin.productos.index');
    }

    public function store(StoreProductoRequest $request)
    {
        $data = $request->validated();

        unset($data['imagenes'], $data['orden_imagenes']);

        $producto = Producto::create($data);
        $this->auditarCrear($producto, 'productos', "Creó el producto '{$producto->nombre}'");

        if ($request->hasFile('imagenes')) {
            $ordenes = $request->input('orden_imagenes', []);
            foreach ($request->file('imagenes') as $i => $imagen) {
                $result = $this->cloudinary->upload($imagen, 'productos');
                ProductoImagen::create([
                    'producto_id_producto' => $producto->id_producto,
                    'url' => $result['url'],
                    'public_id' => $result['public_id'],
                    'orden' => $ordenes[$i] ?? $i,
                ]);
            }
        }

        Categoria::where('id_categoria', $producto->categoria_id_categoria)->increment('cantidad');
        Marca::where('id_marca', $producto->marca_id_marca)->increment('cantidad');

        $this->olvidaIndex('productos');
        $this->olvidaIndex('categorias');
        $this->olvidaIndex('marcas');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Producto creado correctamente',
                'producto' => $producto,
            ]);
        }

        return redirect()->route('admin.productos.index')->with('success', 'Producto creado correctamente');
    }

    public function edit(Producto $producto)
    {
        if (request()->ajax()) {
            $categorias = Categoria::where('estado', 1)->orderBy('nombre')->get();
            $marcas = Marca::where('estado', 1)->orderBy('nombre')->get();
            return view('admin.productos.edit', compact('producto', 'categorias', 'marcas'));
        }

        return redirect()->route('admin.productos.index');
    }

    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $data = $request->validated();

        unset($data['imagenes'], $data['orden_imagenes'], $data['imagenes_eliminar']);

        if ($request->has('imagenes_eliminar')) {
            $eliminar = ProductoImagen::whereIn('id_producto_imagen', $request->imagenes_eliminar)
                ->where('producto_id_producto', $producto->id_producto)
                ->get();

            foreach ($eliminar as $img) {
                $publicId = $img->public_id ?? $this->cloudinary->getPublicIdFromUrl($img->url);
                $this->cloudinary->delete($publicId);
                $img->delete();
            }
        }

        if ($request->hasFile('imagenes')) {
            $ordenes = $request->input('orden_imagenes', []);
            foreach ($request->file('imagenes') as $i => $imagen) {
                $result = $this->cloudinary->upload($imagen, 'productos');
                ProductoImagen::create([
                    'producto_id_producto' => $producto->id_producto,
                    'url' => $result['url'],
                    'public_id' => $result['public_id'],
                    'orden' => $ordenes[$i] ?? $i,
                ]);
            }
        }

        if ($request->has('orden_imagenes')) {
            $ordenes = $request->input('orden_imagenes', []);
            foreach ($ordenes as $idImg => $orden) {
                if (is_numeric($idImg)) {
                    ProductoImagen::where('id_producto_imagen', $idImg)
                        ->where('producto_id_producto', $producto->id_producto)
                        ->update(['orden' => $orden]);
                }
            }
        }

        $oldCategoriaId = $producto->categoria_id_categoria;
        $oldMarcaId = $producto->marca_id_marca;

        $producto->update($data);
        $this->auditarActualizar($producto, 'productos', "Actualizó el producto '{$producto->nombre}'");

        $cambioCategoria = $oldCategoriaId != $producto->categoria_id_categoria;
        $cambioMarca = $oldMarcaId != $producto->marca_id_marca;

        if ($cambioCategoria) {
            Categoria::where('id_categoria', $oldCategoriaId)->decrement('cantidad');
            Categoria::where('id_categoria', $producto->categoria_id_categoria)->increment('cantidad');
        }
        if ($cambioMarca) {
            Marca::where('id_marca', $oldMarcaId)->decrement('cantidad');
            Marca::where('id_marca', $producto->marca_id_marca)->increment('cantidad');
        }

        $this->olvidaIndex('productos');
        if ($cambioCategoria || $cambioMarca) {
            $this->olvidaIndex('categorias');
            $this->olvidaIndex('marcas');
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado correctamente',
                'producto' => $producto,
            ]);
        }

        return redirect()->route('admin.productos.index')->with('success', 'Producto actualizado correctamente');
    }

    public function toggleStatus(Producto $producto)
    {
        $nuevoEstado = $producto->estado ? 0 : 1;
        $producto->update(['estado' => $nuevoEstado]);
        $verbo = $nuevoEstado ? 'Activó' : 'Desactivó';
        $this->auditar('productos', 'toggle-estado', $producto->id_producto, "{$verbo} el producto '{$producto->nombre}'");

        if ($nuevoEstado) {
            Categoria::where('id_categoria', $producto->categoria_id_categoria)->increment('cantidad');
            Marca::where('id_marca', $producto->marca_id_marca)->increment('cantidad');
        } else {
            Categoria::where('id_categoria', $producto->categoria_id_categoria)->decrement('cantidad');
            Marca::where('id_marca', $producto->marca_id_marca)->decrement('cantidad');
        }

        $this->olvidaIndex('productos');
        $this->olvidaIndex('categorias');
        $this->olvidaIndex('marcas');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $nuevoEstado ? 'Producto activado correctamente' : 'Producto desactivado correctamente',
            ]);
        }

        return redirect()->route('admin.productos.index');
    }

    public function destroy(Producto $producto)
    {
        $producto->update(['estado' => 0]);
        $this->auditarEliminar($producto, 'productos', "Desactivó el producto '{$producto->nombre}'");

        Categoria::where('id_categoria', $producto->categoria_id_categoria)->decrement('cantidad');
        Marca::where('id_marca', $producto->marca_id_marca)->decrement('cantidad');

        $this->olvidaIndex('productos');
        $this->olvidaIndex('categorias');
        $this->olvidaIndex('marcas');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Producto desactivado correctamente',
            ]);
        }

        return redirect()->route('admin.productos.index')->with('success', 'Producto desactivado correctamente');
    }
}
