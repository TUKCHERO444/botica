<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;

class AuditoriaController extends Controller
{
    public function index()
    {
        $query = Auditoria::with('user')->orderBy('id_auditoria', 'desc');

        if ($modulo = request('modulo')) {
            $query->where('modulo', $modulo);
        }
        if ($userId = request('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($accion = request('accion')) {
            $query->where('accion', $accion);
        }
        if ($desde = request('desde')) {
            $query->whereDate('created_at', '>=', $desde);
        }
        if ($hasta = request('hasta')) {
            $query->whereDate('created_at', '<=', $hasta);
        }
        if ($q = request('q')) {
            $query->where('descripcion', 'like', "%{$q}%");
        }

        $items = $query->paginate(50);

        $modulos = [
            'productos', 'categorias', 'marcas', 'proveedores',
            'clientes', 'ventas', 'atenciones', 'ingresos',
            'servicios', 'usuarios', 'roles', 'caja',
            'banners', 'sliders', 'portada-categorias',
        ];
        $usuarios = User::orderBy('name')->get(['id', 'name', 'email']);

        if (request()->ajax()) {
            return view('admin.auditoria.index', compact('items', 'modulos', 'usuarios'));
        }

        return view('admin.auditoria.full', compact('items', 'modulos', 'usuarios'));
    }

    public function show(Auditoria $auditorium)
    {
        $auditorium->load('user');

        $valoresAnteriores = $auditorium->valores_anteriores ?? [];
        $valoresNuevos = $auditorium->valores_nuevos ?? [];

        $cambios = [];
        $todasKeys = array_unique(array_merge(array_keys($valoresAnteriores), array_keys($valoresNuevos)));
        foreach ($todasKeys as $key) {
            $old = $valoresAnteriores[$key] ?? null;
            $new = $valoresNuevos[$key] ?? null;
            if ($old !== $new) {
                $cambios[] = [
                    'campo' => $key,
                    'anterior' => is_array($old) ? json_encode($old) : $old,
                    'nuevo' => is_array($new) ? json_encode($new) : $new,
                ];
            }
        }

        if (request()->ajax()) {
            return view('admin.auditoria.show', compact('auditorium', 'cambios'));
        }

        return redirect()->route('admin.auditoria.index');
    }
}
