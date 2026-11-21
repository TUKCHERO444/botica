<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductoRequest;
use App\Http\Requests\Admin\UpdateProductoRequest;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    use Audita, CacheaPanel;

    public function __construct(
        private readonly CloudinaryService $cloudinary
    ) {}

    public function index()
    {
        $search = request('q');
        $alerta = request('alerta');
        $alerta = in_array($alerta, ['inventario', 'bajo', 'agotado', 'por_vencer'], true) ? $alerta : null;

        $query = Producto::with('categoria', 'marca', 'unidadBase', 'presentaciones.presentacion')
            ->conStockVendibleCalculado()
            ->withCount([
                'lotes',
                'lotes as lotes_por_vencer_count' => fn ($lotes) => $lotes
                    ->where('estado', 'disponible')
                    ->where('cantidad_disponible_base', '>', 0)
                    ->whereDate('fecha_vencimiento', '>=', today()->toDateString())
                    ->whereDate('fecha_vencimiento', '<=', today()->addDays(30)->toDateString()),
            ])
            ->orderBy('id_producto', 'desc');

        if ($search) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        if ($alerta) {
            $query->where('estado', 1)->conAlertaStock($alerta);
        }

        if (request()->ajax()) {
            return $this->getIndex('productos', function () use ($query, $search, $alerta) {
                $productos = $query->paginate(10)->withQueryString();

                return view('admin.productos.index', compact('productos', 'search', 'alerta'))->render();
            });
        }

        $productos = $query->paginate(10)->withQueryString();

        return view('admin.productos.full', compact('productos', 'search', 'alerta'));
    }

    public function search(Request $request)
    {
        $q = $request->get('q');
        if (! $q || strlen(trim($q)) < 2) {
            return response()->json([]);
        }

        $results = Producto::with('categoria', 'marca')
            ->where('nombre', 'like', "%{$q}%")
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id_producto,
                'nombre' => $p->nombre,
                'categoria' => $p->categoria?->nombre,
                'marca' => $p->marca?->nombre,
                'foto_url' => $p->foto_url,
                'edit_url' => route('admin.productos.edit', $p),
            ]);

        return response()->json($results);
    }

    public function create()
    {
        if (request()->ajax()) {
            $categorias = Categoria::where('estado', 1)->orderBy('nombre')->get();
            $marcas = Marca::where('estado', 1)->orderBy('nombre')->get();
            $unidades = UnidadMedida::where('activo', true)->orderBy('nombre')->get();
            $presentaciones = Presentacion::where('activo', true)->orderBy('nombre')->get();

            return view('admin.productos.create', compact(
                'categorias',
                'marcas',
                'unidades',
                'presentaciones'
            ));
        }

        return redirect()->route('admin.productos.index');
    }

    public function store(StoreProductoRequest $request)
    {
        $data = $request->validated();
        $presentaciones = $data['presentaciones'] ?? [];

        unset($data['imagenes'], $data['orden_imagenes'], $data['presentaciones']);

        // El producto nace sin stock. Las existencias iniciales se cargan
        // únicamente desde el módulo de inventario inicial.
        $data['stock'] = 0;
        $data['inventario'] = 0;

        $basePaso = $data['presentacion_base_paso'] ?? 1;
        unset($data['presentacion_base_paso']);

        $unidad = UnidadMedida::findOrFail($data['unidad_base_id']);

        $producto = DB::transaction(function () use ($data, $presentaciones, $unidad, $basePaso) {
            $producto = Producto::create($data);

            $this->crearPresentacionBase($producto, $unidad, $basePaso);
            $this->sincronizarPresentaciones($producto, $presentaciones, $unidad);

            return $producto;
        });

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
                'producto' => $producto->load('presentaciones.presentacion'),
            ]);
        }

        return redirect()->route('admin.productos.index')->with('success', 'Producto creado correctamente');
    }

    public function edit(Producto $producto)
    {
        if (request()->ajax()) {
            $categorias = Categoria::where('estado', 1)->orderBy('nombre')->get();
            $marcas = Marca::where('estado', 1)->orderBy('nombre')->get();
            $unidades = UnidadMedida::where('activo', true)->orderBy('nombre')->get();
            $presentaciones = Presentacion::where('activo', true)->orderBy('nombre')->get();

            $producto->load('presentaciones.presentacion');

            return view('admin.productos.edit', compact(
                'producto',
                'categorias',
                'marcas',
                'unidades',
                'presentaciones'
            ));
        }

        return redirect()->route('admin.productos.index');
    }

    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        $data = $request->validated();
        $presentaciones = $data['presentaciones'] ?? [];

        unset($data['imagenes'], $data['orden_imagenes'], $data['imagenes_eliminar'], $data['presentaciones']);

        // El stock solo se modifica por ingresos, ventas, atenciones o ajustes de lote.
        unset($data['stock'], $data['inventario']);

        $basePaso = $data['presentacion_base_paso'] ?? ($producto->presentacionBase?->paso ?? 1);
        unset($data['presentacion_base_paso']);

        $unidad = UnidadMedida::findOrFail($data['unidad_base_id']);

        $oldCategoriaId = $producto->categoria_id_categoria;
        $oldMarcaId = $producto->marca_id_marca;

        DB::transaction(function () use ($producto, $data, $presentaciones, $unidad, $basePaso) {
            $baseId = (int) $unidad->presentacion_base_id;
            $base = $producto->presentaciones()->where('es_base', true)->first();

            if ($base && (int) $base->presentacion_id !== $baseId) {
                $conflicto = $producto->presentaciones()
                    ->where('es_base', false)
                    ->where('presentacion_id', $baseId)
                    ->first();

                if ($conflicto) {
                    if ($this->presentacionTieneReferencias($conflicto)) {
                        throw ValidationException::withMessages([
                            'unidad_base_id' => 'No se puede cambiar la unidad base: ya existe una presentación con esa unidad y tiene movimientos asociados.',
                        ]);
                    }

                    $conflicto->delete();
                }
            }

            $producto->update($data);
            $this->auditarActualizar($producto, 'productos', "Actualizó el producto '{$producto->nombre}'");

            $this->crearPresentacionBase($producto->fresh(), $unidad, $basePaso);
            $this->sincronizarPresentaciones($producto->fresh(), $presentaciones, $unidad);
        });

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

        $producto->refresh();

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
                'producto' => $producto->fresh()->load('presentaciones.presentacion'),
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

    private function crearPresentacionBase(
        Producto $producto,
        UnidadMedida $unidad,
        string|int|float $paso = 1
    ): ProductoPresentacion {
        return ProductoPresentacion::updateOrCreate(
            [
                'producto_id_producto' => $producto->id_producto,
                'es_base' => true,
            ],
            [
                'presentacion_id' => $unidad->presentacion_base_id,
                'factor' => 1,
                'precio_venta' => $producto->precio_venta,
                'paso' => $paso,
                'activo' => true,
                'orden' => 0,
            ]
        );
    }

    private function sincronizarPresentaciones(
        Producto $producto,
        array $presentaciones,
        UnidadMedida $unidad
    ): void {
        $baseId = (int) $unidad->presentacion_base_id;

        $existentes = $producto->presentaciones()
            ->where('es_base', false)
            ->get()
            ->keyBy('presentacion_id');

        $mantenidos = [];

        foreach ($presentaciones as $i => $presentacion) {
            $presentacionId = (int) $presentacion['presentacion_id'];

            if ($presentacionId === $baseId) {
                continue;
            }

            $datos = [
                'factor' => $presentacion['factor'],
                'precio_venta' => $presentacion['precio_venta'],
                'paso' => $presentacion['paso'],
                'activo' => (bool) $presentacion['activo'],
                'codigo_barras' => $presentacion['codigo_barras'] ?? null,
                'orden' => $i + 1,
            ];

            $existente = $existentes->get($presentacionId);

            if ($existente) {
                $existente->update($datos);
                $mantenidos[] = $existente->id_producto_presentacion;
            } else {
                $nueva = $producto->presentaciones()->create(array_merge($datos, [
                    'presentacion_id' => $presentacionId,
                    'es_base' => false,
                ]));
                $mantenidos[] = $nueva->id_producto_presentacion;
            }
        }

        $producto->presentaciones()
            ->where('es_base', false)
            ->when($mantenidos, fn ($q) => $q->whereNotIn('id_producto_presentacion', $mantenidos))
            ->update(['activo' => false]);
    }

    private function presentacionTieneReferencias(ProductoPresentacion $presentacion): bool
    {
        foreach (['detalle_venta', 'detalle_ingreso', 'atencion_producto', 'kardex_movimientos', 'carrito_item', 'combo_producto'] as $tabla) {
            if (DB::table($tabla)->where('producto_presentacion_id', $presentacion->id_producto_presentacion)->exists()) {
                return true;
            }
        }

        return false;
    }
}
