<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServicioRequest;
use App\Http\Requests\Admin\UpdateServicioRequest;
use App\Models\Servicio;

class ServicioController extends Controller
{
    use CacheaPanel, Audita;

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('servicios', function () {
                $servicios = Servicio::orderBy('id_servicio', 'desc')->get();
                return view('admin.servicios.index', compact('servicios'))->render();
            });
        }

        $servicios = Servicio::orderBy('id_servicio', 'desc')->get();
        return view('admin.servicios.full', compact('servicios'));
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.servicios.create');
        }

        return redirect()->route('admin.servicios.index');
    }

    public function store(StoreServicioRequest $request)
    {
        $servicio = Servicio::create($request->validated());
        $this->auditarCrear($servicio, 'servicios', "Creó el servicio '{$servicio->nombre}'");

        $this->olvidaIndex('servicios');
        $this->olvidaIndex('atenciones');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Servicio creado correctamente',
                'servicio' => $servicio,
            ]);
        }

        return redirect()->route('admin.servicios.index')->with('success', 'Servicio creado correctamente');
    }

    public function edit(Servicio $servicio)
    {
        if (request()->ajax()) {
            return view('admin.servicios.edit', compact('servicio'));
        }

        return redirect()->route('admin.servicios.index');
    }

    public function update(UpdateServicioRequest $request, Servicio $servicio)
    {
        $servicio->update($request->validated());
        $this->auditarActualizar($servicio, 'servicios', "Actualizó el servicio '{$servicio->nombre}'");

        $this->olvidaIndex('servicios');
        $this->olvidaIndex('atenciones');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Servicio actualizado correctamente',
                'servicio' => $servicio,
            ]);
        }

        return redirect()->route('admin.servicios.index')->with('success', 'Servicio actualizado correctamente');
    }

    public function destroy(Servicio $servicio)
    {
        $nombre = $servicio->nombre;
        $servicio->delete();
        $this->auditarEliminar($servicio, 'servicios', "Eliminó el servicio '{$nombre}'");

        $this->olvidaIndex('servicios');
        $this->olvidaIndex('atenciones');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Servicio eliminado correctamente',
            ]);
        }

        return redirect()->route('admin.servicios.index')->with('success', 'Servicio eliminado correctamente');
    }
}
