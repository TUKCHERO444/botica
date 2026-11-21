<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CantidadHelper;
use App\Http\Controllers\Admin\Traits\Audita;
use App\Http\Controllers\Admin\Traits\CacheaPanel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnularVentaRequest;
use App\Http\Requests\Admin\StoreVentaRequest;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\LoteConsumo;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\ProductoPresentacion;
use App\Models\Venta;
use App\Services\AnulacionVentaService;
use App\Services\CajaService;
use App\Services\InventarioService;
use App\Services\PagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    use Audita, CacheaPanel;

    public function __construct(
        private readonly InventarioService $inventario,
        private readonly AnulacionVentaService $anulaciones,
        private readonly PagoService $pagos,
        private readonly CajaService $cajas
    ) {}

    private function generarCorrelativo(int $idVenta): string
    {
        $sufijo = str_pad($idVenta, 8, '0', STR_PAD_LEFT);

        return "T001-{$sufijo}";
    }

    public function index()
    {
        if (request()->ajax()) {
            return $this->getIndex('ventas', function () {
                $ventas = Venta::with('cliente', 'user')->orderBy('id_venta', 'desc')->paginate(10)->withQueryString();
                $cajaAbierta = Caja::abierta()->exists();

                return view('admin.ventas.index', compact('ventas', 'cajaAbierta'))->render();
            });
        }

        $ventas = Venta::with('cliente', 'user')->orderBy('id_venta', 'desc')->paginate(10)->withQueryString();
        $cajaAbierta = Caja::abierta()->exists();

        return view('admin.ventas.full', compact('ventas', 'cajaAbierta'));
    }

    public function show(Venta $venta)
    {
        $venta->load('cliente', 'user', 'productos', 'pagos.metodoPago');
        $presentaciones = $this->presentacionesDe($venta);
        $lotesPorProducto = LoteConsumo::with('lote')
            ->where('referencia_tipo', 'Venta')
            ->where('referencia_id', $venta->id_venta)
            ->get()
            ->groupBy(fn ($consumo) => $consumo->lote?->producto_id);

        if (request()->ajax()) {
            return view('admin.ventas.show', compact('venta', 'presentaciones', 'lotesPorProducto'));
        }

        return redirect()->route('admin.ventas.index');
    }

    public function ticket(Venta $venta)
    {
        $venta->load('cliente', 'user', 'anuladaPor', 'productos', 'pagos.metodoPago');
        $presentaciones = $this->presentacionesDe($venta);
        $nueva = request()->boolean('nueva');

        return view('admin.ventas.ticket', [
            'venta' => $venta,
            'presentaciones' => $presentaciones,
            'nueva' => $nueva,
            'titulo' => 'Ticket '.$venta->correlativo,
            'volverUrl' => route('admin.ventas.index'),
            'aviso' => $nueva
                ? 'Venta registrada correctamente. Puede imprimir el ticket o volver al panel sin imprimirlo.'
                : null,
        ]);
    }

    public function searchPresentaciones(Request $request)
    {
        $q = trim((string) $request->get('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $soloConStock = $request->boolean('solo_stock', true);

        $resultados = ProductoPresentacion::query()
            ->with(['producto.unidadBase', 'producto.lotesVendibles', 'presentacion'])
            ->where('producto_presentacion.activo', true)
            ->whereHas('producto', function ($query) use ($soloConStock) {
                $query->where('estado', 1);

                if ($soloConStock) {
                    $query->conStockVendible();
                }
            })
            ->where(function ($query) use ($q) {
                $query->where('producto_presentacion.codigo_barras', 'like', "%{$q}%")
                    ->orWhere('producto_presentacion.nombre', 'like', "%{$q}%")
                    ->orWhereHas('producto', fn ($producto) => $producto->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('presentacion', fn ($presentacion) => $presentacion->where('nombre', 'like', "%{$q}%"));
            })
            ->join('producto', 'producto.id_producto', '=', 'producto_presentacion.producto_id_producto')
            ->orderByRaw('CASE WHEN producto_presentacion.codigo_barras = ? THEN 0 ELSE 1 END', [$q])
            ->orderBy('producto.nombre')
            ->orderBy('producto_presentacion.orden')
            ->limit(15)
            ->select('producto_presentacion.*')
            ->get()
            ->map(function (ProductoPresentacion $pp) {
                $producto = $pp->producto;

                return [
                    'id' => $pp->id_producto_presentacion,
                    'label' => trim(($producto?->nombre ?? '').' — '.$pp->nombre_completo, ' —'),
                    'producto' => $producto?->nombre,
                    'presentacion' => $pp->nombre_completo,
                    'codigo_barras' => $pp->codigo_barras,
                    'precio' => (float) $pp->precio_venta,
                    'precio_formateado' => 'S/ '.number_format((float) $pp->precio_venta, 2),
                    'stock' => (float) ($producto?->stock ?? 0),
                    'stock_formateado' => $producto?->stock_formateado ?? '',
                    'paso' => (float) $pp->paso,
                    'factor' => (float) $pp->factor,
                    ...($producto?->resumenInventario() ?? []),
                ];
            });

        return response()->json($resultados);
    }

    public function create()
    {
        if (request()->ajax()) {
            $nextId = (Venta::max('id_venta') ?? 0) + 1;
            $siguienteCorrelativo = $this->generarCorrelativo($nextId);
            $metodosPago = MetodoPago::activos()->orderBy('nombre')->get();

            return view('admin.ventas.create', compact('siguienteCorrelativo', 'metodosPago'));
        }

        return redirect()->route('admin.ventas.index');
    }

    public function store(StoreVentaRequest $request)
    {
        if (! Caja::abierta()->exists()) {
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
            $venta = DB::transaction(function () use ($data) {
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

                $lineas = $this->resolverLineas($data['productos']);
                $total = $this->sumarTotales($lineas);

                $venta = Venta::create([
                    'correlativo' => 'TEMP',
                    'total' => $total,
                    'cliente_id_cliente' => $data['cliente_id_cliente'],
                    'user_id' => auth()->id(),
                    'estado' => Venta::ESTADO_CONFIRMADA,
                ]);

                $venta->update(['correlativo' => $this->generarCorrelativo($venta->id_venta)]);

                foreach ($lineas as $linea) {
                    $detalleId = DB::table('detalle_venta')->insertGetId([
                        'venta_id_venta' => $venta->id_venta,
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
                        'VENTA',
                        'Venta',
                        $venta->id_venta,
                        $venta->correlativo,
                        'Venta registrada'
                    );

                    DB::table('detalle_venta')->where('id_detalle_venta', $detalleId)->update([
                        'costo_total' => $consumo['costo_total'],
                    ]);
                }

                $this->cajas->registrarPagos(
                    'venta',
                    $venta->id_venta,
                    $data['pagos'],
                    "Venta {$venta->correlativo}",
                    auth()->id()
                );

                $this->pagos->registrar(Pago::TIPO_VENTA, $venta->id_venta, $data['pagos'], auth()->id());

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

        // Al concretar la venta se muestra el ticket para imprimirlo (flujo de caja/ventanilla).
        $ticketUrl = route('admin.ventas.ticket', [
            'venta' => $venta->id_venta,
            'nueva' => 1,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Venta registrada correctamente',
                'venta' => $venta->load('cliente', 'user', 'productos'),
                'redirect' => $ticketUrl,
            ]);
        }

        return redirect()->to($ticketUrl);
    }

    public function edit(Venta $venta)
    {
        return $this->respuestaBloqueo(
            request(),
            'Las ventas confirmadas no se editan. Si hubo un error, anula la venta con un motivo.'
        );
    }

    public function update(Request $request, Venta $venta)
    {
        return $this->respuestaBloqueo(
            $request,
            'Las ventas confirmadas no se editan. Si hubo un error, anula la venta con un motivo.'
        );
    }

    public function destroy(Venta $venta)
    {
        return $this->respuestaBloqueo(
            request(),
            'Las ventas no se eliminan. Anula la venta con un motivo para conservar la trazabilidad.'
        );
    }

    public function formAnular(Venta $venta)
    {
        if ($venta->estaAnulada()) {
            return $this->respuestaBloqueo(request(), 'La venta ya fue anulada.');
        }

        if (! $this->esAjax(request())) {
            return redirect()->route('admin.ventas.index');
        }

        $venta->load('cliente', 'user');

        return view('admin.ventas.anular', compact('venta'));
    }

    public function anular(AnularVentaRequest $request, Venta $venta)
    {
        try {
            $venta = $this->anulaciones->anular(
                $venta,
                $request->validated()['motivo_anulacion'],
                auth()->id()
            );
        } catch (\RuntimeException $e) {
            return $this->error($request, $e->getMessage());
        }

        $this->olvidaIndex('ventas');
        $this->olvidaIndex('productos');
        $this->olvidaIndex('caja');
        $this->auditarActualizar(
            $venta,
            'ventas',
            "Anuló la venta {$venta->correlativo}: {$venta->motivo_anulacion}"
        );

        if ($this->esAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Venta anulada y stock revertido correctamente.',
                'venta' => $venta,
            ]);
        }

        return redirect()->route('admin.ventas.index')
            ->with('success', 'Venta anulada correctamente.');
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

        return redirect()->route('admin.ventas.index')->with('error', $mensaje);
    }

    private function esAjax(Request $request): bool
    {
        return $request->ajax() || $request->expectsJson();
    }

    private function presentacionesDe(Venta $venta)
    {
        $ids = $venta->productos->pluck('pivot.producto_presentacion_id')->filter()->unique();

        return ProductoPresentacion::with(['producto.unidadBase', 'presentacion'])
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

    private function sumarTotales(array $lineas): string
    {
        $total = '0.00';

        foreach ($lineas as $linea) {
            $total = CantidadHelper::sumar($total, $linea['sub_total'], 2);
        }

        return $total;
    }
}
