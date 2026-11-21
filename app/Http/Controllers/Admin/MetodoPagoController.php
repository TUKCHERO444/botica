<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMetodoPagoRequest;
use App\Http\Requests\Admin\UpdateMetodoPagoRequest;
use App\Models\MetodoPago;

class MetodoPagoController extends Controller
{
    use Audita, CacheaPanel;

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('metodos-pago', function () {
                $metodos = MetodoPago::orderBy('id_metodo_pago', 'desc')->paginate(10)->withQueryString();

                return view('admin.metodos-pago.index', compact('metodos'))->render();
            });
        }

        $metodos = MetodoPago::orderBy('id_metodo_pago', 'desc')->paginate(10)->withQueryString();

        return view('admin.metodos-pago.full', compact('metodos'));
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.metodos-pago.create');
        }

        return redirect()->route('admin.metodos-pago.index');
    }

    public function store(StoreMetodoPagoRequest $request)
    {
        $metodo = MetodoPago::create($request->validated());
        $this->auditarCrear($metodo, 'metodos-pago', "Creó el método de pago '{$metodo->nombre}'");

        $this->olvidaIndex('metodos-pago');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Método de pago creado correctamente',
                'metodo_pago' => $metodo,
            ]);
        }

        return redirect()->route('admin.metodos-pago.index')->with('success', 'Método de pago creado correctamente');
    }

    public function edit(MetodoPago $metodoPago)
    {
        if (request()->ajax()) {
            return view('admin.metodos-pago.edit', compact('metodoPago'));
        }

        return redirect()->route('admin.metodos-pago.index');
    }

    public function update(UpdateMetodoPagoRequest $request, MetodoPago $metodoPago)
    {
        $metodoPago->update($request->validated());
        $this->auditarActualizar($metodoPago, 'metodos-pago', "Actualizó el método de pago '{$metodoPago->nombre}'");

        $this->olvidaIndex('metodos-pago');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Método de pago actualizado correctamente',
                'metodo_pago' => $metodoPago,
            ]);
        }

        return redirect()->route('admin.metodos-pago.index')->with('success', 'Método de pago actualizado correctamente');
    }

    public function toggleStatus(MetodoPago $metodoPago)
    {
        $nuevoEstado = $metodoPago->estado ? 0 : 1;
        $metodoPago->update(['estado' => $nuevoEstado]);
        $verbo = $nuevoEstado ? 'Activó' : 'Desactivó';
        $this->auditar(
            'metodos-pago',
            'toggle-estado',
            $metodoPago->id_metodo_pago,
            "{$verbo} el método de pago '{$metodoPago->nombre}'"
        );

        $this->olvidaIndex('metodos-pago');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $nuevoEstado
                    ? 'Método de pago activado correctamente'
                    : 'Método de pago desactivado correctamente',
            ]);
        }

        return redirect()->route('admin.metodos-pago.index');
    }

    public function destroy(MetodoPago $metodoPago)
    {
        $metodoPago->update(['estado' => 0]);
        $this->auditarEliminar($metodoPago, 'metodos-pago', "Desactivó el método de pago '{$metodoPago->nombre}'");

        $this->olvidaIndex('metodos-pago');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Método de pago desactivado correctamente',
            ]);
        }

        return redirect()->route('admin.metodos-pago.index')->with('success', 'Método de pago desactivado correctamente');
    }
}
