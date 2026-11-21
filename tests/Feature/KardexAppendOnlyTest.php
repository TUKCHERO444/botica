<?php

namespace Tests\Feature;

use App\Models\Atencion;
use App\Models\Cliente;
use App\Models\DevolucionProveedor;
use App\Models\Ingreso;
use App\Models\ProductoPresentacion;
use App\Models\Servicio;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class KardexAppendOnlyTest extends TestCase
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
            'apellido_paterno' => 'Kardex',
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
                    'lote' => 'LOT-KARDEX',
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

    private function vender(int $cantidad, ?int $presentacionId = null)
    {
        $presentacion = ProductoPresentacion::find(
            $presentacionId ?? $this->caja->id_producto_presentacion
        );

        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $presentacionId ?? $this->caja->id_producto_presentacion,
                        'cantidad' => $cantidad,
                    ],
                ],
                ...$this->pagos((float) $presentacion->precio_venta * $cantidad),
            ]);
    }

    public function test_anular_compra_conserva_kardex_y_agrega_reversion(): void
    {
        $ingreso = $this->compraRecibida();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.anular', $ingreso), [
                'motivo_anulacion' => 'Documento duplicado',
            ])
            ->assertOk();

        $this->assertDatabaseCount('kardex_movimientos', 2);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'INGRESO',
            'referencia_tipo' => 'Compra',
            'referencia_id' => $ingreso->id_ingreso,
            'cantidad' => 200,
        ]);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'REVERSION',
            'referencia_tipo' => 'Compra',
            'referencia_id' => $ingreso->id_ingreso,
            'cantidad' => -200,
            'stock_anterior' => 200,
            'stock_nuevo' => 0,
        ]);

        $this->assertSame('0.000', $this->producto->fresh()->stock);
    }

    public function test_anular_venta_conserva_kardex_original_y_agrega_reversion(): void
    {
        $this->compraRecibida();
        $this->vender(1)->assertOk();

        $venta = Venta::firstOrFail();
        $this->assertSame('100.000', $this->producto->fresh()->stock);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.anular', $venta), [
                'motivo_anulacion' => 'Venta duplicada',
            ])
            ->assertOk();

        $this->assertSame('200.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('kardex_movimientos', 3);

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

    public function test_no_se_elimina_venta_confirmada_ni_su_kardex(): void
    {
        $this->compraRecibida();
        $this->vender(1)->assertOk();

        $venta = Venta::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.ventas.destroy', $venta))
            ->assertStatus(422);

        $this->assertDatabaseCount('venta', 1);
        $this->assertDatabaseCount('kardex_movimientos', 2);
        $this->assertSame('100.000', $this->producto->fresh()->stock);
    }

    public function test_anular_devolucion_conserva_kardex_y_agrega_reversion(): void
    {
        $ingreso = $this->compraRecibida();
        $detalle = $ingreso->detalles()->firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.store'), [
                'ingreso_id_ingreso' => $ingreso->id_ingreso,
                'fecha' => today()->toDateString(),
                'motivo' => 'Prueba de kardex',
                'reembolsado_a_caja' => 0,
                'lineas' => [
                    [
                        'detalle_ingreso_id' => $detalle->id_detalle_ingreso,
                        'cantidad' => 1,
                    ],
                ],
            ])
            ->assertOk();

        $devolucion = DevolucionProveedor::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.devoluciones.anular', $devolucion), [
                'motivo_anulacion' => 'Registrada por error',
            ])
            ->assertOk();

        $this->assertSame('200.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('kardex_movimientos', 3);

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'DEVOLUCION',
            'referencia_tipo' => 'DevolucionProveedor',
            'referencia_id' => $devolucion->id_devolucion_proveedor,
            'cantidad' => -100,
        ]);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'REVERSION',
            'referencia_tipo' => 'DevolucionProveedor',
            'referencia_id' => $devolucion->id_devolucion_proveedor,
            'cantidad' => 100,
        ]);
    }

    public function test_anular_atencion_conserva_kardex_y_agrega_reversion(): void
    {
        $this->compraRecibida();

        $servicio = Servicio::create([
            'nombre' => 'Control',
            'precio' => 10,
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                        'cantidad' => 10,
                    ],
                ],
                'servicios' => [
                    [
                        'servicio_id_servicio' => $servicio->id_servicio,
                        'precio' => 10,
                    ],
                ],
                ...$this->pagos(25),
            ]);

        $response->assertOk();

        $atencion = Atencion::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.anular', $atencion), [
                'motivo_anulacion' => 'Atención duplicada',
            ])
            ->assertOk();

        $this->assertSame('200.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('kardex_movimientos', 3);

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'ATENCION',
            'referencia_tipo' => 'Atencion',
            'referencia_id' => $atencion->id_atencion,
            'cantidad' => -10,
        ]);
        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'REVERSION',
            'referencia_tipo' => 'Atencion',
            'referencia_id' => $atencion->id_atencion,
            'cantidad' => 10,
        ]);
    }
}
