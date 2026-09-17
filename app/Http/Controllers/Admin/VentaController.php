<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\KardexHelper;
use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVentaRequest;
use App\Http\Requests\Admin\UpdateVentaRequest;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    use Audita, CacheaPanel;

    private function generarCorrelativo(int $idVenta): string
    {
        $sufijo = str_pad($idVenta, 8, '0', STR_PAD_LEFT);

        return "T001-{$sufijo}";
    }

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('ventas', function () {
                $ventas = Venta::with('cliente', 'user')->orderBy('id_venta', 'desc')->get();
                $cajaAbierta = Caja::abierta()->exists();

                return view('admin.ventas.index', compact('ventas', 'cajaAbierta'))->render();
            });
        }

        $ventas = Venta::with('cliente', 'user')->orderBy('id_venta', 'desc')->get();
        $cajaAbierta = Caja::abierta()->exists();

        return view('admin.ventas.full', compact('ventas', 'cajaAbierta'));
    }

    public function show(Venta $venta)
    {
        $venta->load('cliente', 'user', 'productos');

        if (request()->ajax()) {
            return view('admin.ventas.show', compact('venta'));
        }

        return redirect()->route('admin.ventas.index');
    }

    public function create()
    {
        if (request()->ajax()) {
            $productos = Producto::where('estado', 1)->where('stock', '>', 0)->orderBy('nombre')->get();
            $nextId = (Venta::max('id_venta') ?? 0) + 1;
            $siguienteCorrelativo = $this->generarCorrelativo($nextId);

            return view('admin.ventas.create', compact('productos', 'siguienteCorrelativo'));
        }

        return redirect()->route('admin.ventas.index');
    }

    public function store(StoreVentaRequest $request)
    {
        $cajaAbierta = Caja::abierta()->first();
        if (! $cajaAbierta) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe abrir una caja antes de registrar una venta.',
                ], 422);
            }

            return redirect()->back()->with('error', 'Debe abrir una caja antes de registrar una venta.');
        }

        $data = $request->validated();

        try {
            $venta = DB::transaction(function () use ($data, $cajaAbierta) {
                if (empty($data['cliente_id_cliente']) && ! empty($data['cliente_data'])) {
                    $cliente = Cliente::create([
                        'dni' => $data['cliente_data']['dni'],
                        'nombre' => $data['cliente_data']['nombre'],
                        'apellido_paterno' => $data['cliente_data']['apellido_paterno'],
                        'apellido_materno' => $data['cliente_data']['apellido_materno'],
                        'registro_web' => false,
                    ]);

                    $this->auditarCrear($cliente, 'clientes', "Cliente auto-creado desde consulta RENIEC (DNI: {$cliente->dni})");

                    $data['cliente_id_cliente'] = $cliente->id_cliente;
                }

                $total = collect($data['productos'])->sum(fn ($p) => $p['cantidad'] * $p['precio']);

                $venta = Venta::create([
                    'correlativo' => 'TEMP',
                    'total' => $total,
                    'cliente_id_cliente' => $data['cliente_id_cliente'],
                    'user_id' => auth()->id(),
                ]);

                $venta->update(['correlativo' => $this->generarCorrelativo($venta->id_venta)]);

                foreach ($data['productos'] as $p) {
                    $subTotal = $p['cantidad'] * $p['precio'];
                    $venta->productos()->attach($p['producto_id_producto'], [
                        'cantidad' => $p['cantidad'],
                        'sub_total' => $subTotal,
                    ]);

                    $producto = Producto::findOrFail($p['producto_id_producto']);
                    $producto->decrement('stock', $p['cantidad']);
                    KardexHelper::registrar(
                        $producto,
                        'VENTA',
                        'Venta',
                        $venta->id_venta,
                        $venta->correlativo,
                        -$p['cantidad'],
                        'Venta registrada'
                    );
                }

                CajaMovimiento::create([
                    'caja_id_caja' => $cajaAbierta->id_caja,
                    'tipo' => 'ingreso',
                    'monto' => $total,
                    'referencia_tipo' => 'venta',
                    'referencia_id' => $venta->id_venta,
                    'user_id' => auth()->id(),
                ]);
                $cajaAbierta->increment('total_ingresos', $total);

                return $venta;
            });
        } catch (\RuntimeException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        $this->olvidaIndex('ventas');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarCrear($venta, 'ventas', "Registró la venta #{$venta->correlativo} por S/ {$venta->total}");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Venta registrada correctamente',
                'venta' => $venta->load('cliente', 'user', 'productos'),
            ]);
        }

        return redirect()->route('admin.ventas.index')->with('success', 'Venta registrada correctamente');
    }

    public function edit(Venta $venta)
    {
        if (request()->ajax()) {
            $productos = Producto::where('estado', 1)->orderBy('nombre')->get();
            $venta->load('productos', 'cliente');
            $selectedCliente = $venta->cliente;

            return view('admin.ventas.edit', compact('venta', 'productos', 'selectedCliente'));
        }

        return redirect()->route('admin.ventas.index');
    }

    public function update(UpdateVentaRequest $request, Venta $venta)
    {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $venta) {
                foreach ($venta->productos as $old) {
                    Producto::where('id_producto', $old->id_producto)
                        ->increment('stock', $old->pivot->cantidad);
                }
                KardexHelper::eliminarPorReferencia('Venta', $venta->id_venta);

                $venta->productos()->detach();

                $total = collect($data['productos'])->sum(fn ($p) => $p['cantidad'] * $p['precio']);

                $venta->update([
                    'total' => $total,
                    'cliente_id_cliente' => $data['cliente_id_cliente'],
                ]);

                foreach ($data['productos'] as $p) {
                    $subTotal = $p['cantidad'] * $p['precio'];
                    $venta->productos()->attach($p['producto_id_producto'], [
                        'cantidad' => $p['cantidad'],
                        'sub_total' => $subTotal,
                    ]);

                    $producto = Producto::findOrFail($p['producto_id_producto']);
                    $producto->decrement('stock', $p['cantidad']);
                    KardexHelper::registrar(
                        $producto,
                        'VENTA',
                        'Venta',
                        $venta->id_venta,
                        $venta->correlativo,
                        -$p['cantidad'],
                        'Venta registrada'
                    );
                }

                $cajaAbierta = Caja::abierta()->first();
                if ($cajaAbierta) {
                    $oldMovimiento = CajaMovimiento::where('caja_id_caja', $cajaAbierta->id_caja)
                        ->where('referencia_tipo', 'venta')
                        ->where('referencia_id', $venta->id_venta)
                        ->first();
                    if ($oldMovimiento) {
                        $diferencia = $total - $oldMovimiento->monto;
                        $oldMovimiento->update(['monto' => $total]);
                        if ($diferencia != 0) {
                            if ($diferencia > 0) {
                                $cajaAbierta->increment('total_ingresos', $diferencia);
                            } else {
                                $cajaAbierta->decrement('total_ingresos', abs($diferencia));
                            }
                        }
                    }
                }
            });
        } catch (\RuntimeException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        $this->olvidaIndex('ventas');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarActualizar($venta, 'ventas', "Actualizó la venta #{$venta->correlativo}");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Venta actualizada correctamente',
                'venta' => $venta->fresh()->load('cliente', 'user', 'productos'),
            ]);
        }

        return redirect()->route('admin.ventas.index')->with('success', 'Venta actualizada correctamente');
    }

    public function destroy(Venta $venta)
    {
        DB::transaction(function () use ($venta) {
            foreach ($venta->productos as $old) {
                Producto::where('id_producto', $old->id_producto)
                    ->increment('stock', $old->pivot->cantidad);
            }
            KardexHelper::eliminarPorReferencia('Venta', $venta->id_venta);

            $venta->productos()->detach();

            $cajaAbierta = Caja::abierta()->first();
            if ($cajaAbierta) {
                CajaMovimiento::where('caja_id_caja', $cajaAbierta->id_caja)
                    ->where('referencia_tipo', 'venta')
                    ->where('referencia_id', $venta->id_venta)
                    ->delete();
                $cajaAbierta->decrement('total_ingresos', $venta->total);
            }

            $venta->delete();
        });

        $this->olvidaIndex('ventas');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarEliminar($venta, 'ventas', "Eliminó la venta #{$venta->correlativo}");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Venta eliminada correctamente',
            ]);
        }

        return redirect()->route('admin.ventas.index')->with('success', 'Venta eliminada correctamente');
    }
}
