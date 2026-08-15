<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClienteRequest;
use App\Http\Requests\Admin\UpdateClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    use CacheaPanel, Audita;

    public function generico()
    {
        $dniGenerico = '00000001';
        $cliente = Cliente::where('dni', $dniGenerico)->first();

        if (!$cliente) {
            $cliente = Cliente::create([
                'dni' => $dniGenerico,
                'nombre' => 'SIN IDENTIFICAR',
                'apellido_paterno' => null,
                'apellido_materno' => null,
            ]);
            $this->olvidaIndex('clientes');
            $this->olvidaIndex('ventas');
            $this->olvidaIndex('atenciones');
        }

        return response()->json([
            'success' => true,
            'cliente' => [
                'id_cliente' => $cliente->id_cliente,
                'nombre' => $cliente->nombre,
                'apellido_paterno' => $cliente->apellido_paterno,
                'apellido_materno' => $cliente->apellido_materno,
                'dni' => $cliente->dni,
                'registro_web' => false,
            ],
        ]);
    }

    public function search(Request $request)
    {
        $q = $request->get('q');
        if (!$q || strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = Cliente::where('nombre', 'like', "%{$q}%")
            ->orWhere('apellido_paterno', 'like', "%{$q}%")
            ->orWhere('apellido_materno', 'like', "%{$q}%")
            ->orWhere('dni', 'like', "%{$q}%")
            ->orderBy('nombre')
            ->limit(15)
            ->get(['id_cliente', 'nombre', 'apellido_paterno', 'apellido_materno', 'dni', 'registro_web']);

        return response()->json($clientes);
    }

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('clientes', function () {
                $clientes = Cliente::orderBy('id_cliente', 'desc')->get();
                return view('admin.clientes.index', compact('clientes'))->render();
            });
        }

        $clientes = Cliente::orderBy('id_cliente', 'desc')->get();
        return view('admin.clientes.full', compact('clientes'));
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.clientes.create');
        }

        return redirect()->route('admin.clientes.index');
    }

    public function store(StoreClienteRequest $request)
    {
        $cliente = Cliente::create($request->validated());
        $nombreCompleto = trim("{$cliente->nombre} {$cliente->apellido_paterno} {$cliente->apellido_materno}");
        $this->auditarCrear($cliente, 'clientes', "Registró al cliente '{$nombreCompleto}'");

        $this->olvidaIndex('clientes');
        $this->olvidaIndex('ventas');
        $this->olvidaIndex('atenciones');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Cliente registrado correctamente',
                'cliente' => $cliente,
            ]);
        }

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente registrado correctamente');
    }

    public function edit(Cliente $cliente)
    {
        if (request()->ajax()) {
            return view('admin.clientes.edit', compact('cliente'));
        }

        return redirect()->route('admin.clientes.index');
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente)
    {
        $cliente->update($request->validated());
        $nombreCompleto = trim("{$cliente->nombre} {$cliente->apellido_paterno} {$cliente->apellido_materno}");
        $this->auditarActualizar($cliente, 'clientes', "Actualizó al cliente '{$nombreCompleto}'");

        $this->olvidaIndex('clientes');
        $this->olvidaIndex('ventas');
        $this->olvidaIndex('atenciones');

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Cliente actualizado correctamente',
                'cliente' => $cliente,
            ]);
        }

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente actualizado correctamente');
    }

    public function destroy(Cliente $cliente)
    {
        $nombreCompleto = trim("{$cliente->nombre} {$cliente->apellido_paterno} {$cliente->apellido_materno}");
        $cliente->delete();
        $this->auditarEliminar($cliente, 'clientes', "Eliminó al cliente '{$nombreCompleto}'");

        $this->olvidaIndex('clientes');
        $this->olvidaIndex('ventas');
        $this->olvidaIndex('atenciones');

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Cliente eliminado correctamente',
            ]);
        }

        return redirect()->route('admin.clientes.index')->with('success', 'Cliente eliminado correctamente');
    }
}
