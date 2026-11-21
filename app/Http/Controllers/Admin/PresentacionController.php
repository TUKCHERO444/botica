<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePresentacionRequest;
use App\Http\Requests\Admin\UpdatePresentacionRequest;
use App\Models\Presentacion;

class PresentacionController extends Controller
{
    use Audita;

    public function index()
    {
        $presentaciones = Presentacion::withCount('productoPresentaciones')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        if (request()->ajax()) {
            return view('admin.presentaciones.index', compact('presentaciones'));
        }

        return view('admin.presentaciones.full', compact('presentaciones'));
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.presentaciones.create');
        }

        return redirect()->route('admin.presentaciones.index');
    }

    public function store(StorePresentacionRequest $request)
    {
        $presentacion = Presentacion::create($request->validated());
        $this->auditarCrear($presentacion, 'presentaciones', "Creó la presentación '{$presentacion->nombre}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Presentación creada correctamente',
            ]);
        }

        return redirect()->route('admin.presentaciones.index')
            ->with('success', 'Presentación creada correctamente');
    }

    public function edit(Presentacion $presentacion)
    {
        if (request()->ajax()) {
            return view('admin.presentaciones.edit', compact('presentacion'));
        }

        return redirect()->route('admin.presentaciones.index');
    }

    public function update(UpdatePresentacionRequest $request, Presentacion $presentacion)
    {
        $presentacion->update($request->validated());
        $this->auditarActualizar($presentacion, 'presentaciones', "Actualizó la presentación '{$presentacion->nombre}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Presentación actualizada correctamente',
            ]);
        }

        return redirect()->route('admin.presentaciones.index')
            ->with('success', 'Presentación actualizada correctamente');
    }

    public function destroy(Presentacion $presentacion)
    {
        if ($presentacion->productoPresentaciones()->exists()
            || $presentacion->unidadesMedida()->exists()) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar: la presentación está asignada a productos o unidades de medida.',
                ], 422);
            }

            return redirect()->route('admin.presentaciones.index')
                ->with('error', 'No se puede eliminar: la presentación está asignada.');
        }

        $nombre = $presentacion->nombre;
        $presentacion->delete();
        $this->auditarEliminar($presentacion, 'presentaciones', "Eliminó la presentación '{$nombre}'");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Presentación eliminada correctamente',
            ]);
        }

        return redirect()->route('admin.presentaciones.index')
            ->with('success', 'Presentación eliminada correctamente');
    }
}
