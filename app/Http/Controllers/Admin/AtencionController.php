<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\StockHelper;
use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAtencionRequest;
use App\Http\Requests\Admin\UpdateAtencionRequest;
use App\Models\Atencion;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Servicio;
use Illuminate\Support\Facades\DB;

class AtencionController extends Controller
{
    use CacheaPanel, Audita;

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('atenciones', function () {
                $atenciones = Atencion::with('cliente', 'user', 'productos', 'servicios')
                    ->orderBy('id_atencion', 'desc')->get();
                $cajaAbierta = Caja::abierta()->exists();
                return view('admin.atenciones.index', compact('atenciones', 'cajaAbierta'))->render();
            });
        }

        $atenciones = Atencion::with('cliente', 'user', 'productos', 'servicios')
            ->orderBy('id_atencion', 'desc')->get();
        $cajaAbierta = Caja::abierta()->exists();
        return view('admin.atenciones.full', compact('atenciones', 'cajaAbierta'));
    }

    public function show(Atencion $atencione)
    {
        $atencione->load('cliente', 'user', 'productos', 'servicios');

        if (request()->ajax()) {
            return view('admin.atenciones.show', compact('atencione'));
        }

        return redirect()->route('admin.atenciones.index');
    }

    public function create()
    {
        if (request()->ajax()) {
            $productos = Producto::where('estado', 1)->where('stock', '>', 0)->orderBy('nombre')->get();
            $servicios = Servicio::orderBy('nombre')->get();
            return view('admin.atenciones.create', compact('productos', 'servicios'));
        }

        return redirect()->route('admin.atenciones.index');
    }

    public function store(StoreAtencionRequest $request)
    {
        $cajaAbierta = Caja::abierta()->first();
        if (!$cajaAbierta) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe abrir una caja antes de registrar una atención.',
                ], 422);
            }
            return redirect()->back()->with('error', 'Debe abrir una caja antes de registrar una atención.');
        }

        $data = $request->validated();

        try {
            $atencion = DB::transaction(function () use ($data, $cajaAbierta) {
                if (empty($data['cliente_id_cliente']) && !empty($data['cliente_data'])) {
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

                $total = 0;

                if (!empty($data['productos'])) {
                    $total += collect($data['productos'])->sum(fn($p) => $p['cantidad'] * $p['precio']);
                }
                if (!empty($data['servicios'])) {
                    $total += collect($data['servicios'])->sum(fn($s) => $s['precio']);
                }

                $atencion = Atencion::create([
                    'observacio' => $data['observacio'] ?? null,
                    'total' => $total,
                    'cliente_id_cliente' => $data['cliente_id_cliente'],
                    'user_id' => auth()->id(),
                ]);

                if (!empty($data['productos'])) {
                    foreach ($data['productos'] as $p) {
                        $subTotal = $p['cantidad'] * $p['precio'];
                        $atencion->productos()->attach($p['producto_id_producto'], [
                            'cantidad' => $p['cantidad'],
                            'sub_total' => $subTotal,
                        ]);

                        $producto = Producto::findOrFail($p['producto_id_producto']);
                        StockHelper::consumir($producto, $p['cantidad'], $atencion, 'atencion');
                    }
                }

                if (!empty($data['servicios'])) {
                    foreach ($data['servicios'] as $s) {
                        $atencion->servicios()->attach($s['servicio_id_servicio'], [
                            'subtotal' => $s['precio'],
                        ]);
                    }
                }

                CajaMovimiento::create([
                    'caja_id_caja' => $cajaAbierta->id_caja,
                    'tipo' => 'ingreso',
                    'monto' => $total,
                    'referencia_tipo' => 'atencion',
                    'referencia_id' => $atencion->id_atencion,
                    'user_id' => auth()->id(),
                ]);
                $cajaAbierta->increment('total_ingresos', $total);

                return $atencion;
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

        $this->olvidaIndex('atenciones');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarCrear($atencion, 'atenciones', "Registró la atención #{$atencion->id_atencion} por S/ {$atencion->total}");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Atención registrada correctamente',
                'atencion' => $atencion->load('cliente', 'user', 'productos', 'servicios'),
            ]);
        }

        return redirect()->route('admin.atenciones.index')->with('success', 'Atención registrada correctamente');
    }

    public function edit(Atencion $atencione)
    {
        if (request()->ajax()) {
            $productos = Producto::where('estado', 1)->orderBy('nombre')->get();
            $servicios = Servicio::orderBy('nombre')->get();
            $atencione->load('productos', 'servicios', 'cliente');
            $selectedCliente = $atencione->cliente;
            return view('admin.atenciones.edit', compact('atencione', 'productos', 'servicios', 'selectedCliente'));
        }

        return redirect()->route('admin.atenciones.index');
    }

    public function update(UpdateAtencionRequest $request, Atencion $atencione)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $atencione) {
            StockHelper::revertir($atencione, 'atencion');

            $atencione->productos()->detach();
            $atencione->servicios()->detach();

            $total = 0;

            if (!empty($data['productos'])) {
                $total += collect($data['productos'])->sum(fn($p) => $p['cantidad'] * $p['precio']);

                foreach ($data['productos'] as $p) {
                    $subTotal = $p['cantidad'] * $p['precio'];
                    $atencione->productos()->attach($p['producto_id_producto'], [
                        'cantidad' => $p['cantidad'],
                        'sub_total' => $subTotal,
                    ]);

                    $producto = Producto::findOrFail($p['producto_id_producto']);
                    StockHelper::consumir($producto, $p['cantidad'], $atencione, 'atencion');
                }
            }

            if (!empty($data['servicios'])) {
                $total += collect($data['servicios'])->sum(fn($s) => $s['precio']);

                foreach ($data['servicios'] as $s) {
                    $atencione->servicios()->attach($s['servicio_id_servicio'], [
                        'subtotal' => $s['precio'],
                    ]);
                }
            }

            $atencione->update([
                'total' => $total,
                'observacio' => $data['observacio'] ?? null,
                'cliente_id_cliente' => $data['cliente_id_cliente'],
            ]);

            $cajaAbierta = Caja::abierta()->first();
            if ($cajaAbierta) {
                $oldMovimiento = CajaMovimiento::where('caja_id_caja', $cajaAbierta->id_caja)
                    ->where('referencia_tipo', 'atencion')
                    ->where('referencia_id', $atencione->id_atencion)
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

        $this->olvidaIndex('atenciones');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarActualizar($atencione, 'atenciones', "Actualizó la atención #{$atencione->id_atencion}");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Atención actualizada correctamente',
                'atencion' => $atencione->fresh()->load('cliente', 'user', 'productos', 'servicios'),
            ]);
        }

        return redirect()->route('admin.atenciones.index')->with('success', 'Atención actualizada correctamente');
    }

    public function destroy(Atencion $atencione)
    {
        DB::transaction(function () use ($atencione) {
            StockHelper::revertir($atencione, 'atencion');
            $atencione->productos()->detach();
            $atencione->servicios()->detach();

            $cajaAbierta = Caja::abierta()->first();
            if ($cajaAbierta) {
                CajaMovimiento::where('caja_id_caja', $cajaAbierta->id_caja)
                    ->where('referencia_tipo', 'atencion')
                    ->where('referencia_id', $atencione->id_atencion)
                    ->delete();
                $cajaAbierta->decrement('total_ingresos', $atencione->total);
            }

            $atencione->delete();
        });

        $this->olvidaIndex('atenciones');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarEliminar($atencione, 'atenciones', "Eliminó la atención #{$atencione->id_atencion}");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Atención eliminada correctamente',
            ]);
        }

        return redirect()->route('admin.atenciones.index')->with('success', 'Atención eliminada correctamente');
    }
}
