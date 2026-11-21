<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDatosSistemaRequest;
use App\Models\ConfiguracionSistema;

class DatosSistemaController extends Controller
{
    use Audita, CacheaPanel;

    public function index()
    {
        $configuracion = ConfiguracionSistema::actual();

        if (request()->ajax()) {
            return view('admin.datos-sistema.index', compact('configuracion'));
        }

        return view('admin.datos-sistema.full', compact('configuracion'));
    }

    public function update(UpdateDatosSistemaRequest $request)
    {
        $configuracion = ConfiguracionSistema::guardarNombre(
            $request->validated()['nombre_negocio']
        );

        $this->auditarActualizar(
            $configuracion,
            'datos-sistema',
            "Actualizó el nombre del negocio a '{$configuracion->nombre_negocio}'"
        );

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Datos del sistema actualizados correctamente.',
                'nombre_negocio' => $configuracion->nombre_negocio,
            ]);
        }

        return redirect()
            ->route('admin.datos-sistema.index')
            ->with('success', 'Datos del sistema actualizados correctamente.');
    }
}
