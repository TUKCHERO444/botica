<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\PortadaCategoria;

class PortadaCategoriaController extends Controller
{
    use Audita;

    public function index()
    {
        $categorias = Categoria::where('estado', 1)->orderBy('nombre')->paginate(10)->withQueryString();
        $seleccionadas = PortadaCategoria::orderBy('orden')->pluck('categoria_id_categoria')->toArray();

        if (request()->ajax()) {
            return view('admin.portada-categorias.index', compact('categorias', 'seleccionadas'));
        }

        return view('admin.portada-categorias.full', compact('categorias', 'seleccionadas'));
    }

    public function toggle(Categoria $categoria)
    {
        $existe = PortadaCategoria::where('categoria_id_categoria', $categoria->id_categoria)->first();

        if ($existe) {
            $existe->delete();
            $this->auditar('portada-categorias', 'toggle-estado', $categoria->id_categoria, "Quitó la categoría '{$categoria->nombre}' de la portada");

            return response()->json([
                'success' => true,
                'message' => 'Categoría quitada de la portada',
                'activa' => false,
            ]);
        }

        $maxOrden = PortadaCategoria::max('orden') ?? 0;

        PortadaCategoria::create([
            'categoria_id_categoria' => $categoria->id_categoria,
            'orden' => $maxOrden + 1,
        ]);
        $this->auditar('portada-categorias', 'toggle-estado', $categoria->id_categoria, "Agregó la categoría '{$categoria->nombre}' a la portada");

        return response()->json([
            'success' => true,
            'message' => 'Categoría agregada a la portada',
            'activa' => true,
        ]);
    }

    public function reordenar()
    {
        $data = request()->validate([
            'items' => 'required|array',
            'items.*' => 'required|integer|exists:portada_categoria,id_portada_categoria',
        ]);

        foreach ($data['items'] as $i => $id) {
            PortadaCategoria::where('id_portada_categoria', $id)->update(['orden' => $i]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orden actualizado correctamente',
        ]);
    }
}
