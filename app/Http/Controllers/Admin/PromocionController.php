<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromocionRequest;
use App\Http\Requests\Admin\UpdatePromocionRequest;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Promocion;
use App\Services\CloudinaryService;

class PromocionController extends Controller
{
    use Audita;

    public function __construct(
        private readonly CloudinaryService $cloudinary
    ) {}

    public function index()
    {
        $promociones = Promocion::orderBy('orden')->with('combo')->paginate(10)->withQueryString();

        if (request()->ajax()) {
            return view('admin.promociones.index', compact('promociones'));
        }

        return view('admin.promociones.full', compact('promociones'));
    }

    public function create()
    {
        if (request()->ajax()) {
            $combos = Combo::where('activo', true)->orderBy('nombre')->get(['id_combo', 'nombre', 'precio_sugerido']);

            return view('admin.promociones.create', compact('combos'));
        }

        return redirect()->route('admin.promociones.index');
    }

    public function store(StorePromocionRequest $request)
    {
        $data = $request->validated();

        $result = $this->cloudinary->upload($request->file('imagen'), 'promociones');

        $data['imagen_url'] = $result['url'];
        $data['public_id'] = $result['public_id'];
        $data['activo'] = $request->boolean('activo', true);

        $promocion = Promocion::create($data);
        $this->auditarCrear($promocion, 'promociones', "Creó la promoción '{$promocion->titulo}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Promoción creada correctamente',
            ]);
        }

        return redirect()->route('admin.promociones.index')
            ->with('success', 'Promoción creada correctamente');
    }

    public function edit(Promocion $promocion)
    {
        if (request()->ajax()) {
            $promocion->load('combo');
            $combos = Combo::where('activo', true)->orderBy('nombre')->get(['id_combo', 'nombre', 'precio_sugerido']);

            return view('admin.promociones.edit', compact('promocion', 'combos'));
        }

        return redirect()->route('admin.promociones.index');
    }

    public function update(UpdatePromocionRequest $request, Promocion $promocion)
    {
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $publicId = $promocion->public_id
                ?? $this->cloudinary->getFolder('promociones').'/'.$this->cloudinary->getPublicIdFromUrl($promocion->imagen_url);

            $this->cloudinary->delete($publicId);

            $result = $this->cloudinary->upload($request->file('imagen'), 'promociones');

            $data['imagen_url'] = $result['url'];
            $data['public_id'] = $result['public_id'];
        }

        $data['activo'] = $request->boolean('activo', true);

        $promocion->update($data);
        $this->auditarActualizar($promocion, 'promociones', "Actualizó la promoción '{$promocion->titulo}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Promoción actualizada correctamente',
            ]);
        }

        return redirect()->route('admin.promociones.index')
            ->with('success', 'Promoción actualizada correctamente');
    }

    public function destroy(Promocion $promocion)
    {
        $publicId = $promocion->public_id
            ?? $this->cloudinary->getFolder('promociones').'/'.$this->cloudinary->getPublicIdFromUrl($promocion->imagen_url);

        $this->cloudinary->delete($publicId);

        $promocion->delete();
        $this->auditarEliminar($promocion, 'promociones', "Eliminó la promoción '{$promocion->titulo}'");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Promoción eliminada correctamente',
            ]);
        }

        return redirect()->route('admin.promociones.index')
            ->with('success', 'Promoción eliminada correctamente');
    }

    public function reordenar()
    {
        $data = request()->validate([
            'items' => 'required|array',
            'items.*' => 'required|integer|exists:promocion,id_promocion',
        ]);

        foreach ($data['items'] as $i => $id) {
            Promocion::where('id_promocion', $id)->update(['orden' => $i]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orden actualizado correctamente',
        ]);
    }

    public function buscarEntidad()
    {
        $ambito = request('ambito');
        $q = request('q');

        $resultados = match ($ambito) {
            'producto' => Producto::where('estado', 1)
                ->where('nombre', 'like', "%{$q}%")
                ->limit(20)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id_producto,
                    'text' => "{$p->nombre} — S/ {$p->precio_venta}",
                ]),
            'categoria' => Categoria::where('estado', 1)
                ->where('nombre', 'like', "%{$q}%")
                ->limit(20)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id_categoria,
                    'text' => $c->nombre,
                ]),
            'marca' => Marca::where('estado', 1)
                ->where('nombre', 'like', "%{$q}%")
                ->limit(20)
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id_marca,
                    'text' => $m->nombre,
                ]),
            default => collect(),
        };

        return response()->json($resultados);
    }
}
