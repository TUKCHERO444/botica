<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CantidadHelper;
use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Admin\Traits\FiltraHistorial;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnularAtencionRequest;
use App\Http\Requests\Admin\StoreAtencionRequest;
use App\Models\Atencion;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\LoteConsumo;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\ProductoPresentacion;
use App\Models\Servicio;
use App\Models\User;
use App\Services\AnulacionAtencionService;
use App\Services\CajaService;
use App\Services\InventarioService;
use App\Services\PagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtencionController extends Controller
{
    use Audita, CacheaPanel, FiltraHistorial;

    public function __construct(
        private readonly InventarioService $inventario,
        private readonly PagoService $pagos,
        private readonly CajaService $cajas,
        private readonly AnulacionAtencionService $anulaciones
    ) {}

    private function generarCorrelativo(int $idAtencion): string
    {
        $sufijo = str_pad($idAtencion, 8, '0', STR_PAD_LEFT);

        return "A001-{$sufijo}";
    }

    public function index()
    {
        // Vista del día: solo las atenciones del usuario autenticado registradas hoy.
        $atenciones = Atencion::with('cliente', 'user')
            ->withCount(['productos', 'servicios'])
            ->where('user_id', auth()->id())
            ->delDia()
            ->orderByDesc('id_atencion')
            ->paginate(10)
            ->withQueryString();

        $resumen = Atencion::where('user_id', auth()->id())
            ->delDia()
            ->where('estado', Atencion::ESTADO_CONFIRMADA)
            ->selectRaw('count(*) as cantidad, coalesce(sum(total), 0) as total')
            ->first();

        $cajaAbierta = Caja::abierta()->exists();
        $hoy = today();
        $datos = compact('atenciones', 'resumen', 'cajaAbierta', 'hoy');

        if (request()->ajax()) {
            return $this->getIndex('atenciones', fn () => view('admin.atenciones.index', $datos)->render());
        }

        return view('admin.atenciones.full', $datos);
    }

    public function historial(Request $request)
    {
        [$fecha, $desde, $hasta] = $this->rangoFechas($request);

        $base = Atencion::query()
            ->deUsuario($request->integer('user_id') ?: null)
            ->entreFechas($fecha, $desde, $hasta)
            ->deEstado($request->get('estado'))
            ->buscar($request->get('q'));

        $atenciones = (clone $base)
            ->with('cliente', 'user')
            ->withCount(['productos', 'servicios'])
            ->orderByDesc('id_atencion')
            ->paginate(10)
            ->withQueryString();

        $resumen = (clone $base)
            ->where('estado', Atencion::ESTADO_CONFIRMADA)
            ->selectRaw('count(*) as cantidad, coalesce(sum(total), 0) as total')
            ->first();

        $usuarios = User::orderBy('name')->get(['id', 'name']);
        $filtros = compact('fecha', 'desde', 'hasta');
        $datos = compact('atenciones', 'resumen', 'usuarios', 'filtros');

        if ($request->ajax()) {
            return $this->getIndex('atenciones', fn () => view('admin.atenciones.historial', $datos)->render());
        }

        return view('admin.atenciones.historial-full', $datos);
    }

    public function show(Atencion $atencione)
    {
        $atencione->load('cliente', 'user', 'anuladaPor', 'productos', 'servicios', 'pagos.metodoPago');
        $presentaciones = $this->presentacionesDe($atencione);
        $lotesPorProducto = LoteConsumo::with('lote')
            ->where('referencia_tipo', 'Atencion')
            ->where('referencia_id', $atencione->id_atencion)
            ->get()
            ->groupBy(fn ($consumo) => $consumo->lote?->producto_id);

        if (request()->ajax()) {
            return view('admin.atenciones.show', compact('atencione', 'presentaciones', 'lotesPorProducto'));
        }

        return redirect()->route('admin.atenciones.index');
    }

    public function ticket(Atencion $atencione)
    {
        $atencione->load('cliente', 'user', 'anuladaPor', 'productos', 'servicios', 'pagos.metodoPago');
        $presentaciones = $this->presentacionesDe($atencione);
        $nueva = request()->boolean('nueva');

        return view('admin.atenciones.ticket', [
            'atencione' => $atencione,
            'presentaciones' => $presentaciones,
            'nueva' => $nueva,
            'titulo' => 'Ticket '.$atencione->correlativo,
            'volverUrl' => route('admin.atenciones.index'),
            'aviso' => $nueva
                ? 'Atención registrada correctamente. Puede imprimir el ticket o volver al panel sin imprimirlo.'
                : null,
        ]);
    }

    public function create()
    {
        if (request()->ajax()) {
            $presentaciones = $this->presentacionesDisponibles();
            $servicios = Servicio::orderBy('nombre')->get();
            $metodosPago = MetodoPago::activos()->orderBy('nombre')->get();

            return view('admin.atenciones.create', compact('presentaciones', 'servicios', 'metodosPago'));
        }

        return redirect()->route('admin.atenciones.index');
    }

    public function store(StoreAtencionRequest $request)
    {
        if (! Caja::abierta()->exists()) {
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
            $atencion = DB::transaction(function () use ($data) {
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

                $lineas = $this->resolverLineas($data['productos'] ?? []);
                $total = $this->sumarTotales($lineas, $data['servicios'] ?? []);

                $atencion = Atencion::create([
                    'correlativo' => null,
                    'observacio' => $data['observacio'] ?? null,
                    'total' => $total,
                    'cliente_id_cliente' => $data['cliente_id_cliente'],
                    'user_id' => auth()->id(),
                    'estado' => Atencion::ESTADO_CONFIRMADA,
                ]);

                $atencion->update([
                    'correlativo' => $this->generarCorrelativo($atencion->id_atencion),
                ]);

                foreach ($lineas as $linea) {
                    $detalleId = DB::table('atencion_producto')->insertGetId([
                        'atencion_id_atencion' => $atencion->id_atencion,
                        'producto_id_producto' => $linea['producto']->id_producto,
                        'producto_presentacion_id' => $linea['presentacion']->id_producto_presentacion,
                        'cantidad' => $linea['cantidad'],
                        'factor' => $linea['presentacion']->factor,
                        'cantidad_base' => $linea['cantidad_base'],
                        'precio_unitario' => $linea['precio'],
                        'sub_total' => $linea['sub_total'],
                    ]);

                    $consumo = $this->inventario->consumir(
                        $linea['presentacion'],
                        $linea['cantidad'],
                        'ATENCION',
                        'Atencion',
                        $atencion->id_atencion,
                        $atencion->correlativo,
                        'Atención registrada'
                    );

                    DB::table('atencion_producto')->where('id_atencion_producto', $detalleId)->update([
                        'costo_total' => $consumo['costo_total'],
                    ]);
                }

                foreach ($data['servicios'] as $servicio) {
                    $atencion->servicios()->attach($servicio['servicio_id_servicio'], [
                        'subtotal' => $servicio['precio'],
                    ]);
                }

                $this->cajas->registrarPagos(
                    'atencion',
                    $atencion->id_atencion,
                    $data['pagos'],
                    "Atención {$atencion->correlativo}",
                    auth()->id()
                );

                $this->pagos->registrar(Pago::TIPO_ATENCION, $atencion->id_atencion, $data['pagos'], auth()->id());

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
        $this->auditarCrear($atencion, 'atenciones', "Registró la atención {$atencion->correlativo} por S/ {$atencion->total}");

        // Al concretar la atención se muestra el ticket para imprimirlo.
        $ticketUrl = route('admin.atenciones.ticket', [
            'atencione' => $atencion->id_atencion,
            'nueva' => 1,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Atención registrada correctamente',
                'atencion' => $atencion->load('cliente', 'user', 'productos', 'servicios'),
                'redirect' => $ticketUrl,
            ]);
        }

        return redirect()->to($ticketUrl);
    }

    public function edit(Atencion $atencione)
    {
        return $this->respuestaBloqueo(
            request(),
            'Las atenciones confirmadas no se editan. Si hubo un error, anula la atención con un motivo.'
        );
    }

    public function update(Request $request, Atencion $atencione)
    {
        return $this->respuestaBloqueo(
            $request,
            'Las atenciones confirmadas no se editan. Si hubo un error, anula la atención con un motivo.'
        );
    }

    public function destroy(Atencion $atencione)
    {
        return $this->respuestaBloqueo(
            request(),
            'Las atenciones no se eliminan. Anula la atención con un motivo para conservar la trazabilidad.'
        );
    }

    public function formAnular(Atencion $atencione)
    {
        if ($atencione->estaAnulada()) {
            return $this->respuestaBloqueo(request(), 'La atención ya fue anulada.');
        }

        if (! $this->esAjax(request())) {
            return redirect()->route('admin.atenciones.index');
        }

        $atencione->load('cliente', 'user');

        return view('admin.atenciones.anular', compact('atencione'));
    }

    public function anular(AnularAtencionRequest $request, Atencion $atencione)
    {
        try {
            $atencione = $this->anulaciones->anular(
                $atencione,
                $request->validated()['motivo_anulacion'],
                auth()->id()
            );
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('atenciones');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarActualizar(
            $atencione,
            'atenciones',
            "Anuló la atención {$atencione->correlativo}: {$atencione->motivo_anulacion}"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Atención anulada y stock revertido correctamente.',
                'atencion' => $atencione,
            ]);
        }

        return redirect()->route('admin.atenciones.index')
            ->with('success', 'Atención anulada correctamente.');
    }

    private function respuestaBloqueo(Request $request, string $mensaje)
    {
        return $this->error($request, $mensaje);
    }

    private function error(Request $request, string $mensaje)
    {
        if ($this->esAjax($request)) {
            return response()->json([
                'success' => false,
                'message' => $mensaje,
            ], 422);
        }

        return redirect()->route('admin.atenciones.index')->with('error', $mensaje);
    }

    private function esAjax(Request $request): bool
    {
        return $request->ajax() || $request->expectsJson();
    }

    private function presentacionesDisponibles(bool $soloConStock = true)
    {
        return ProductoPresentacion::with(['producto.unidadBase', 'producto.lotesVendibles', 'producto.presentaciones.presentacion', 'presentacion'])
            ->where('activo', true)
            ->whereHas('producto', function ($q) use ($soloConStock) {
                $q->where('estado', 1);

                if ($soloConStock) {
                    $q->conStockVendible();
                }
            })
            ->join('producto', 'producto.id_producto', '=', 'producto_presentacion.producto_id_producto')
            ->orderBy('producto.nombre')
            ->select('producto_presentacion.*')
            ->get();
    }

    private function presentacionesDe(Atencion $atencion)
    {
        $ids = $atencion->productos->pluck('pivot.producto_presentacion_id')->filter()->unique();

        return ProductoPresentacion::with('presentacion')
            ->whereIn('id_producto_presentacion', $ids)
            ->get()
            ->keyBy('id_producto_presentacion');
    }

    private function resolverLineas(array $productos): array
    {
        return collect($productos)->map(function ($p) {
            $presentacion = ProductoPresentacion::with('producto')
                ->findOrFail($p['producto_presentacion_id']);

            $cantidad = (string) $p['cantidad'];
            $precio = (string) $presentacion->precio_venta;
            $subTotal = CantidadHelper::redondear(
                CantidadHelper::multiplicar($cantidad, $precio, 4),
                2
            );

            return [
                'presentacion' => $presentacion,
                'producto' => $presentacion->producto,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'sub_total' => $subTotal,
                'cantidad_base' => CantidadHelper::aBase($cantidad, $presentacion->factor),
            ];
        })->all();
    }

    private function sumarTotales(array $lineas, array $servicios): string
    {
        $total = '0.00';

        foreach ($lineas as $linea) {
            $total = CantidadHelper::sumar($total, $linea['sub_total'], 2);
        }

        foreach ($servicios as $servicio) {
            $total = CantidadHelper::sumar($total, $servicio['precio'], 2);
        }

        return $total;
    }
}
