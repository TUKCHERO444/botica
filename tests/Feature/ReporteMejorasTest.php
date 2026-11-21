<?php

namespace Tests\Feature;

use App\Models\Atencion;
use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\LoteIngreso;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\ProductoLote;
use App\Models\Venta;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class ReporteMejorasTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase, RegistraPagos;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();

        $this->cliente = Cliente::create([
            'dni' => '12345678',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Reporte',
        ]);
    }

    public function test_reporte_de_clientes_no_duplica_montos_por_ventas_y_atenciones(): void
    {
        foreach ([100, 100] as $total) {
            Venta::create([
                'correlativo' => 'T001-'.uniqid(),
                'total' => $total,
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'user_id' => $this->user->id,
                'estado' => Venta::ESTADO_CONFIRMADA,
            ]);
        }

        foreach ([50, 50, 50] as $total) {
            Atencion::create([
                'total' => $total,
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'user_id' => $this->user->id,
            ]);
        }

        $data = app(ReporteService::class)->clientes(
            today()->toDateString(),
            today()->toDateString(),
            20
        );

        $cliente = $data['clientes']->firstWhere('id_cliente', $this->cliente->id_cliente);

        $this->assertNotNull($cliente);
        $this->assertSame(350.0, (float) $cliente->total_gastado);
        $this->assertSame(5, (int) $cliente->total_transacciones);
    }

    public function test_inventario_valora_con_costo_real_de_capas(): void
    {
        $this->compraRecibida();
        $this->producto->update(['precio_compra' => 2.00]);

        $data = app(ReporteService::class)->inventario();

        $producto = $data['productos']->firstWhere('id_producto', $this->producto->id_producto);

        $this->assertNotNull($producto);
        $this->assertSame(100.0, (float) $producto->valor_costo);
        $this->assertSame(100.0, (float) $data['total_valorizado']);
        $this->assertSame(200.0, (float) $data['total_valor_compra']);
    }

    public function test_inventario_permite_filtrar_stock_cero(): void
    {
        $data = app(ReporteService::class)->inventario(null, null, 0);

        $this->assertTrue($data['productos']->contains('id_producto', $this->producto->id_producto));
    }

    public function test_lotes_permite_filtrar_vencidos(): void
    {
        $lote = ProductoLote::create([
            'producto_id' => $this->producto->id_producto,
            'codigo_lote' => 'LOT-VENCIDO',
            'fecha_vencimiento' => today()->subDays(3)->toDateString(),
            'estado' => 'disponible',
            'cantidad_inicial_base' => 10,
            'cantidad_disponible_base' => 10,
            'es_inicial' => false,
        ]);

        LoteIngreso::create([
            'producto_lote_id' => $lote->id,
            'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
            'cantidad_presentacion' => 10,
            'factor' => 1,
            'cantidad_base' => 10,
            'cantidad_disponible_base' => 10,
            'costo_unitario_base' => 1.50,
            'costo_total' => 15,
        ]);

        $service = app(ReporteService::class);

        $vencidos = $service->lotes(null, null, 'vencidos');
        $this->assertSame(1, $vencidos['total_lotes']);
        $this->assertSame(15.0, (float) $vencidos['total_valor_vencido']);

        $proximos = $service->lotes(null, null, 30);
        $this->assertSame(0, $proximos['total_lotes']);
    }

    public function test_reporte_de_pagos_agrupa_y_excluye_ventas_anuladas(): void
    {
        $yape = MetodoPago::create(['nombre' => 'Yape', 'estado' => 1]);

        $ventaOk = Venta::create([
            'correlativo' => 'T001-OK',
            'total' => 100,
            'cliente_id_cliente' => $this->cliente->id_cliente,
            'user_id' => $this->user->id,
            'estado' => Venta::ESTADO_CONFIRMADA,
        ]);

        Pago::create([
            'referencia_tipo' => Pago::TIPO_VENTA,
            'referencia_id' => $ventaOk->id_venta,
            'metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago,
            'monto' => 60,
            'user_id' => $this->user->id,
        ]);
        Pago::create([
            'referencia_tipo' => Pago::TIPO_VENTA,
            'referencia_id' => $ventaOk->id_venta,
            'metodo_pago_id' => $yape->id_metodo_pago,
            'monto' => 40,
            'user_id' => $this->user->id,
        ]);

        $ventaAnulada = Venta::create([
            'correlativo' => 'T001-ANULADA',
            'total' => 50,
            'cliente_id_cliente' => $this->cliente->id_cliente,
            'user_id' => $this->user->id,
            'estado' => Venta::ESTADO_ANULADA,
        ]);

        Pago::create([
            'referencia_tipo' => Pago::TIPO_VENTA,
            'referencia_id' => $ventaAnulada->id_venta,
            'metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago,
            'monto' => 50,
            'user_id' => $this->user->id,
        ]);

        $data = app(ReporteService::class)->pagos(
            today()->toDateString(),
            today()->toDateString()
        );

        $this->assertSame(100.0, (float) $data['total']);
        $this->assertSame(2, $data['cantidad_pagos']);
        $this->assertSame(60.0, (float) $data['metodos']->firstWhere('nombre', 'Efectivo')->total);
        $this->assertSame(40.0, (float) $data['metodos']->firstWhere('nombre', 'Yape')->total);
    }

    public function test_reporte_rechaza_tipo_no_permitido(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.reportes.preview', ['tipo' => 'inexistente']))
            ->assertStatus(422);
    }

    public function test_kardex_acepta_tipo_valido_y_rechaza_entrada(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.preview', [
                'tipo' => 'kardex',
                'producto_id' => $this->producto->id_producto,
                'tipo_movimiento' => 'VENTA',
            ]))
            ->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.reportes.preview', [
                'tipo' => 'kardex',
                'producto_id' => $this->producto->id_producto,
                'tipo_movimiento' => 'entrada',
            ]))
            ->assertStatus(422);
    }

    public function test_reporte_de_pagos_renderiza_vista_previa(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.preview', [
                'tipo' => 'pagos',
                'desde' => today()->toDateString(),
                'hasta' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Reporte de Cobros por Método de Pago');
    }

    public function test_genera_pdf_de_los_reportes_principales(): void
    {
        $this->compraRecibida();

        foreach (['ventas', 'pagos', 'inventario', 'lotes', 'movimientos'] as $tipo) {
            $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get(route('admin.reportes.generate', [
                    'tipo' => $tipo,
                    'desde' => today()->toDateString(),
                    'hasta' => today()->toDateString(),
                ]));

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_genera_csv_del_reporte_de_pagos(): void
    {
        $venta = Venta::create([
            'correlativo' => 'T001-CSV',
            'total' => 25,
            'cliente_id_cliente' => $this->cliente->id_cliente,
            'user_id' => $this->user->id,
            'estado' => Venta::ESTADO_CONFIRMADA,
        ]);

        Pago::create([
            'referencia_tipo' => Pago::TIPO_VENTA,
            'referencia_id' => $venta->id_venta,
            'metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago,
            'monto' => 25,
            'user_id' => $this->user->id,
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.reportes.generate', [
                'tipo' => 'pagos',
                'desde' => today()->toDateString(),
                'hasta' => today()->toDateString(),
                'formato' => 'csv',
            ]));

        $response->assertOk();
        $this->assertStringContainsString('Método de pago', $response->streamedContent());
        $this->assertStringContainsString('Efectivo', $response->streamedContent());
    }

    private function compraRecibida(): Ingreso
    {
        $this->crearCompraBorrador([
            'numero_documento' => 'F001-000001',
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 1,
                    'lote' => 'LOT-MEJORAS',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 100,
                ],
            ],
        ]);

        $ingreso = Ingreso::where('numero_documento', 'F001-000001')->firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), ['pagado_desde_caja' => 0])
            ->assertOk();

        return $ingreso->fresh();
    }
}
