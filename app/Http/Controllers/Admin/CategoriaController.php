<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoriaRequest;
use App\Http\Requests\Admin\UpdateCategoriaRequest;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    use CacheaPanel, Audita;

    public function index()
    {
        $search = request('q');

        $query = Categoria::orderBy('id_categoria', 'desc');

        if ($search) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        if (request()->ajax()) {
            return $this->getIndex('categorias', function () use ($query, $search) {
                $categorias = $query->get();
                return view('admin.categorias.index', compact('categorias', 'search'))->render();
            });
        }

        $categorias = $query->get();
        return view('admin.categorias.full', compact('categorias', 'search'));
    }

    public function search(Request $request)
    {
        $q = $request->get('q');
        if (!$q || strlen(trim($q)) < 2) {
            return response()->json([]);
        }

        $results = Categoria::where('nombre', 'like', "%{$q}%")
            ->limit(10)
            ->get()
            ->map(fn($c) => [
                'id'       => $c->id_categoria,
                'nombre'   => $c->nombre,
                'edit_url' => route('admin.categorias.edit', $c),
            ]);

        return response()->json($results);
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.categorias.create');
        }

        return redirect()->route('admin.categorias.index');
    }

    public function store(StoreCategoriaRequest $request)
    {
        $data = $request->validated();
        $data['cantidad'] = 0;
        $categoria = Categoria::create($data);
        $this->auditarCrear($categoria, 'categorias', "Creó la categoría '{$categoria->nombre}'");

        $this->olvidaIndex('categorias');
        $this->olvidaIndex('productos');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoría creada correctamente',
                'categoria' => $categoria,
            ]);
        }

        return redirect()->route('admin.categorias.index')->with('success', 'Categoría creada correctamente');
    }

    public function edit(Categoria $categoria)
    {
        if (request()->ajax()) {
            return view('admin.categorias.edit', compact('categoria'));
        }

        return redirect()->route('admin.categorias.index');
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria)
    {
        $categoria->update($request->validated());
        $this->auditarActualizar($categoria, 'categorias', "Actualizó la categoría '{$categoria->nombre}'");

        $this->olvidaIndex('categorias');
        $this->olvidaIndex('productos');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoría actualizada correctamente',
                'categoria' => $categoria,
            ]);
        }

        return redirect()->route('admin.categorias.index')->with('success', 'Categoría actualizada correctamente');
    }

    public function toggleStatus(Categoria $categoria)
    {
        $nuevoEstado = $categoria->estado ? 0 : 1;
        $categoria->update(['estado' => $nuevoEstado]);
        $verbo = $nuevoEstado ? 'Activó' : 'Desactivó';
        $this->auditar('categorias', 'toggle-estado', $categoria->id_categoria, "{$verbo} la categoría '{$categoria->nombre}'");

        $this->olvidaIndex('categorias');
        $this->olvidaIndex('productos');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $nuevoEstado ? 'Categoría activada correctamente' : 'Categoría desactivada correctamente',
            ]);
        }

        return redirect()->route('admin.categorias.index');
    }

    public function destroy(Categoria $categoria)
    {
        $categoria->update(['estado' => 0]);
        $this->auditarEliminar($categoria, 'categorias', "Desactivó la categoría '{$categoria->nombre}'");

        $this->olvidaIndex('categorias');
        $this->olvidaIndex('productos');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoría desactivada correctamente',
            ]);
        }

        return redirect()->route('admin.categorias.index')->with('success', 'Categoría desactivada correctamente');
    }
}
