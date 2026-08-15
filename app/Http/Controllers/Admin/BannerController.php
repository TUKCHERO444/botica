<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Models\Banner;
use App\Services\CloudinaryService;

class BannerController extends Controller
{
    use Audita;

    public function __construct(
        private readonly CloudinaryService $cloudinary
    ) {}

    public function index()
    {
        $banners = Banner::orderBy('orden')->get();

        if (request()->ajax()) {
            return view('admin.banners.index', compact('banners'));
        }

        return view('admin.banners.full', compact('banners'));
    }

    public function create()
    {
        if (request()->ajax()) {
            return view('admin.banners.create');
        }

        return redirect()->route('admin.banners.index');
    }

    public function store(StoreBannerRequest $request)
    {
        $data = $request->validated();

        $result = $this->cloudinary->upload($request->file('imagen'), 'banners');

        $data['imagen_url'] = $result['url'];
        $data['public_id'] = $result['public_id'];
        $data['activo'] = $request->boolean('activo', true);

        $banner = Banner::create($data);
        $this->auditarCrear($banner, 'banners', "Creó el banner '{$banner->titulo}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Banner creado correctamente',
            ]);
        }

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner creado correctamente');
    }

    public function edit(Banner $banner)
    {
        if (request()->ajax()) {
            return view('admin.banners.edit', compact('banner'));
        }

        return redirect()->route('admin.banners.index');
    }

    public function update(UpdateBannerRequest $request, Banner $banner)
    {
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $publicId = $banner->public_id
                ?? $this->cloudinary->getFolder('banners') . '/' . $this->cloudinary->getPublicIdFromUrl($banner->imagen_url);

            $this->cloudinary->delete($publicId);

            $result = $this->cloudinary->upload($request->file('imagen'), 'banners');

            $data['imagen_url'] = $result['url'];
            $data['public_id'] = $result['public_id'];
        }

        $data['activo'] = $request->boolean('activo', true);

        $banner->update($data);
        $this->auditarActualizar($banner, 'banners', "Actualizó el banner '{$banner->titulo}'");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Banner actualizado correctamente',
            ]);
        }

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner actualizado correctamente');
    }

    public function destroy(Banner $banner)
    {
        $publicId = $banner->public_id
            ?? $this->cloudinary->getFolder('banners') . '/' . $this->cloudinary->getPublicIdFromUrl($banner->imagen_url);

        $this->cloudinary->delete($publicId);

        $banner->delete();
        $this->auditarEliminar($banner, 'banners', "Eliminó el banner '{$banner->titulo}'");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Banner eliminado correctamente',
            ]);
        }

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner eliminado correctamente');
    }

    public function reordenar()
    {
        $data = request()->validate([
            'items'   => 'required|array',
            'items.*' => 'required|integer|exists:banner,id_banner',
        ]);

        foreach ($data['items'] as $i => $id) {
            Banner::where('id_banner', $id)->update(['orden' => $i]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Orden actualizado correctamente',
        ]);
    }
}
