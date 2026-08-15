<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAperturaRequest;
use App\Http\Requests\Admin\StoreEgresoRequest;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    use CacheaPanel, Audita;

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('caja', function () {
                $cajaAbierta = Caja::abierta()->with('movimientos', 'aperturaUser')->first();
                $cajasCerradas = Caja::where('estado', 'cerrada')
                    ->with('aperturaUser', 'cierreUser')
                    ->orderBy('id_caja', 'desc')
                    ->limit(5)
                    ->get();
                return view('admin.caja.index', compact('cajaAbierta', 'cajasCerradas'))->render();
            });
        }

        $cajaAbierta = Caja::abierta()->with('movimientos', 'aperturaUser')->first();
        $cajasCerradas = Caja::where('estado', 'cerrada')
            ->with('aperturaUser', 'cierreUser')
            ->orderBy('id_caja', 'desc')
            ->limit(5)
            ->get();
        return view('admin.caja.full', compact('cajaAbierta', 'cajasCerradas'));
    }

    public function apertura(StoreAperturaRequest $request)
    {
        if (Caja::abierta()->exists()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya hay una caja abierta. Ciérrela antes de abrir una nueva.',
                ], 422);
            }
            return redirect()->route('admin.caja.index')
                ->with('error', 'Ya hay una caja abierta.');
        }

        $caja = DB::transaction(function () use ($request) {
            return Caja::create([
                'monto_inicial' => $request->monto_inicial,
                'total_ingresos' => 0,
                'total_egresos' => 0,
                'estado' => 'abierta',
                'apertura_user_id' => auth()->id(),
                'apertura_at' => now(),
            ]);
        });

        $this->olvidaIndex('caja');
        $this->olvidaIndex('ventas');
        $this->olvidaIndex('atenciones');
        $this->auditar('caja', 'crear', $caja->id_caja, "Abrió caja con S/ {$caja->monto_inicial}");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Caja abierta correctamente con S/ ' . number_format($caja->monto_inicial, 2),
                'caja' => $caja,
            ]);
        }

        return redirect()->route('admin.caja.index')
            ->with('success', 'Caja abierta correctamente.');
    }

    public function egreso(StoreEgresoRequest $request)
    {
        $caja = Caja::abierta()->first();

        if (!$caja) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una caja abierta.',
                ], 422);
            }
            return redirect()->route('admin.caja.index')
                ->with('error', 'No hay una caja abierta.');
        }

        DB::transaction(function () use ($request, $caja) {
            CajaMovimiento::create([
                'caja_id_caja' => $caja->id_caja,
                'tipo' => 'egreso',
                'monto' => $request->monto,
                'descripcion' => $request->descripcion,
                'referencia_tipo' => 'egreso_manual',
                'user_id' => auth()->id(),
            ]);

            $caja->increment('total_egresos', $request->monto);
        });

        $this->olvidaIndex('caja');
        $this->auditar('caja', 'crear', $caja->id_caja, "Registró egreso de S/ {$request->monto} en caja: {$request->descripcion}");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Egreso registrado correctamente.',
            ]);
        }

        return redirect()->route('admin.caja.index')
            ->with('success', 'Egreso registrado correctamente.');
    }

    public function cierre()
    {
        $caja = Caja::abierta()->first();

        if (!$caja) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una caja abierta.',
                ], 422);
            }
            return redirect()->route('admin.caja.index')
                ->with('error', 'No hay una caja abierta.');
        }

        DB::transaction(function () use ($caja) {
            $montoFinal = $caja->monto_inicial + $caja->total_ingresos - $caja->total_egresos;

            $caja->update([
                'estado' => 'cerrada',
                'monto_final' => $montoFinal,
                'cierre_user_id' => auth()->id(),
                'cierre_at' => now(),
            ]);
        });

        $this->olvidaIndex('caja');
        $this->olvidaIndex('ventas');
        $this->olvidaIndex('atenciones');
        $this->auditar('caja', 'actualizar', $caja->id_caja, "Cerró caja con monto final S/ {$caja->fresh()->monto_final}");

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Caja cerrada correctamente. Monto final: S/ ' . number_format($caja->fresh()->monto_final, 2),
            ]);
        }

        return redirect()->route('admin.caja.index')
            ->with('success', 'Caja cerrada correctamente.');
    }

    public function historial()
    {
        $cajas = Caja::where('estado', 'cerrada')
            ->with('aperturaUser', 'cierreUser')
            ->orderBy('id_caja', 'desc')
            ->get();

        if (request()->ajax()) {
            return view('admin.caja.historial', compact('cajas'));
        }

        return redirect()->route('admin.caja.index');
    }

    public function historialShow(Caja $caja)
    {
        if ($caja->estado !== 'cerrada') {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La caja aún está abierta.',
                ], 422);
            }
            return redirect()->route('admin.caja.index');
        }

        $caja->load('movimientos.user', 'aperturaUser', 'cierreUser');

        if (request()->ajax()) {
            return view('admin.caja.historial-show', compact('caja'));
        }

        return redirect()->route('admin.caja.index');
    }

    public function formApertura()
    {
        if (request()->ajax()) {
            return view('admin.caja.apertura');
        }

        return redirect()->route('admin.caja.index');
    }

    public function formEgreso()
    {
        if (request()->ajax()) {
            return view('admin.caja.egreso');
        }

        return redirect()->route('admin.caja.index');
    }
}
