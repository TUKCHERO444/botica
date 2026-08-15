<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KardexMovimiento;
use App\Models\Producto;
use App\Models\User;

class KardexController extends Controller
{
    public function index()
    {
        $query = KardexMovimiento::with('producto', 'usuario')
            ->orderBy('created_at', 'desc');

        if ($productoId = request('producto_id')) {
            $query->where('producto_id', $productoId);
        }
        if ($tipo = request('tipo_movimiento')) {
            $query->where('tipo_movimiento', $tipo);
        }
        if ($usuarioId = request('usuario_id')) {
            $query->where('usuario_id', $usuarioId);
        }
        if ($desde = request('fecha_desde')) {
            $query->whereDate('created_at', '>=', $desde);
        }
        if ($hasta = request('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $hasta);
        }

        $movimientos = $query->paginate(50);
        $productos = Producto::where('estado', 1)->orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        if (request()->ajax()) {
            return view('admin.kardex.index', compact('movimientos', 'productos', 'usuarios'));
        }

        return view('admin.kardex.full', compact('movimientos', 'productos', 'usuarios'));
    }

    public function porProducto(Producto $producto)
    {
        $movimientos = KardexMovimiento::with('usuario')
            ->where('producto_id', $producto->id_producto)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        if (request()->ajax()) {
            return view('admin.kardex.producto', compact('movimientos', 'producto'));
        }

        return redirect()->route('admin.kardex.index');
    }
}
