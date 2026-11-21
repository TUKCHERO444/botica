<?php

namespace Tests\Feature;

use App\Models\AjusteInventario;
use App\Models\Cliente;
use App\Models\DetalleAjusteInventario;
use App\Models\Ingreso;
use App\Models\Servicio;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class ReporteGestionTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase, RegistraPagos;

    private Cliente $cliente;

    private Servicio $servicio;

    private Ingreso $ingreso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();

        $this->cliente = Cliente::create([
            'dni' => '12345678',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Gestión',
        ]);

        $this->servicio = Servicio::create([
            'nombre' => 'Control',
            'precio' => 10,
        ]);

        $this->ingreso = $this->compraRecibida();
    }

    public function test_servicios_calculan_costo_y_margen(): void
    {
        $this->servicio->update(['costo' => 4]);
        $this->atender(5);

        $data = app(ReporteService::class)->servicios(
            today()->toDateString(),
            today()->toDateString()
        );

        $servicio = $data['servicios']->first();

        $this->assertSame(4.0, (float) $servicio->costo);
        $this->assertSame(6.0, (float) $servicio->margen);
        $this->assertSame(4.0, (float) $data['total_costo']);
        $this->assertSame(6.0, (float) $data['total_margen']);
    }

    public function test_sugerido_compra_usa_lead_time_y_ventas(): void
    {
        $this->vender(10);
        $this->proveedor->update(['lead_time' => 5]);
        $this->producto->update(['stock_maximo' => 120]);

        $data = app(ReporteService::class)->sugeridoCompra(null, null, 1);
        $producto = $data['productos']->firstWhere('id_producto', $this->producto->id_producto);

        $this->assertSame(5, $producto->lead_time);
        $this->assertSame(30.0, (float) $producto->sugerido);
        $this->assertSame(1, $data['con_sugerido']);
    }

    public function test_cumpleanos_muestra_proximos(): void
    {
        Cliente::create([
            'dni' => '11223344',
            'nombre' => 'Cumple',
            'apellido_paterno' => 'Años',
            'fecha_nacimiento' => today()->addDays(10)->subYears(30)->toDateString(),
            'direccion' => 'Av. Prueba 123',
        ]);

        $data = app(ReporteService::class)->cumpleanos(30);

        $cliente = $data['clientes']->firstWhere('dni', '11223344');

        $this->assertNotNull($cliente);
        $this->assertSame(10, $cliente->dias_faltantes);
        $this->assertSame(30, $cliente->edad_cumple);
    }

    public function test_ventas_producto_calcula_ingresos_costo_y_margen(): void
    {
        $this->vender(10);

        $data = app(ReporteService::class)->ventasProducto(
            today()->toDateString(),
            today()->toDateString()
        );

        $this->assertSame(15.0, (float) $data['total_ingresos']);
        $this->assertSame(10.0, (float) $data['total_costo']);
        $this->assertSame(5.0, (float) $data['total_margen']);
        $this->assertSame(10.0, (float) $data['productos']->first()->cantidad_base);
    }

    public function test_resumen_diario_y_vendedor(): void
    {
        $this->vender(10);

        $service = app(ReporteService::class);

        $diario = $service->ventasDiario(today()->toDateString(), today()->toDateString());
        $this->assertSame(1, $diario['total_tickets']);
        $this->assertSame(15.0, (float) $diario['total_ingresos']);

        $vendedor = $service->ventasVendedor(today()->toDateString(), today()->toDateString());
        $this->assertSame(1, $vendedor['total_tickets']);
        $this->assertSame(15.0, (float) $vendedor['total_ingresos']);
    }

    public function test_margen_agrupado_y_servicios(): void
    {
        $this->vender(10);
        $this->atender(5);

        $service = app(ReporteService::class);

        $margen = $service->margenAgrupado(
            today()->toDateString(),
            today()->toDateString(),
            'categoria'
        );

        $this->assertSame(22.5, (float) $margen['total_ingresos']);
        $this->assertSame(15.0, (float) $margen['total_costo']);
        $this->assertSame(7.5, (float) $margen['total_margen']);

        $servicios = $service->servicios(today()->toDateString(), today()->toDateString());
        $this->assertSame(10.0, (float) $servicios['total']);
        $this->assertSame('Control', $servicios['servicios']->first()->nombre);
    }

    public function test_compras_por_proveedor_y_rotacion(): void
    {
        $this->vender(10);
        $this->atender(5);

        $service = app(ReporteService::class);

        $compras = $service->comprasPorProveedor(today()->toDateString(), today()->toDateString());
        $this->assertSame(100.0, (float) $compras['total']);
        $this->assertSame(1, $compras['proveedores']->first()->compras);

        $rotacion = $service->rotacion(null, null, 90);
        $producto = $rotacion['productos']->firstWhere('id_producto', $this->producto->id_producto);
        $this->assertSame(15.0, (float) $producto->vendidos);
        $this->assertSame(85.0, (float) $producto->stock);
    }

    public function test_merma_y_caja_por_origen(): void
    {
        $this->vender(10);

        $ajuste = AjusteInventario::create([
            'correlativo' => 'AJU-TEST',
            'tipo' => 'dano',
            'fecha' => today()->toDateString(),
            'motivo' => 'Producto dañado',
            'total' => 12.50,
            'user_id' => $this->user->id,
        ]);

        DetalleAjusteInventario::create([
            'ajuste_inventario_id' => $ajuste->id_ajuste_inventario,
            'producto_id_producto' => $this->producto->id_producto,
            'cantidad_base' => 5,
            'costo_unitario_base' => 2.50,
            'costo_total' => 12.50,
        ]);

        $service = app(ReporteService::class);

        $merma = $service->merma(today()->toDateString(), today()->toDateString());
        $this->assertSame(12.5, (float) $merma['total']);

        $caja = $service->cajaPorOrigen(today()->toDateString(), today()->toDateString());
        $this->assertSame(15.0, (float) $caja['total_ingresos']);
        $this->assertTrue($caja['filas']->contains('categoria', 'venta'));
    }

    public function test_clientes_web(): void
    {
        Cliente::create([
            'dni' => '87654321',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Web',
            'email' => 'web@example.com',
            'registro_web' => true,
            'ultimo_login' => now(),
        ]);

        $data = app(ReporteService::class)->clientesWeb(
            today()->toDateString(),
            today()->toDateString()
        );

        $this->assertSame(2, $data['total']);
        $this->assertSame(1, $data['web']);
        $this->assertSame(1, $data['presencial']);
        $this->assertSame(1, $data['activos_web']);
    }

    public function test_clientes_rfm(): void
    {
        $this->vender(10);
        $this->atender(5);

        $data = app(ReporteService::class)->clientesRfm(
            today()->toDateString(),
            today()->toDateString()
        );

        $cliente = $data['clientes']->firstWhere('id_cliente', $this->cliente->id_cliente);

        $this->assertNotNull($cliente);
        $this->assertSame(2, $cliente->frecuencia);
        $this->assertSame(32.5, (float) $cliente->monto);
        $this->assertSame('Nuevo', $cliente->segmento);
    }

    public function test_previews_de_reportes_de_gestion(): void
    {
        $this->vender(10);
        $this->atender(5);

        $tipos = [
            'ventas_producto' => 'Ventas por Producto',
            'ventas_diario' => 'Diario de Ventas',
            'ventas_vendedor' => 'Vendedor y Hora',
            'anulaciones' => 'Anulaciones',
            'compras_proveedor' => 'Compras por Proveedor',
            'costos_producto' => 'Evolución de Costos',
            'devoluciones_proveedor' => 'Devoluciones a Proveedor',
            'merma' => 'Ajustes y Merma',
            'rotacion' => 'Rotación y Cobertura',
            'margen_agrupado' => 'Margen Agrupado',
            'servicios' => 'Servicios',
            'clientes_rfm' => 'Clientes RFM',
            'clientes_web' => 'Clientes Nuevos y Adopción Web',
            'cumpleanos' => 'Cumpleaños',
            'sugerido_compra' => 'Sugerido de Compra',
            'caja_origen' => 'Caja por Origen',
            'resumen_gerencial' => 'Resumen Gerencial',
        ];

        foreach ($tipos as $tipo => $titulo) {
            $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('admin.reportes.preview', [
                    'tipo' => $tipo,
                    'desde' => today()->toDateString(),
                    'hasta' => today()->toDateString(),
                ]))
                ->assertOk()
                ->assertSee($titulo);
        }
    }

    public function test_genera_pdf_y_csv_de_ventas_por_producto(): void
    {
        $this->vender(10);

        $pdf = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.generate', [
                'tipo' => 'ventas_producto',
                'desde' => today()->toDateString(),
                'hasta' => today()->toDateString(),
            ]));

        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');

        $csv = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.generate', [
                'tipo' => 'ventas_producto',
                'desde' => today()->toDateString(),
                'hasta' => today()->toDateString(),
                'formato' => 'csv',
            ]));

        $csv->assertOk();
        $this->assertStringContainsString('Producto', $csv->streamedContent());
    }

    public function test_genera_pdf_del_resumen_gerencial(): void
    {
        $this->vender(10);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.generate', [
                'tipo' => 'resumen_gerencial',
                'desde' => today()->toDateString(),
                'hasta' => today()->toDateString(),
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    private function vender(int $cantidad): void
    {
        $total = $cantidad * 1.50;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), array_merge([
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                        'cantidad' => $cantidad,
                    ],
                ],
            ], $this->pagos($total)))
            ->assertOk();
    }

    private function atender(int $cantidad): void
    {
        $total = ($cantidad * 1.50) + 10;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), array_merge([
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
            ], $this->pagos($total)))
            ->assertOk();
    }

    private function compraRecibida(): Ingreso
    {
        $this->crearCompraBorrador([
            'numero_documento' => 'F001-GESTION',
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 1,
                    'lote' => 'LOT-GESTION',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 100,
                ],
            ],
        ]);

        $ingreso = Ingreso::where('numero_documento', 'F001-GESTION')->firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), ['pagado_desde_caja' => 0])
            ->assertOk();

        return $ingreso->fresh();
    }
}
