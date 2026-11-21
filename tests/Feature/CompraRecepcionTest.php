<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Ingreso;
use App\Models\ProductoLote;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\TestCase;

class CompraRecepcionTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();
    }

    private function recibir(Ingreso $ingreso, array $overrides = [])
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), array_merge([
                'pagado_desde_caja' => 0,
            ], $overrides));
    }

    public function test_recibir_compra_crea_lote_stock_y_kardex(): void
    {
        $this->crearCompraBorrador([
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 2,
                    'lote' => 'LOT-A',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 100,
                ],
            ],
        ]);

        $ingreso = Ingreso::firstOrFail();
        $this->recibir($ingreso)->assertOk();

        $ingreso->refresh();

        $this->assertSame(Ingreso::ESTADO_RECIBIDA, $ingreso->estado);
        $this->assertSame('CMP-'.str_pad((string) $ingreso->id_ingreso, 8, '0', STR_PAD_LEFT), $ingreso->correlativo);
        $this->assertNotNull($ingreso->fecha_recepcion);
        $this->assertSame($this->user->id, $ingreso->recepcion_user_id);

        $this->assertSame('200.000', $this->producto->fresh()->stock);

        $this->assertDatabaseHas('producto_lote', [
            'codigo_lote' => 'LOT-A',
            'estado' => 'disponible',
            'cantidad_disponible_base' => 200,
        ]);

        $this->assertDatabaseHas('lote_ingreso', [
            'ingreso_id' => $ingreso->id_ingreso,
            'cantidad_base' => 200,
            'cantidad_disponible_base' => 200,
        ]);

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'INGRESO',
            'referencia_tipo' => 'Compra',
            'referencia_id' => $ingreso->id_ingreso,
            'cantidad' => 200,
            'stock_anterior' => 0,
            'stock_nuevo' => 200,
        ]);

        $this->assertDatabaseCount('caja_movimiento', 0);
    }

    public function test_no_se_puede_recibir_dos_veces(): void
    {
        $this->crearCompraBorrador();
        $ingreso = Ingreso::firstOrFail();

        $this->recibir($ingreso)->assertOk();
        $this->recibir($ingreso)->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame('100.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('lote_ingreso', 1);
    }

    public function test_recibir_con_pago_en_caja_genera_egreso(): void
    {
        $this->crearCompraBorrador([
            'pagado_desde_caja' => 1,
            'metodo_pago' => 'efectivo',
        ]);

        $ingreso = Ingreso::firstOrFail();

        $this->recibir($ingreso, [
            'pagado_desde_caja' => 1,
            'metodo_pago' => 'efectivo',
        ])->assertOk();

        $ingreso->refresh();
        $caja = Caja::firstOrFail();

        $this->assertTrue($ingreso->pagado_desde_caja);
        $this->assertSame($caja->id_caja, $ingreso->caja_id);
        $this->assertNotNull($ingreso->egreso_caja_id);
        $this->assertSame('100.00', $caja->total_egresos);

        $this->assertDatabaseHas('caja_movimiento', [
            'caja_id_caja' => $caja->id_caja,
            'tipo' => 'egreso',
            'monto' => 100,
            'referencia_tipo' => 'compra',
            'referencia_id' => $ingreso->id_ingreso,
        ]);
    }

    public function test_recibir_con_pago_externo_no_mueve_caja(): void
    {
        $this->crearCompraBorrador([
            'pagado_desde_caja' => 0,
            'metodo_pago' => 'transferencia',
            'referencia_pago' => 'OP-123',
        ]);

        $ingreso = Ingreso::firstOrFail();

        $this->recibir($ingreso, [
            'pagado_desde_caja' => 0,
            'metodo_pago' => 'transferencia',
            'referencia_pago' => 'OP-123',
        ])->assertOk();

        $ingreso->refresh();

        $this->assertFalse($ingreso->pagado_desde_caja);
        $this->assertSame('transferencia', $ingreso->metodo_pago);
        $this->assertSame('OP-123', $ingreso->referencia_pago);
        $this->assertNull($ingreso->caja_id);
        $this->assertNull($ingreso->egreso_caja_id);

        $this->assertDatabaseCount('caja_movimiento', 0);
        $this->assertSame('0.00', Caja::firstOrFail()->total_egresos);
        $this->assertSame('100.000', $this->producto->fresh()->stock);
    }

    public function test_pago_desde_caja_sin_caja_abierta_se_bloquea(): void
    {
        Caja::query()->update(['estado' => 'cerrada']);

        $this->crearCompraBorrador([
            'pagado_desde_caja' => 1,
            'metodo_pago' => 'efectivo',
        ]);

        $ingreso = Ingreso::firstOrFail();

        $this->recibir($ingreso, [
            'pagado_desde_caja' => 1,
            'metodo_pago' => 'efectivo',
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame('0.000', $this->producto->fresh()->stock);
        $this->assertSame(Ingreso::ESTADO_BORRADOR, $ingreso->fresh()->estado);
        $this->assertDatabaseCount('lote_ingreso', 0);
    }

    public function test_pago_desde_caja_exige_metodo_de_pago(): void
    {
        $this->crearCompraBorrador();

        $ingreso = Ingreso::firstOrFail();

        $this->recibir($ingreso, [
            'pagado_desde_caja' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('metodo_pago');

        $this->assertSame('0.000', $this->producto->fresh()->stock);
    }

    public function test_anular_compra_recibida_revierte_stock_lotes_y_caja(): void
    {
        $this->crearCompraBorrador([
            'pagado_desde_caja' => 1,
            'metodo_pago' => 'efectivo',
        ]);

        $ingreso = Ingreso::firstOrFail();
        $this->recibir($ingreso, [
            'pagado_desde_caja' => 1,
            'metodo_pago' => 'efectivo',
        ])->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.anular', $ingreso), [
                'motivo_anulacion' => 'Documento duplicado',
            ])
            ->assertOk();

        $ingreso->refresh();
        $caja = Caja::firstOrFail();

        $this->assertSame(Ingreso::ESTADO_ANULADA, $ingreso->estado);
        $this->assertSame('Documento duplicado', $ingreso->motivo_anulacion);
        $this->assertNotNull($ingreso->anulada_at);

        $this->assertSame('0.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('lote_ingreso', 0);
        $this->assertDatabaseCount('kardex_movimientos', 2);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'REVERSION',
            'referencia_tipo' => 'Compra',
            'referencia_id' => $ingreso->id_ingreso,
        ]);
        $this->assertSame('0.000', ProductoLote::firstOrFail()->cantidad_disponible_base);

        $this->assertSame('100.00', $caja->total_egresos);
        $this->assertSame('100.00', $caja->total_ingresos);
        $this->assertDatabaseCount('caja_movimiento', 2);
        $this->assertDatabaseHas('caja_movimiento', [
            'tipo' => 'ingreso',
            'monto' => 100,
            'referencia_tipo' => 'compra',
            'referencia_id' => $ingreso->id_ingreso,
        ]);
    }

    public function test_anular_compra_con_lote_consumido_se_bloquea(): void
    {
        $this->crearCompraBorrador();
        $ingreso = Ingreso::firstOrFail();
        $this->recibir($ingreso)->assertOk();

        app(InventarioService::class)->consumir(
            $this->unidad,
            1,
            'VENTA',
            'Venta',
            999,
            'Consumo de prueba'
        );

        $this->assertSame('99.000', $this->producto->fresh()->stock);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.anular', $ingreso), [
                'motivo_anulacion' => 'Intento de anulación',
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame('99.000', $this->producto->fresh()->stock);
        $this->assertSame(Ingreso::ESTADO_RECIBIDA, $ingreso->fresh()->estado);
        $this->assertDatabaseCount('lote_ingreso', 1);
    }

    public function test_anular_compra_externa_no_mueve_caja(): void
    {
        $this->crearCompraBorrador([
            'pagado_desde_caja' => 0,
            'metodo_pago' => 'transferencia',
        ]);

        $ingreso = Ingreso::firstOrFail();
        $this->recibir($ingreso, [
            'pagado_desde_caja' => 0,
            'metodo_pago' => 'transferencia',
        ])->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.anular', $ingreso), [
                'motivo_anulacion' => 'Devolución al proveedor',
            ])
            ->assertOk();

        $this->assertDatabaseCount('caja_movimiento', 0);
        $this->assertSame('0.00', Caja::firstOrFail()->total_egresos);
        $this->assertSame(Ingreso::ESTADO_ANULADA, $ingreso->fresh()->estado);
    }

    public function test_los_totales_se_calculan_en_el_servidor(): void
    {
        $this->crearCompraBorrador([
            'subtotal' => 1,
            'total' => 1,
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 2,
                    'lote' => 'LOT-A',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 100,
                ],
            ],
        ]);

        $ingreso = Ingreso::firstOrFail();
        $this->assertSame('200.00', $ingreso->total);

        $this->recibir($ingreso)->assertOk();

        $this->assertSame('200.00', $ingreso->fresh()->total);
    }

    public function test_una_compra_puede_tener_dos_lotes_del_mismo_producto(): void
    {
        $this->crearCompraBorrador([
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 1,
                    'lote' => 'LOT-A',
                    'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
                    'costo_unitario' => 100,
                ],
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 1,
                    'lote' => 'LOT-B',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 110,
                ],
            ],
        ]);

        $ingreso = Ingreso::firstOrFail();
        $this->recibir($ingreso)->assertOk();

        $this->assertSame('200.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('producto_lote', 2);
        $this->assertDatabaseCount('lote_ingreso', 2);
        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'LOT-A', 'cantidad_disponible_base' => 100]);
        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'LOT-B', 'cantidad_disponible_base' => 100]);
    }

    public function test_el_formulario_de_anulacion_renderiza(): void
    {
        $this->crearCompraBorrador();
        $ingreso = Ingreso::firstOrFail();
        $this->recibir($ingreso)->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.formAnular', $ingreso))
            ->assertOk()
            ->assertSee('Anular compra');
    }

    public function test_no_se_puede_recibir_un_lote_que_vencio_entre_el_borrador_y_la_recepcion(): void
    {
        $this->crearCompraBorrador([
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 1,
                    'lote' => 'LOT-VENCE',
                    'fecha_vencimiento' => now()->addDay()->toDateString(),
                    'costo_unitario' => 100,
                ],
            ],
        ]);

        $ingreso = Ingreso::firstOrFail();

        $this->travel(2)->days();

        $this->recibir($ingreso)->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame('0.000', $this->producto->fresh()->stock);
        $this->assertSame(Ingreso::ESTADO_BORRADOR, $ingreso->fresh()->estado);
        $this->assertDatabaseCount('lote_ingreso', 0);
    }
}
