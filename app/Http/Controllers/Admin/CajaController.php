<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CerrarCajaRequest;
use App\Http\Requests\Admin\StoreAperturaRequest;
use App\Http\Requests\Admin\StoreEgresoRequest;
use App\Http\Requests\Admin\StoreIngresoCajaRequest;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\MetodoPago;
use App\Services\CajaService;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    use Audita, CacheaPanel;

    public function __construct(
        private readonly CajaService $cajas
    ) {}

    public function index()
    {
        $cargar = function () {
            $cajaAbierta = Caja::abierta()->with('aperturaUser')->first();
            $movimientos = $cajaAbierta
                ? CajaMovimiento::with('metodoPago', 'user')
                    ->where('caja_id_caja', $cajaAbierta->id_caja)
                    ->orderByDesc('id_movimiento')
                    ->paginate(10, ['*'], 'mov_page')
                    ->withQueryString()
                : null;
            $cajasCerradas = Caja::where('estado', 'cerrada')
                ->with('aperturaUser', 'cierreUser')
                ->orderBy('id_caja', 'desc')
                ->paginate(10, ['*'], 'caja_page')
                ->withQueryString();
            $efectivoEsperado = $cajaAbierta ? $this->cajas->efectivoDisponible($cajaAbierta) : null;
            $resumenMetodos = $cajaAbierta ? $this->cajas->resumenPorMetodo($cajaAbierta) : collect();

            return compact('cajaAbierta', 'movimientos', 'cajasCerradas', 'efectivoEsperado', 'resumenMetodos');
        };

        if (request()->ajax()) {
            return $this->getIndex('caja', function () use ($cargar) {
                return view('admin.caja.index', $cargar())->render();
            });
        }

        return view('admin.caja.full', $cargar());
    }

    public function apertura(StoreAperturaRequest $request)
    {
        try {
            $caja = $this->cajas->abrir($request->validated(), auth()->id());
        } catch (\RuntimeException $e) {
            return $this->respuestaError($request, $e->getMessage());
        }

        $this->olvidaIndex('caja');
        $this->olvidaIndex('ventas');
        $this->olvidaIndex('atenciones');
        $this->auditar('caja', 'crear', $caja->id_caja, "Abrió caja con S/ {$caja->monto_inicial}");

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Caja abierta correctamente con S/ '.number_format((float) $caja->monto_inicial, 2),
                'caja' => $caja->load('aperturaUser'),
            ]);
        }

        return redirect()->route('admin.caja.index')
            ->with('success', 'Caja abierta correctamente.');
    }

    public function ingreso(StoreIngresoCajaRequest $request)
    {
        try {
            $movimiento = DB::transaction(function () use ($request) {
                return $this->cajas->registrarIngresoManual(
                    $this->cajas->abiertaParaOperar(),
                    $request->validated(),
                    auth()->id()
                );
            });
        } catch (\RuntimeException $e) {
            return $this->respuestaError($request, $e->getMessage());
        }

        $this->olvidaIndex('caja');
        $this->auditar(
            'caja',
            'crear',
            $movimiento->caja_id_caja,
            "Registró ingreso de S/ {$movimiento->monto} en caja: {$movimiento->descripcion}"
        );

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Ingreso registrado correctamente.',
            ]);
        }

        return redirect()->route('admin.caja.index')
            ->with('success', 'Ingreso registrado correctamente.');
    }

    public function egreso(StoreEgresoRequest $request)
    {
        try {
            $movimiento = DB::transaction(function () use ($request) {
                return $this->cajas->registrarEgresoManual(
                    $this->cajas->abiertaParaOperar(),
                    $request->validated(),
                    auth()->id()
                );
            });
        } catch (\RuntimeException $e) {
            return $this->respuestaError($request, $e->getMessage());
        }

        $this->olvidaIndex('caja');
        $this->auditar(
            'caja',
            'crear',
            $movimiento->caja_id_caja,
            "Registró egreso de S/ {$movimiento->monto} en caja: {$movimiento->descripcion}"
        );

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Egreso registrado correctamente.',
            ]);
        }

        return redirect()->route('admin.caja.index')
            ->with('success', 'Egreso registrado correctamente.');
    }

    public function cierre(CerrarCajaRequest $request)
    {
        try {
            $caja = DB::transaction(function () use ($request) {
                $abierta = $this->cajas->abiertaParaOperar();

                return $this->cajas->cerrar($abierta, $request->validated(), auth()->id());
            });
        } catch (\RuntimeException $e) {
            return $this->respuestaError($request, $e->getMessage());
        }

        $this->olvidaIndex('caja');
        $this->olvidaIndex('ventas');
        $this->olvidaIndex('atenciones');
        $this->auditar(
            'caja',
            'actualizar',
            $caja->id_caja,
            "Cerró caja con efectivo contado S/ {$caja->efectivo_contado} y diferencia S/ {$caja->diferencia}"
        );

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Caja cerrada correctamente. Efectivo contado: S/ '
                    .number_format((float) $caja->efectivo_contado, 2)
                    .' — Diferencia: S/ '.number_format((float) $caja->diferencia, 2),
                'caja' => $caja,
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
            ->paginate(10)
            ->withQueryString();

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

        $caja->load('aperturaUser', 'cierreUser', 'arqueoUser');
        $movimientos = $caja->movimientos()
            ->with('metodoPago', 'user')
            ->orderByDesc('id_movimiento')
            ->paginate(10, ['*'], 'mov_page')
            ->withQueryString();

        if (request()->ajax()) {
            return view('admin.caja.historial-show', compact('caja', 'movimientos'));
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

    public function formIngreso()
    {
        if (request()->ajax()) {
            $metodosPago = MetodoPago::activos()->orderBy('nombre')->get();
            $categoriasIngreso = CajaMovimiento::CATEGORIAS_INGRESO_MANUAL;
            $caja = Caja::abierta()->first();
            $efectivoDisponible = $caja ? $this->cajas->efectivoDisponible($caja) : '0.00';

            return view('admin.caja.ingreso', compact('metodosPago', 'categoriasIngreso', 'efectivoDisponible'));
        }

        return redirect()->route('admin.caja.index');
    }

    public function formEgreso()
    {
        if (request()->ajax()) {
            $metodosPago = MetodoPago::activos()->orderBy('nombre')->get();
            $categoriasEgreso = CajaMovimiento::CATEGORIAS_EGRESO_MANUAL;
            $caja = Caja::abierta()->first();
            $efectivoDisponible = $caja ? $this->cajas->efectivoDisponible($caja) : '0.00';

            return view('admin.caja.egreso', compact('metodosPago', 'categoriasEgreso', 'efectivoDisponible'));
        }

        return redirect()->route('admin.caja.index');
    }

    public function formCierre()
    {
        $caja = Caja::abierta()->first();

        if (! $caja) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una caja abierta.',
                ], 422);
            }

            return redirect()->route('admin.caja.index');
        }

        if (request()->ajax()) {
            $efectivoEsperado = $this->cajas->efectivoDisponible($caja);
            $resumen = $this->cajas->totales($caja);
            $motivosDiferencia = Caja::MOTIVOS_DIFERENCIA;

            return view('admin.caja.cierre', compact('caja', 'efectivoEsperado', 'resumen', 'motivosDiferencia'));
        }

        return redirect()->route('admin.caja.index');
    }

    private function respuestaError($request, string $mensaje)
    {
        if ($request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $mensaje,
            ], 422);
        }

        return redirect()->route('admin.caja.index')->with('error', $mensaje);
    }
}
