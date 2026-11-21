<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProveedorRequest;
use App\Http\Requests\Admin\UpdateProveedorRequest;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    use Audita, CacheaPanel;

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('proveedores', function () {
                $proveedores = Proveedor::orderBy('id_proveedor', 'desc')->paginate(10)->withQueryString();

                return view('admin.proveedores.index', compact('proveedores'))->render();
            });
        }

        $proveedores = Proveedor::orderBy('id_proveedor', 'desc')->paginate(10)->withQueryString();

        return view('admin.proveedores.full', compact('proveedores'));
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.proveedores.create');
        }

        return redirect()->route('admin.proveedores.index');
    }

    /**
     * Verifica un RUC solo contra la base de datos local. No consume la API.
     */
    public function verificarRuc(Request $request)
    {
        $request->validate([
            'ruc' => 'required|string|size:11|regex:/^\d{11}$/',
        ]);

        $proveedor = Proveedor::where('ruc', $request->input('ruc'))->first([
            'id_proveedor', 'ruc', 'razon_social', 'condicion',
            'estado', 'direccion', 'ubigeo', 'distrito',
            'provincia', 'departamento',
        ]);

        if (! $proveedor) {
            return response()->json([
                'success' => true,
                'exists' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'exists' => true,
            'proveedor' => $proveedor->toArray(),
        ]);
    }

    public function store(StoreProveedorRequest $request)
    {
        $proveedor = Proveedor::create($request->validated());
        $this->auditarCrear($proveedor, 'proveedores', "Registró al proveedor '{$proveedor->razon_social}'");

        $this->olvidaIndex('proveedores');
        $this->olvidaIndex('ingresos');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Proveedor registrado correctamente',
                'proveedor' => $proveedor,
            ]);
        }

        return redirect()->route('admin.proveedores.index')->with('success', 'Proveedor registrado correctamente');
    }

    public function edit(Proveedor $proveedor)
    {
        if (request()->ajax()) {
            return view('admin.proveedores.edit', compact('proveedor'));
        }

        return redirect()->route('admin.proveedores.index');
    }

    public function update(UpdateProveedorRequest $request, Proveedor $proveedor)
    {
        $proveedor->update($request->validated());
        $this->auditarActualizar($proveedor, 'proveedores', "Actualizó al proveedor '{$proveedor->razon_social}'");

        $this->olvidaIndex('proveedores');
        $this->olvidaIndex('ingresos');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Proveedor actualizado correctamente',
                'proveedor' => $proveedor,
            ]);
        }

        return redirect()->route('admin.proveedores.index')->with('success', 'Proveedor actualizado correctamente');
    }

    public function toggleStatus(Proveedor $proveedor)
    {
        $nuevoEstado = $proveedor->estado ? 0 : 1;
        $proveedor->update(['estado' => $nuevoEstado]);
        $verbo = $nuevoEstado ? 'Activó' : 'Desactivó';
        $this->auditar('proveedores', 'toggle-estado', $proveedor->id_proveedor, "{$verbo} al proveedor '{$proveedor->razon_social}'");

        $this->olvidaIndex('proveedores');
        $this->olvidaIndex('ingresos');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $nuevoEstado ? 'Proveedor activado correctamente' : 'Proveedor desactivado correctamente',
            ]);
        }

        return redirect()->route('admin.proveedores.index');
    }

    public function destroy(Proveedor $proveedor)
    {
        $proveedor->update(['estado' => 0]);
        $this->auditarEliminar($proveedor, 'proveedores', "Desactivó al proveedor '{$proveedor->razon_social}'");

        $this->olvidaIndex('proveedores');
        $this->olvidaIndex('ingresos');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Proveedor desactivado correctamente',
            ]);
        }

        return redirect()->route('admin.proveedores.index')->with('success', 'Proveedor desactivado correctamente');
    }
}
