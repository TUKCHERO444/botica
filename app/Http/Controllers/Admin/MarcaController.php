<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMarcaRequest;
use App\Http\Requests\Admin\UpdateMarcaRequest;
use App\Models\Marca;
use Illuminate\Http\Request;

class MarcaController extends Controller
{
    use CacheaPanel, Audita;

    public function index()
    {
        $search = request('q');

        $query = Marca::orderBy('id_marca', 'desc');

        if ($search) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        if (request()->ajax()) {
            return $this->getIndex('marcas', function () use ($query, $search) {
                $marcas = $query->get();
                return view('admin.marcas.index', compact('marcas', 'search'))->render();
            });
        }

        $marcas = $query->get();
        return view('admin.marcas.full', compact('marcas', 'search'));
    }

    public function search(Request $request)
    {
        $q = $request->get('q');
        if (!$q || strlen(trim($q)) < 2) {
            return response()->json([]);
        }

        $results = Marca::where('nombre', 'like', "%{$q}%")
            ->limit(10)
            ->get()
            ->map(fn($m) => [
                'id'       => $m->id_marca,
                'nombre'   => $m->nombre,
                'edit_url' => route('admin.marcas.edit', $m),
            ]);

        return response()->json($results);
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.marcas.create');
        }

        return redirect()->route('admin.marcas.index');
    }

    public function store(StoreMarcaRequest $request)
    {
        $data = $request->validated();
        $data['cantidad'] = 0;
        $marca = Marca::create($data);
        $this->auditarCrear($marca, 'marcas', "Creó la marca '{$marca->nombre}'");

        $this->olvidaIndex('marcas');
        $this->olvidaIndex('productos');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Marca creada correctamente',
                'marca' => $marca,
            ]);
        }

        return redirect()->route('admin.marcas.index')->with('success', 'Marca creada correctamente');
    }

    public function edit(Marca $marca)
    {
        if (request()->ajax()) {
            return view('admin.marcas.edit', compact('marca'));
        }

        return redirect()->route('admin.marcas.index');
    }

    public function update(UpdateMarcaRequest $request, Marca $marca)
    {
        $marca->update($request->validated());
        $this->auditarActualizar($marca, 'marcas', "Actualizó la marca '{$marca->nombre}'");

        $this->olvidaIndex('marcas');
        $this->olvidaIndex('productos');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Marca actualizada correctamente',
                'marca' => $marca,
            ]);
        }

        return redirect()->route('admin.marcas.index')->with('success', 'Marca actualizada correctamente');
    }

    public function toggleStatus(Marca $marca)
    {
        $nuevoEstado = $marca->estado ? 0 : 1;
        $marca->update(['estado' => $nuevoEstado]);
        $verbo = $nuevoEstado ? 'Activó' : 'Desactivó';
        $this->auditar('marcas', 'toggle-estado', $marca->id_marca, "{$verbo} la marca '{$marca->nombre}'");

        $this->olvidaIndex('marcas');
        $this->olvidaIndex('productos');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $nuevoEstado ? 'Marca activada correctamente' : 'Marca desactivada correctamente',
            ]);
        }

        return redirect()->route('admin.marcas.index');
    }

    public function destroy(Marca $marca)
    {
        $marca->update(['estado' => 0]);
        $this->auditarEliminar($marca, 'marcas', "Desactivó la marca '{$marca->nombre}'");

        $this->olvidaIndex('marcas');
        $this->olvidaIndex('productos');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Marca desactivada correctamente',
            ]);
        }

        return redirect()->route('admin.marcas.index')->with('success', 'Marca desactivada correctamente');
    }
}
