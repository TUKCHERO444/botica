<?php

namespace Tests\Feature;

use App\Models\Atencion;
use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Servicio;
use App\Models\Venta;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class PagoVentaAtencionTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase, RegistraPagos;

    private Cliente $cliente;

    private Servicio $servicio;

    private MetodoPago $yape;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();

        $this->cliente = Cliente::create([
            'dni' => '12345678',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Pagos',
        ]);

        $this->servicio = Servicio::create([
            'nombre' => 'Control',
            'precio' => 10,
        ]);

        $this->yape = MetodoPago::create([
            'nombre' => 'Yape',
            'descripcion' => 'Billetera digital',
            'estado' => 1,
        ]);

        $inventario = app(InventarioService::class);
        $inventario->ingresar(
            $this->unidad,
            500,
            'LOT-PAGOS-U',
            now()->addYear()->toDateString(),
            1.00,
            'Ajuste',
            0,
            'SETUP',
            'Stock de prueba'
        );
        $inventario->ingresar(
            $this->caja,
            10,
            'LOT-PAGOS-C',
            now()->addYear()->toDateString(),
            1.00,
            'Ajuste',
            0,
            'SETUP',
            'Stock de prueba'
        );
    }

    public function test_venta_con_pagos_mixtos_guarda_el_desglose(): void
    {
        $total = (float) $this->caja->precio_venta;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 1,
                    ],
                ],
                'pagos' => [
                    ['metodo_pago_id' => $this->yape->id_metodo_pago, 'monto' => 40],
                    ['metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago, 'monto' => $total - 40],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $venta = Venta::firstOrFail();

        $this->assertSame($total, (float) $venta->total);
        $this->assertDatabaseCount('pago', 2);
        $this->assertDatabaseHas('pago', [
            'referencia_tipo' => 'Venta',
            'referencia_id' => $venta->id_venta,
            'metodo_pago_id' => $this->yape->id_metodo_pago,
            'monto' => 40,
        ]);
        $this->assertDatabaseHas('pago', [
            'referencia_tipo' => 'Venta',
            'referencia_id' => $venta->id_venta,
            'metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago,
            'monto' => $total - 40,
        ]);
        $this->assertDatabaseCount('caja_movimiento', 2);
        $this->assertDatabaseHas('caja_movimiento', [
            'referencia_tipo' => 'venta',
            'referencia_id' => $venta->id_venta,
            'metodo_pago_id' => $this->yape->id_metodo_pago,
            'monto' => 40,
            'afecta_efectivo' => false,
            'categoria' => 'venta',
        ]);
        $this->assertDatabaseHas('caja_movimiento', [
            'referencia_tipo' => 'venta',
            'referencia_id' => $venta->id_venta,
            'metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago,
            'monto' => $total - 40,
            'afecta_efectivo' => true,
            'categoria' => 'venta',
        ]);
        $this->assertDatabaseHas('caja', [
            'id_caja' => Caja::abierta()->firstOrFail()->id_caja,
            'total_ingresos' => $total,
            'total_ingresos_efectivo' => $total - 40,
            'total_ingresos_digital' => 40,
        ]);
    }

    public function test_venta_exige_al_menos_un_metodo_de_pago(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 1,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pagos']);

        $this->assertDatabaseCount('venta', 0);
    }

    public function test_venta_rechaza_pagos_que_no_cubren_el_total(): void
    {
        $total = (float) $this->caja->precio_venta;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 1,
                    ],
                ],
                ...$this->pagos($total - 10),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pagos']);

        $this->assertDatabaseCount('venta', 0);
        $this->assertDatabaseCount('pago', 0);
    }

    public function test_venta_rechaza_un_metodo_de_pago_inactivo(): void
    {
        $inactivo = MetodoPago::create(['nombre' => 'BCP inactivo', 'estado' => 0]);
        $total = (float) $this->caja->precio_venta;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 1,
                    ],
                ],
                'pagos' => [
                    ['metodo_pago_id' => $inactivo->id_metodo_pago, 'monto' => $total],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pagos.0.metodo_pago_id']);

        $this->assertDatabaseCount('venta', 0);
    }

    public function test_atencion_con_pagos_mixtos_guarda_el_desglose(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'servicios' => [
                    ['servicio_id_servicio' => $this->servicio->id_servicio, 'precio' => 10],
                ],
                'pagos' => [
                    ['metodo_pago_id' => $this->yape->id_metodo_pago, 'monto' => 4],
                    ['metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago, 'monto' => 6],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $atencion = Atencion::firstOrFail();
        $this->assertDatabaseCount('pago', 2);
        $this->assertDatabaseHas('pago', [
            'referencia_tipo' => 'Atencion',
            'referencia_id' => $atencion->id_atencion,
            'metodo_pago_id' => $this->yape->id_metodo_pago,
            'monto' => 4,
        ]);
    }

    public function test_atencion_confirmada_no_se_edita(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'servicios' => [
                    ['servicio_id_servicio' => $this->servicio->id_servicio, 'precio' => 10],
                ],
                ...$this->pagos(10),
            ])
            ->assertOk();

        $atencion = Atencion::firstOrFail();
        $this->assertSame(1, Pago::count());

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.atenciones.update', $atencion), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'servicios' => [
                    ['servicio_id_servicio' => $this->servicio->id_servicio, 'precio' => 10],
                ],
                'pagos' => [
                    ['metodo_pago_id' => $this->yape->id_metodo_pago, 'monto' => 10],
                ],
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(1, Pago::count());
        $this->assertDatabaseHas('pago', [
            'referencia_tipo' => 'Atencion',
            'referencia_id' => $atencion->id_atencion,
            'metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago,
            'monto' => 10,
        ]);
        $this->assertDatabaseMissing('pago', [
            'metodo_pago_id' => $this->yape->id_metodo_pago,
        ]);
    }

    public function test_formulario_de_edicion_de_atencion_esta_bloqueado(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'servicios' => [
                    ['servicio_id_servicio' => $this->servicio->id_servicio, 'precio' => 10],
                ],
                ...$this->pagos(10),
            ])
            ->assertOk();

        $atencion = Atencion::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.atenciones.edit', $atencion))
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_el_detalle_y_el_ticket_muestran_los_metodos_de_pago(): void
    {
        $total = (float) $this->caja->precio_venta;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 1,
                    ],
                ],
                'pagos' => [
                    ['metodo_pago_id' => $this->yape->id_metodo_pago, 'monto' => 40],
                    ['metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago, 'monto' => $total - 40],
                ],
            ])
            ->assertOk();

        $venta = Venta::firstOrFail();

        $this->get(route('admin.ventas.ticket', $venta->id_venta))
            ->assertOk()
            ->assertSee('Yape')
            ->assertSee('Efectivo');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ventas.show', $venta->id_venta))
            ->assertOk()
            ->assertSee('Yape')
            ->assertSee('Efectivo');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'servicios' => [
                    ['servicio_id_servicio' => $this->servicio->id_servicio, 'precio' => 10],
                ],
                'pagos' => [
                    ['metodo_pago_id' => $this->yape->id_metodo_pago, 'monto' => 4],
                    ['metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago, 'monto' => 6],
                ],
            ])
            ->assertOk();

        $atencion = Atencion::firstOrFail();

        $this->get(route('admin.atenciones.ticket', $atencion->id_atencion))
            ->assertOk()
            ->assertSee('Yape')
            ->assertSee('Efectivo')
            ->assertSee('TICKET DE ATENCIÓN');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.atenciones.show', $atencion->id_atencion))
            ->assertOk()
            ->assertSee('Yape')
            ->assertSee('Efectivo');
    }

    public function test_anular_atencion_conserva_el_movimiento_original_y_registra_reversion(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'servicios' => [
                    ['servicio_id_servicio' => $this->servicio->id_servicio, 'precio' => 10],
                ],
                ...$this->pagos(10),
            ])
            ->assertOk();

        $atencion = Atencion::firstOrFail();
        $original = CajaMovimiento::where('referencia_tipo', 'atencion')
            ->where('referencia_id', $atencion->id_atencion)
            ->where('tipo', 'ingreso')
            ->firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.anular', $atencion), [
                'motivo_anulacion' => 'Atención duplicada',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $atencion->refresh();

        $this->assertTrue($atencion->estaAnulada());
        $this->assertSame('Atención duplicada', $atencion->motivo_anulacion);

        $this->assertDatabaseHas('caja_movimiento', [
            'id_movimiento' => $original->id_movimiento,
            'monto' => 10,
            'categoria' => 'atencion',
        ]);

        $this->assertDatabaseHas('caja_movimiento', [
            'referencia_tipo' => 'atencion',
            'referencia_id' => $atencion->id_atencion,
            'tipo' => 'egreso',
            'monto' => 10,
            'naturaleza' => 'reversion',
            'categoria' => 'anulacion_atencion',
        ]);
    }

    public function test_atencion_confirmada_no_se_elimina(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), [
                'cliente_id_cliente' => $this->cliente->id_cliente,
                'servicios' => [
                    ['servicio_id_servicio' => $this->servicio->id_servicio, 'precio' => 10],
                ],
                ...$this->pagos(10),
            ])
            ->assertOk();

        $atencion = Atencion::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.atenciones.destroy', $atencion))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('atencion', [
            'id_atencion' => $atencion->id_atencion,
            'estado' => Atencion::ESTADO_CONFIRMADA,
        ]);
    }
}
