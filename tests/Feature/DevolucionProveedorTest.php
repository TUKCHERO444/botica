<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\DevolucionProveedor;
use App\Models\Ingreso;
use App\Models\LoteIngreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\TestCase;

class DevolucionProveedorTest extends TestCase
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

    private function crearCompraRecibida(
        string $documento = 'F001-000001',
        int $cantidad = 2,
        string $lote = 'LOT-DEV',
        bool $pagadaDesdeCaja = false
    ): array {
        $this->crearCompraBorrador([
            'numero_documento' => $documento,
            'pagado_desde_caja' => $pagadaDesdeCaja ? 1 : 0,
            'metodo_pago' => 'efectivo',
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => $cantidad,
                    'lote' => $lote,
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 100,
                ],
            ],
        ]);

        $ingreso = Ingreso::where('numero_documento', $documento)->firstOrFail();

        $this->recibir($ingreso, [
            'pagado_desde_caja' => $pagadaDesdeCaja ? 1 : 0,
            'metodo_pago' => 'efectivo',
        ])->assertOk();

        $ingreso = $ingreso->fresh();
        $detalle = $ingreso->detalles()->firstOrFail();
        $capa = LoteIngreso::where('detalle_ingreso_id', $detalle->id_detalle_ingreso)->firstOrFail();

        return [$ingreso, $detalle, $capa];
    }

    private function payloadDevolucion(
        Ingreso $ingreso,
        int $detalleId,
        float $cantidad = 1,
        bool $reembolsado = false,
        string $metodo = 'efectivo'
    ): array {
        return [
            'ingreso_id_ingreso' => $ingreso->id_ingreso,
            'fecha' => today()->toDateString(),
            'motivo' => 'Producto dañado',
            'reembolsado_a_caja' => $reembolsado ? 1 : 0,
            'metodo_pago' => $reembolsado ? $metodo : null,
            'lineas' => [
                [
                    'detalle_ingreso_id' => $detalleId,
                    'cantidad' => $cantidad,
                ],
            ],
        ];
    }

    public function test_crear_devolucion_parcial_descuenta_stock_lote_y_kardex(): void
    {
        [$ingreso, $detalle, $capa] = $this->crearCompraRecibida();

        $this->assertSame('200.000', $this->producto->fresh()->stock);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion($ingreso, $detalle->id_detalle_ingreso, 1))
            ->assertOk();

        $devolucion = DevolucionProveedor::firstOrFail();

        $this->assertSame('DVP-'.str_pad((string) $devolucion->id_devolucion_proveedor, 8, '0', STR_PAD_LEFT), $devolucion->correlativo);
        $this->assertSame(DevolucionProveedor::ESTADO_REGISTRADA, $devolucion->estado);
        $this->assertSame('100.00', $devolucion->total);
        $this->assertDatabaseCount('detalle_devolucion_proveedor', 1);

        $this->assertSame('100.000', $this->producto->fresh()->stock);
        $this->assertSame('100.000', $capa->fresh()->cantidad_disponible_base);
        $this->assertSame('100.000', $capa->lote->fresh()->cantidad_disponible_base);

        $this->assertDatabaseHas('lote_consumo', [
            'referencia_tipo' => 'DevolucionProveedor',
            'referencia_id' => $devolucion->id_devolucion_proveedor,
            'cantidad_base' => 100,
        ]);

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'DEVOLUCION',
            'referencia_tipo' => 'DevolucionProveedor',
            'referencia_id' => $devolucion->id_devolucion_proveedor,
            'cantidad' => -100,
        ]);

        $this->assertDatabaseCount('caja_movimiento', 0);
    }

    public function test_no_permite_devolver_mas_de_lo_disponible(): void
    {
        [$ingreso, $detalle, $capa] = $this->crearCompraRecibida();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion($ingreso, $detalle->id_detalle_ingreso, 3))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('devolucion_proveedor', 0);
        $this->assertSame('200.000', $this->producto->fresh()->stock);
        $this->assertSame('200.000', $capa->fresh()->cantidad_disponible_base);
    }

    public function test_devolucion_con_reembolso_en_caja_genera_ingreso(): void
    {
        [$ingreso, $detalle] = $this->crearCompraRecibida('F001-000010');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion(
                $ingreso,
                $detalle->id_detalle_ingreso,
                1,
                true,
                'efectivo'
            ))
            ->assertOk();

        $devolucion = DevolucionProveedor::firstOrFail();
        $caja = Caja::firstOrFail();

        $this->assertTrue($devolucion->reembolsado_a_caja);
        $this->assertSame($caja->id_caja, $devolucion->caja_id);
        $this->assertNotNull($devolucion->caja_movimiento_id);
        $this->assertSame('100.00', $caja->total_ingresos);

        $this->assertDatabaseHas('caja_movimiento', [
            'caja_id_caja' => $caja->id_caja,
            'tipo' => 'ingreso',
            'monto' => 100,
            'referencia_tipo' => 'devolucion_compra',
            'referencia_id' => $devolucion->id_devolucion_proveedor,
        ]);
    }

    public function test_devolucion_sin_reembolso_no_mueve_caja(): void
    {
        [$ingreso, $detalle] = $this->crearCompraRecibida('F001-000011');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion(
                $ingreso,
                $detalle->id_detalle_ingreso,
                1,
                false
            ))
            ->assertOk();

        $this->assertDatabaseCount('caja_movimiento', 0);
        $this->assertSame('0.00', Caja::firstOrFail()->total_ingresos);
    }

    public function test_reembolso_sin_caja_abierta_se_bloquea(): void
    {
        Caja::query()->update(['estado' => 'cerrada']);

        [$ingreso, $detalle] = $this->crearCompraRecibida('F001-000012');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion(
                $ingreso,
                $detalle->id_detalle_ingreso,
                1,
                true
            ))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('devolucion_proveedor', 0);
        $this->assertSame('200.000', $this->producto->fresh()->stock);
    }

    public function test_no_permite_devolver_una_compra_en_borrador(): void
    {
        $this->crearCompraBorrador(['numero_documento' => 'F001-000013']);
        $ingreso = Ingreso::where('numero_documento', 'F001-000013')->firstOrFail();
        $detalle = $ingreso->detalles()->firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion($ingreso, $detalle->id_detalle_ingreso))
            ->assertStatus(422);

        $this->assertDatabaseCount('devolucion_proveedor', 0);
    }

    public function test_no_permite_devolver_una_linea_de_otra_compra(): void
    {
        [$ingresoA] = $this->crearCompraRecibida('F001-000014', 2, 'LOT-A');
        [, $detalleB] = $this->crearCompraRecibida('F001-000015', 2, 'LOT-B');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion($ingresoA, $detalleB->id_detalle_ingreso))
            ->assertStatus(422);

        $this->assertDatabaseCount('devolucion_proveedor', 0);
        $this->assertSame('400.000', $this->producto->fresh()->stock);
    }

    public function test_anular_devolucion_restaura_stock_y_caja(): void
    {
        [$ingreso, $detalle, $capa] = $this->crearCompraRecibida('F001-000016');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion(
                $ingreso,
                $detalle->id_detalle_ingreso,
                1,
                true
            ))
            ->assertOk();

        $devolucion = DevolucionProveedor::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.anular', $devolucion), [
                'motivo_anulacion' => 'Registrada por error',
            ])
            ->assertOk();

        $devolucion->refresh();
        $caja = Caja::firstOrFail();

        $this->assertSame(DevolucionProveedor::ESTADO_ANULADA, $devolucion->estado);
        $this->assertSame('200.000', $this->producto->fresh()->stock);
        $this->assertSame('200.000', $capa->fresh()->cantidad_disponible_base);
        $this->assertSame('200.000', $capa->lote->fresh()->cantidad_disponible_base);
        $this->assertDatabaseCount('lote_consumo', 0);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'DEVOLUCION',
            'referencia_tipo' => 'DevolucionProveedor',
            'referencia_id' => $devolucion->id_devolucion_proveedor,
        ]);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'REVERSION',
            'referencia_tipo' => 'DevolucionProveedor',
            'referencia_id' => $devolucion->id_devolucion_proveedor,
        ]);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'INGRESO',
            'referencia_tipo' => 'Compra',
        ]);

        $this->assertSame('100.00', $caja->total_ingresos);
        $this->assertSame('100.00', $caja->total_egresos);
        $this->assertDatabaseCount('caja_movimiento', 2);
    }

    public function test_devolucion_de_dos_lotes(): void
    {
        $this->crearCompraBorrador([
            'numero_documento' => 'F001-000017',
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 2,
                    'lote' => 'LOT-A',
                    'fecha_vencimiento' => now()->addMonths(6)->toDateString(),
                    'costo_unitario' => 100,
                ],
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 2,
                    'lote' => 'LOT-B',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 110,
                ],
            ],
        ]);

        $ingreso = Ingreso::where('numero_documento', 'F001-000017')->firstOrFail();
        $this->recibir($ingreso)->assertOk();
        $ingreso = $ingreso->fresh();

        $detalles = $ingreso->detalles()->get();
        $this->assertCount(2, $detalles);

        $payload = [
            'ingreso_id_ingreso' => $ingreso->id_ingreso,
            'fecha' => today()->toDateString(),
            'motivo' => 'Retiro parcial',
            'reembolsado_a_caja' => 0,
            'lineas' => $detalles->map(fn ($detalle) => [
                'detalle_ingreso_id' => $detalle->id_detalle_ingreso,
                'cantidad' => 1,
            ])->all(),
        ];

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $payload)
            ->assertOk();

        $devolucion = DevolucionProveedor::firstOrFail();

        $this->assertSame('210.00', $devolucion->total);
        $this->assertDatabaseCount('detalle_devolucion_proveedor', 2);
        $this->assertSame('200.000', $this->producto->fresh()->stock);

        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'LOT-A', 'cantidad_disponible_base' => 100]);
        $this->assertDatabaseHas('producto_lote', ['codigo_lote' => 'LOT-B', 'cantidad_disponible_base' => 100]);
    }

    public function test_anular_devolucion_con_caja_cerrada_se_bloquea(): void
    {
        [$ingreso, $detalle, $capa] = $this->crearCompraRecibida('F001-000018');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion(
                $ingreso,
                $detalle->id_detalle_ingreso,
                1,
                true
            ))
            ->assertOk();

        $devolucion = DevolucionProveedor::firstOrFail();
        Caja::query()->update(['estado' => 'cerrada']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.anular', $devolucion), [
                'motivo_anulacion' => 'Intento con caja cerrada',
            ])
            ->assertStatus(422);

        $this->assertSame(DevolucionProveedor::ESTADO_REGISTRADA, $devolucion->fresh()->estado);
        $this->assertSame('100.000', $this->producto->fresh()->stock);
        $this->assertSame('100.000', $capa->fresh()->cantidad_disponible_base);
    }

    public function test_listado_y_formulario_de_devoluciones_renderizan(): void
    {
        [$ingreso, $detalle] = $this->crearCompraRecibida('F001-000019');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), $this->payloadDevolucion(
                $ingreso,
                $detalle->id_detalle_ingreso,
                1
            ))
            ->assertOk();

        $devolucion = DevolucionProveedor::firstOrFail();

        $this->assertSame($ingreso->proveedor_id_proveedor, $devolucion->proveedor_id_proveedor);
        $this->assertNotNull($devolucion->fresh()->proveedor);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.devoluciones.index'))
            ->assertOk()
            ->assertSee('Devoluciones a proveedor')
            ->assertSee($devolucion->correlativo);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.devoluciones.create', ['ingreso_id' => $ingreso->id_ingreso]))
            ->assertOk()
            ->assertSee('Nueva devolución a proveedor')
            ->assertSee('LOT-DEV');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.devoluciones.show', $devolucion))
            ->assertOk()
            ->assertSee('Lotes devueltos');
    }
}
