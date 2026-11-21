<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreComboRequest;
use App\Http\Requests\Admin\UpdateComboRequest;
use App\Models\Combo;
use App\Models\Producto;
use App\Services\CloudinaryService;

class ComboController extends Controller
{
    use Audita;

    public function __construct(
        private readonly CloudinaryService $cloudinary
    ) {}

    public function index()
    {
        $combos = Combo::withCount('productos')->orderBy('nombre')->paginate(10)->withQueryString();

        if (request()->ajax()) {
            return view('admin.combos.index', compact('combos'));
        }

        return view('admin.combos.full', compact('combos'));
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.combos.create');
        }

        return redirect()->route('admin.combos.index');
    }

    public function store(StoreComboRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $result = $this->cloudinary->upload($request->file('imagen'), 'combos');
            $data['imagen_url'] = $result['url'];
            $data['public_id'] = $result['public_id'];
        }

        $data['activo'] = $request->boolean('activo', true);

        $combo = Combo::create($data);

        $productos = collect($request->input('productos', []))
            ->mapWithKeys(fn ($p) => [$p['id'] => ['cantidad' => $p['cantidad']]]);

        $combo->productos()->sync($productos);

        $this->auditarCrear($combo, 'combos', "Creó el combo '{$combo->nombre}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Combo creado correctamente',
            ]);
        }

        return redirect()->route('admin.combos.index')
            ->with('success', 'Combo creado correctamente');
    }

    public function edit(Combo $combo)
    {
        if (request()->ajax()) {
            $combo->load('productos');

            return view('admin.combos.edit', compact('combo'));
        }

        return redirect()->route('admin.combos.index');
    }

    public function update(UpdateComboRequest $request, Combo $combo)
    {
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $publicId = $combo->public_id
                ?? $this->cloudinary->getFolder('combos').'/'.$this->cloudinary->getPublicIdFromUrl($combo->imagen_url);

            $this->cloudinary->delete($publicId);

            $result = $this->cloudinary->upload($request->file('imagen'), 'combos');
            $data['imagen_url'] = $result['url'];
            $data['public_id'] = $result['public_id'];
        }

        $data['activo'] = $request->boolean('activo', true);

        $combo->update($data);

        $productos = collect($request->input('productos', []))
            ->mapWithKeys(fn ($p) => [$p['id'] => ['cantidad' => $p['cantidad']]]);

        $combo->productos()->sync($productos);

        $this->auditarActualizar($combo, 'combos', "Actualizó el combo '{$combo->nombre}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Combo actualizado correctamente',
            ]);
        }

        return redirect()->route('admin.combos.index')
            ->with('success', 'Combo actualizado correctamente');
    }

    public function destroy(Combo $combo)
    {
        if ($combo->imagen_url) {
            $publicId = $combo->public_id
                ?? $this->cloudinary->getFolder('combos').'/'.$this->cloudinary->getPublicIdFromUrl($combo->imagen_url);

            $this->cloudinary->delete($publicId);
        }

        $combo->productos()->detach();
        $combo->delete();

        $this->auditarEliminar($combo, 'combos', "Eliminó el combo '{$combo->nombre}'");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Combo eliminado correctamente',
            ]);
        }

        return redirect()->route('admin.combos.index')
            ->with('success', 'Combo eliminado correctamente');
    }

    public function buscarProductos()
    {
        $q = request('q');

        $productos = Producto::where('estado', 1)
            ->where('nombre', 'like', "%{$q}%")
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id_producto' => $p->id_producto,
                'nombre' => $p->nombre,
                'precio_venta' => $p->precio_venta,
                'foto_url' => $p->foto_url,
            ]);

        return response()->json($productos);
    }

    public function buscarCombos()
    {
        $q = request('q');

        $combos = Combo::where('activo', true)
            ->where('nombre', 'like', "%{$q}%")
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'id_combo' => $c->id_combo,
                'nombre' => $c->nombre,
                'precio_sugerido' => $c->precio_sugerido,
            ]);

        return response()->json($combos);
    }
}
