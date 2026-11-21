<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\Venta;
use App\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class AnulacionVentaTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase, RegistraPagos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();
    }

    private function prepararVenta(int $cantidad = 1): Venta
    {
        $this->crearCompraBorrador([
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 2,
                    'lote' => 'LOT-ANUL',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 50,
                ],
            ],
        ]);

        $ingreso = Ingreso::where('numero_documento', 'F001-000001')->firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), ['pagado_desde_caja' => 0])
            ->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => Cliente::create([
                    'dni' => '12345678',
                    'nombre' => 'Cliente',
                    'apellido_paterno' => 'Anulación',
                ])->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => $cantidad,
                    ],
                ],
                ...$this->pagos($cantidad * 100),
            ])
            ->assertOk();

        return Venta::firstOrFail();
    }

    public function test_anular_venta_revierte_stock_lotes_caja_y_kardex(): void
    {
        $venta = $this->prepararVenta();

        $this->assertSame('100.000', $this->producto->fresh()->stock);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [
                'motivo_anulacion' => 'Venta duplicada',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $venta->refresh();

        $this->assertSame(Venta::ESTADO_ANULADA, $venta->estado);
        $this->assertSame('Venta duplicada', $venta->motivo_anulacion);
        $this->assertSame($this->user->id, $venta->anulada_user_id);
        $this->assertNotNull($venta->anulada_at);

        $this->assertSame('200.000', $this->producto->fresh()->stock);
        $this->assertDatabaseHas('producto_lote', [
            'codigo_lote' => 'LOT-ANUL',
            'cantidad_disponible_base' => 200,
        ]);
        $this->assertDatabaseCount('lote_consumo', 0);

        $this->assertDatabaseCount('detalle_venta', 1);
        $this->assertSame('100.00', $venta->total);

        $this->assertDatabaseHas('caja_movimiento', [
            'referencia_tipo' => 'venta',
            'referencia_id' => $venta->id_venta,
            'tipo' => 'ingreso',
            'monto' => 100,
        ]);
        $this->assertDatabaseHas('caja_movimiento', [
            'referencia_tipo' => 'venta',
            'referencia_id' => $venta->id_venta,
            'tipo' => 'egreso',
            'monto' => 100,
        ]);
        $this->assertDatabaseHas('caja', [
            'total_ingresos' => 100,
            'total_egresos' => 100,
        ]);

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'VENTA',
            'referencia_tipo' => 'Venta',
            'referencia_id' => $venta->id_venta,
            'cantidad' => -100,
        ]);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'REVERSION',
            'referencia_tipo' => 'Venta',
            'referencia_id' => $venta->id_venta,
            'cantidad' => 100,
        ]);
    }

    public function test_anular_venta_exige_motivo(): void
    {
        $venta = $this->prepararVenta();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo_anulacion');

        $this->assertSame(Venta::ESTADO_CONFIRMADA, $venta->fresh()->estado);
        $this->assertSame('100.000', $this->producto->fresh()->stock);
    }

    public function test_no_se_puede_anular_dos_veces(): void
    {
        $venta = $this->prepararVenta();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [
                'motivo_anulacion' => 'Primera anulación',
            ])
            ->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [
                'motivo_anulacion' => 'Segunda anulación',
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame('200.000', $this->producto->fresh()->stock);
        $this->assertSame('Primera anulación', $venta->fresh()->motivo_anulacion);
    }

    public function test_no_se_puede_anular_si_la_caja_ya_esta_cerrada(): void
    {
        $venta = $this->prepararVenta();

        Caja::abierta()->firstOrFail()->update(['estado' => 'cerrada']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [
                'motivo_anulacion' => 'Error de digitación',
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(Venta::ESTADO_CONFIRMADA, $venta->fresh()->estado);
        $this->assertSame('100.000', $this->producto->fresh()->stock);
    }

    public function test_anular_venta_con_caja_original_cerrada_registra_el_reembolso_en_la_caja_actual(): void
    {
        $venta = $this->prepararVenta();

        $cajaOriginal = Caja::abierta()->firstOrFail();
        $cajaOriginal->update(['estado' => 'cerrada', 'cierre_at' => now()]);

        $cajaActual = Caja::create([
            'monto_inicial' => 200,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [
                'motivo_anulacion' => 'Devolución al día siguiente',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(Venta::ESTADO_ANULADA, $venta->fresh()->estado);
        $this->assertSame('200.000', $this->producto->fresh()->stock);

        $this->assertDatabaseHas('caja_movimiento', [
            'caja_id_caja' => $cajaActual->id_caja,
            'tipo' => 'egreso',
            'monto' => 100,
            'referencia_tipo' => 'venta',
            'referencia_id' => $venta->id_venta,
            'categoria' => 'anulacion_venta',
            'caja_origen_id' => $cajaOriginal->id_caja,
        ]);

        $this->assertDatabaseHas('caja', [
            'id_caja' => $cajaOriginal->id_caja,
            'total_ingresos' => 100,
            'total_egresos' => 0,
        ]);

        $this->assertDatabaseHas('caja', [
            'id_caja' => $cajaActual->id_caja,
            'total_egresos' => 100,
            'total_egresos_efectivo' => 100,
        ]);
    }

    public function test_reportes_excluyen_ventas_anuladas(): void
    {
        $venta = $this->prepararVenta();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [
                'motivo_anulacion' => 'Venta duplicada',
            ])
            ->assertOk();

        $reporte = app(ReporteService::class);
        $ventas = $reporte->ventas(today()->toDateString(), today()->toDateString());

        $this->assertCount(0, $ventas['ventas']);
        $this->assertEquals(0, $ventas['total_general']);
        $this->assertSame(0, $ventas['cantidad']);

        $general = $reporte->general(now()->month, now()->year);
        $this->assertEquals(0, $general['total_ventas']);
        $this->assertSame(0, $general['cant_ventas']);

        $rentabilidad = $reporte->rentabilidad(today()->toDateString(), today()->toDateString());
        $this->assertTrue($rentabilidad['productos']->isEmpty());
    }

    public function test_ticket_muestra_la_marca_de_anulada(): void
    {
        $venta = $this->prepararVenta();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [
                'motivo_anulacion' => 'Solicitud del cliente',
            ])
            ->assertOk();

        $this->get(route('admin.ventas.ticket', $venta))
            ->assertOk()
            ->assertSee('*** ANULADA ***')
            ->assertSee('Solicitud del cliente');
    }

    public function test_listado_muestra_el_estado_y_la_accion_de_anular(): void
    {
        $this->prepararVenta();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ventas.index'))
            ->assertOk()
            ->assertSee('Confirmada')
            ->assertSee('Anular venta');
    }
}
