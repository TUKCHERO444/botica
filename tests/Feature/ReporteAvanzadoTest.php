<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\Servicio;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class ReporteAvanzadoTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase, RegistraPagos;

    private Cliente $cliente;

    private Servicio $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();

        $this->cliente = Cliente::create([
            'dni' => '12345678',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Reporte',
        ]);

        $this->servicio = Servicio::create([
            'nombre' => 'Control',
            'precio' => 10,
        ]);
    }

    private function compraRecibida(string $documento = 'F001-000001', int $cantidad = 2): Ingreso
    {
        $this->crearCompraBorrador([
            'numero_documento' => $documento,
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => $cantidad,
                    'lote' => 'LOT-REPORTE',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 100,
                ],
            ],
        ]);

        $ingreso = Ingreso::where('numero_documento', $documento)->firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), ['pagado_desde_caja' => 0])
            ->assertOk();

        return $ingreso->fresh();
    }

    private function vender(int $cantidad): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                        'cantidad' => $cantidad,
                    ],
                ],
                ...$this->pagos((float) $this->unidad->precio_venta * $cantidad),
            ])
            ->assertOk();
    }

    private function atender(int $cantidad): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                        'cantidad' => $cantidad,
                    ],
                ],
                'servicios' => [
                    [
                        'servicio_id_servicio' => $this->servicio->id_servicio,
                        'precio' => 10,
                    ],
                ],
                ...$this->pagos((float) $this->unidad->precio_venta * $cantidad + 10),
            ])
            ->assertOk();
    }

    public function test_rentabilidad_calcula_ingresos_costo_y_margen(): void
    {
        $this->compraRecibida();
        $this->vender(10);
        $this->atender(5);

        $data = app(ReporteService::class)->rentabilidad(
            today()->toDateString(),
            today()->toDateString()
        );

        $this->assertSame(1, $data['cantidad']);
        $this->assertSame(22.5, (float) $data['total_ingresos']);
        $this->assertSame(15.0, (float) $data['total_costo']);
        $this->assertSame(7.5, (float) $data['total_margen']);

        $producto = $data['productos']->first();
        $this->assertSame(15.0, (float) $producto->cantidad_base);
        $this->assertSame(7.5, (float) $producto->margen);
        $this->assertEqualsWithDelta(33.33, (float) $producto->margen_pct, 0.01);
    }

    public function test_rentabilidad_filtra_por_categoria(): void
    {
        $this->compraRecibida();
        $this->vender(10);

        $otraCategoria = Categoria::create([
            'nombre' => 'Otros',
            'cantidad' => 0,
            'estado' => 1,
        ]);

        $data = app(ReporteService::class)->rentabilidad(
            today()->toDateString(),
            today()->toDateString(),
            $otraCategoria->id_categoria
        );

        $this->assertSame(0, $data['cantidad']);
        $this->assertSame(0.0, (float) $data['total_ingresos']);
    }

    public function test_reporte_de_lotes_calcula_valor_y_filtra_por_vencimiento(): void
    {
        $this->compraRecibida();

        $service = app(ReporteService::class);

        $todos = $service->lotes();
        $this->assertSame(1, $todos['total_lotes']);
        $this->assertSame(200.0, (float) $todos['total_disponible']);
        $this->assertSame(200.0, (float) $todos['total_valor']);
        $this->assertSame(1.0, (float) $todos['lotes']->first()->costo_promedio);

        $proximos30 = $service->lotes(null, null, 30);
        $this->assertSame(0, $proximos30['total_lotes']);

        $proximos400 = $service->lotes(null, null, 400);
        $this->assertSame(1, $proximos400['total_lotes']);
    }

    public function test_reporte_de_movimientos_filtra_por_tipo(): void
    {
        $ingreso = $this->compraRecibida();
        $detalle = $ingreso->detalles()->firstOrFail();

        // Devolución a proveedor: DEVOLUCION
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), [
                'ingreso_id_ingreso' => $ingreso->id_ingreso,
                'fecha' => today()->toDateString(),
                'motivo' => 'Prueba de reporte',
                'reembolsado_a_caja' => 0,
                'lineas' => [
                    [
                        'detalle_ingreso_id' => $detalle->id_detalle_ingreso,
                        'cantidad' => 1,
                    ],
                ],
            ])
            ->assertOk();

        // Ajuste por daño: AJUSTE
        $lote = $ingreso->detalles()->firstOrFail()->lote;
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), [
                'tipo' => 'dano',
                'fecha' => today()->toDateString(),
                'motivo' => 'Producto roto',
                'lineas' => [
                    [
                        'producto_lote_id' => $lote->id,
                        'cantidad' => 5,
                    ],
                ],
            ])
            ->assertOk();

        $service = app(ReporteService::class);

        $devoluciones = $service->movimientos(
            today()->toDateString(),
            today()->toDateString(),
            'DEVOLUCION'
        );
        $this->assertSame(1, $devoluciones['cantidad']);
        $this->assertSame(-100.0, (float) $devoluciones['total_salidas']);

        $ajustes = $service->movimientos(
            today()->toDateString(),
            today()->toDateString(),
            'AJUSTE'
        );
        $this->assertSame(1, $ajustes['cantidad']);
        $this->assertSame(-5.0, (float) $ajustes['total_salidas']);

        $todos = $service->movimientos(
            today()->toDateString(),
            today()->toDateString()
        );
        $this->assertSame(3, $todos['cantidad']);
    }

    public function test_vistas_previas_de_los_nuevos_reportes_renderizan(): void
    {
        $this->compraRecibida();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.preview', [
                'tipo' => 'rentabilidad',
                'desde' => today()->toDateString(),
                'hasta' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Reporte de Rentabilidad');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.preview', ['tipo' => 'lotes']))
            ->assertOk()
            ->assertSee('Reporte de Lotes y Costos');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.preview', [
                'tipo' => 'movimientos',
                'desde' => today()->toDateString(),
                'hasta' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Reporte de Movimientos de Inventario');
    }

    public function test_reporte_de_inventario_no_muestra_inventario_legacy(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.preview', ['tipo' => 'inventario']))
            ->assertOk()
            ->assertDontSee('Inventario Inicial');
    }

    public function test_formulario_de_compra_incluye_alerta_de_costo(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.create'))
            ->assertOk()
            ->assertSee('data-precio-venta', false)
            ->assertSee('producto-alerta', false);
    }
}
