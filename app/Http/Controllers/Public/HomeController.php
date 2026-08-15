<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\PortadaCategoria;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\SliderProducto;

class HomeController extends Controller
{
    public function index()
    {
        $categoriasPortada = PortadaCategoria::orderBy('orden')
            ->with('categoria')
            ->get()
            ->map(fn($pc) => $pc->categoria)
            ->filter()
            ->values();

        $banners = Banner::where('activo', true)->orderBy('orden')->get();

        $promociones = Promocion::where('activo', true)
            ->orderBy('orden')
            ->with('combo')
            ->get()
            ->map(function ($p) {
                $p->enlace_resuelto = $this->resolverEnlace($p);
                return $p;
            });

        $sliders = SliderProducto::all()->groupBy('slider_tipo');

        $destacados  = $this->productosDeSlider($sliders, 'buscados');
        $masVendidos = $this->productosDeSlider($sliders, 'vendidos');
        $descuentos  = $this->productosDeSlider($sliders, 'descuentos');

        return view('welcome', compact('categoriasPortada', 'banners', 'promociones', 'destacados', 'masVendidos', 'descuentos'));
    }

    private function resolverEnlace(Promocion $p): ?string
    {
        return match ($p->tipo_promocion) {
            'descuento' => match ($p->ambito) {
                'producto'  => route('public.productos.show', $p->ambito_id),
                'categoria' => route('public.productos.index', ['categorias[]' => $p->ambito_id]),
                'marca'     => route('public.productos.index', ['marcas[]' => $p->ambito_id]),
                default     => $p->enlace,
            },
            'combo'     => $p->combo?->activo ? route('public.combos.show', $p->combo) : null,
            default     => $p->enlace,
        };
    }

    private function productosDeSlider($sliders, string $tipo)
    {
        $items = collect($sliders[$tipo] ?? [])->sortBy('orden');
        $ids   = $items->pluck('producto_id_producto');

        $productos = Producto::whereIn('id_producto', $ids)
            ->where('estado', 1)
            ->get()
            ->keyBy('id_producto');

        return $items->map(fn($s) => $productos->get($s->producto_id_producto))
            ->filter()
            ->values();
    }
}
