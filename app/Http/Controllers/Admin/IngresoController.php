<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\KardexHelper;
use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIngresoRequest;
use App\Http\Requests\Admin\UpdateIngresoRequest;
use App\Models\Ingreso;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;

class IngresoController extends Controller
{
    use Audita, CacheaPanel;

    private function generarCorrelativo(int $idIngreso): string
    {
        $sufijo = str_pad($idIngreso, 8, '0', STR_PAD_LEFT);

        return "ING-{$sufijo}";
    }

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('ingresos', function () {
                $ingresos = Ingreso::with('proveedor', 'user', 'productos')
                    ->orderBy('id_ingreso', 'desc')->get();

                return view('admin.ingresos.index', compact('ingresos'))->render();
            });
        }

        $ingresos = Ingreso::with('proveedor', 'user', 'productos')
            ->orderBy('id_ingreso', 'desc')->get();

        return view('admin.ingresos.full', compact('ingresos'));
    }

    public function show(Ingreso $ingreso)
    {
        $ingreso->load('proveedor', 'user', 'productos');

        if (request()->ajax()) {
            return view('admin.ingresos.show', compact('ingreso'));
        }

        return redirect()->route('admin.ingresos.index');
    }

    public function create()
    {
        if (request()->ajax()) {
            $proveedores = Proveedor::where('estado', 1)->orderBy('razon_social')->get();
            $productos = Producto::where('estado', 1)->orderBy('nombre')->get();
            $nextId = (Ingreso::max('id_ingreso') ?? 0) + 1;
            $siguienteCorrelativo = $this->generarCorrelativo($nextId);

            return view('admin.ingresos.create', compact('proveedores', 'productos', 'siguienteCorrelativo'));
        }

        return redirect()->route('admin.ingresos.index');
    }

    public function store(StoreIngresoRequest $request)
    {
        $data = $request->validated();

        $ingreso = DB::transaction(function () use ($data) {
            $ingreso = Ingreso::create([
                'correlativo' => 'TEMP',
                'proveedor_id_proveedor' => $data['proveedor_id_proveedor'],
                'fecha' => $data['fecha'] ?? now()->toDateString(),
                'user_id' => auth()->id(),
            ]);

            $ingreso->update(['correlativo' => $this->generarCorrelativo($ingreso->id_ingreso)]);

            foreach ($data['productos'] as $p) {
                $ingreso->productos()->attach($p['producto_id_producto'], [
                    'cantidad' => $p['cantidad'],
                ]);

                Producto::where('id_producto', $p['producto_id_producto'])
                    ->increment('stock', $p['cantidad']);

                $producto = Producto::find($p['producto_id_producto']);
                KardexHelper::registrar(
                    $producto,
                    'INGRESO',
                    'Ingreso',
                    $ingreso->id_ingreso,
                    $ingreso->correlativo,
                    $p['cantidad'],
                    'Ingreso de inventario'
                );

                Producto::where('id_producto', $p['producto_id_producto'])
                    ->update(['inventario' => DB::raw('stock')]);
            }

            return $ingreso;
        });

        $this->olvidaIndex('ingresos');
        $this->olvidaIndex('productos');
        $this->auditarCrear($ingreso, 'ingresos', "Registró el ingreso #{$ingreso->correlativo}");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Ingreso registrado correctamente',
                'ingreso' => $ingreso->load('proveedor', 'user', 'productos'),
            ]);
        }

        return redirect()->route('admin.ingresos.index')->with('success', 'Ingreso registrado correctamente');
    }

    public function edit(Ingreso $ingreso)
    {
        if (request()->ajax()) {
            $proveedores = Proveedor::where('estado', 1)->orderBy('razon_social')->get();
            $productos = Producto::where('estado', 1)->orderBy('nombre')->get();
            $ingreso->load('productos', 'proveedor');

            return view('admin.ingresos.edit', compact('ingreso', 'proveedores', 'productos'));
        }

        return redirect()->route('admin.ingresos.index');
    }

    public function update(UpdateIngresoRequest $request, Ingreso $ingreso)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $ingreso) {
            KardexHelper::eliminarPorReferencia('Ingreso', $ingreso->id_ingreso);

            foreach ($ingreso->productos as $old) {
                Producto::where('id_producto', $old->id_producto)
                    ->decrement('stock', $old->pivot->cantidad);
            }

            $ingreso->productos()->detach();

            $ingreso->update([
                'proveedor_id_proveedor' => $data['proveedor_id_proveedor'],
                'fecha' => $data['fecha'] ?? now()->toDateString(),
            ]);

            $productosIds = $ingreso->productos->pluck('id_producto');

            foreach ($data['productos'] as $p) {
                $ingreso->productos()->attach($p['producto_id_producto'], [
                    'cantidad' => $p['cantidad'],
                ]);

                Producto::where('id_producto', $p['producto_id_producto'])
                    ->increment('stock', $p['cantidad']);

                $producto = Producto::find($p['producto_id_producto']);
                KardexHelper::registrar(
                    $producto,
                    'INGRESO',
                    'Ingreso',
                    $ingreso->id_ingreso,
                    $ingreso->correlativo,
                    $p['cantidad'],
                    'Ingreso de inventario'
                );
            }

            $productosIds = $productosIds->merge(collect($data['productos'])->pluck('producto_id_producto'))->unique();

            foreach ($productosIds as $id) {
                Producto::where('id_producto', $id)
                    ->update(['inventario' => DB::raw('stock')]);
            }
        });

        $this->olvidaIndex('ingresos');
        $this->olvidaIndex('productos');
        $this->auditarActualizar($ingreso, 'ingresos', "Actualizó el ingreso #{$ingreso->correlativo}");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Ingreso actualizado correctamente',
                'ingreso' => $ingreso->fresh()->load('proveedor', 'user', 'productos'),
            ]);
        }

        return redirect()->route('admin.ingresos.index')->with('success', 'Ingreso actualizado correctamente');
    }

    public function destroy(Ingreso $ingreso)
    {
        DB::transaction(function () use ($ingreso) {
            KardexHelper::eliminarPorReferencia('Ingreso', $ingreso->id_ingreso);

            foreach ($ingreso->productos as $p) {
                Producto::where('id_producto', $p->id_producto)
                    ->decrement('stock', $p->pivot->cantidad);
                Producto::where('id_producto', $p->id_producto)
                    ->update(['inventario' => DB::raw('stock')]);
            }
            $ingreso->productos()->detach();
            $ingreso->delete();
        });

        $this->olvidaIndex('ingresos');
        $this->olvidaIndex('productos');
        $this->auditarEliminar($ingreso, 'ingresos', "Eliminó el ingreso #{$ingreso->correlativo}");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Ingreso eliminado correctamente',
            ]);
        }

        return redirect()->route('admin.ingresos.index')->with('success', 'Ingreso eliminado correctamente');
    }
}
