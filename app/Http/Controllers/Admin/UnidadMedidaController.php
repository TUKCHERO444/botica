<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnidadMedidaRequest;
use App\Http\Requests\Admin\UpdateUnidadMedidaRequest;
use App\Models\Presentacion;
use App\Models\UnidadMedida;

class UnidadMedidaController extends Controller
{
    use Audita;

    public function index()
    {
        $unidades = UnidadMedida::with('presentacionBase')
            ->withCount('productos')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        if (request()->ajax()) {
            return view('admin.unidades-medida.index', compact('unidades'));
        }

        return view('admin.unidades-medida.full', compact('unidades'));
    }

    public function create()
    {
        if (request()->ajax()) {
            $presentaciones = Presentacion::where('activo', true)->orderBy('nombre')->get();

            return view('admin.unidades-medida.create', compact('presentaciones'));
        }

        return redirect()->route('admin.unidades-medida.index');
    }

    public function store(StoreUnidadMedidaRequest $request)
    {
        $unidad = UnidadMedida::create($request->validated());
        $this->auditarCrear($unidad, 'unidades-medida', "Creó la unidad de medida '{$unidad->nombre}' ({$unidad->codigo})");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Unidad de medida creada correctamente',
            ]);
        }

        return redirect()->route('admin.unidades-medida.index')
            ->with('success', 'Unidad de medida creada correctamente');
    }

    public function edit(UnidadMedida $unidadMedida)
    {
        if (request()->ajax()) {
            $presentaciones = Presentacion::where('activo', true)->orderBy('nombre')->get();

            return view('admin.unidades-medida.edit', compact('unidadMedida', 'presentaciones'));
        }

        return redirect()->route('admin.unidades-medida.index');
    }

    public function update(UpdateUnidadMedidaRequest $request, UnidadMedida $unidadMedida)
    {
        $unidadMedida->update($request->validated());
        $this->auditarActualizar($unidadMedida, 'unidades-medida', "Actualizó la unidad de medida '{$unidadMedida->nombre}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Unidad de medida actualizada correctamente',
            ]);
        }

        return redirect()->route('admin.unidades-medida.index')
            ->with('success', 'Unidad de medida actualizada correctamente');
    }

    public function destroy(UnidadMedida $unidadMedida)
    {
        if ($unidadMedida->productos()->exists()) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar: la unidad está asignada a productos.',
                ], 422);
            }

            return redirect()->route('admin.unidades-medida.index')
                ->with('error', 'No se puede eliminar: la unidad está asignada a productos.');
        }

        $nombre = $unidadMedida->nombre;
        $unidadMedida->delete();
        $this->auditarEliminar($unidadMedida, 'unidades-medida', "Eliminó la unidad de medida '{$nombre}'");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Unidad de medida eliminada correctamente',
            ]);
        }

        return redirect()->route('admin.unidades-medida.index')
            ->with('success', 'Unidad de medida eliminada correctamente');
    }
}
