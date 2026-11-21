<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHeaderTextRequest;
use App\Http\Requests\Admin\UpdateHeaderTextRequest;
use App\Models\HeaderText;

class HeaderTextController extends Controller
{
    use Audita;

    public function index()
    {
        $headerTexts = HeaderText::orderBy('orden')->paginate(10)->withQueryString();

        if (request()->ajax()) {
            return view('admin.header-text.index', compact('headerTexts'));
        }

        return view('admin.header-text.full', compact('headerTexts'));
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.header-text.create');
        }

        return redirect()->route('admin.header-text.index');
    }

    public function store(StoreHeaderTextRequest $request)
    {
        $data = $request->validated();
        $data['activo'] = $request->boolean('activo', true);

        $headerText = HeaderText::create($data);
        $this->auditarCrear($headerText, 'header-text', "Creó el texto del header '{$headerText->texto}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Texto creado correctamente',
            ]);
        }

        return redirect()->route('admin.header-text.index')
            ->with('success', 'Texto creado correctamente');
    }

    public function edit(HeaderText $headerText)
    {
        if (request()->ajax()) {
            return view('admin.header-text.edit', compact('headerText'));
        }

        return redirect()->route('admin.header-text.index');
    }

    public function update(UpdateHeaderTextRequest $request, HeaderText $headerText)
    {
        $data = $request->validated();
        $data['activo'] = $request->boolean('activo', true);

        $headerText->update($data);
        $this->auditarActualizar($headerText, 'header-text', "Actualizó el texto del header '{$headerText->texto}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Texto actualizado correctamente',
            ]);
        }

        return redirect()->route('admin.header-text.index')
            ->with('success', 'Texto actualizado correctamente');
    }

    public function destroy(HeaderText $headerText)
    {
        $texto = $headerText->texto;
        $headerText->delete();
        $this->auditarEliminar($headerText, 'header-text', "Eliminó el texto del header '{$texto}'");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Texto eliminado correctamente',
            ]);
        }

        return redirect()->route('admin.header-text.index')
            ->with('success', 'Texto eliminado correctamente');
    }

    public function reordenar()
    {
        $data = request()->validate([
            'items' => 'required|array',
            'items.*' => 'required|integer|exists:header_text,id_header_text',
        ]);

        foreach ($data['items'] as $i => $id) {
            HeaderText::where('id_header_text', $id)->update(['orden' => $i]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orden actualizado correctamente',
        ]);
    }
}
