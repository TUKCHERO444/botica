<?php

namespace Tests\Feature;

use App\Models\Atencion;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreaProductoConUnidad;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class AtencionTicketTest extends TestCase
{
    use CreaProductoConUnidad;
    use RefreshDatabase;
    use RegistraPagos;

    private User $user;

    private Producto $producto;

    private ProductoPresentacion $presentacion;

    private Servicio $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['atenciones', 'productos', 'caja'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['atenciones', 'productos', 'caja']);
        $this->actingAs($this->user);

        Caja::create([
            'monto_inicial' => 100,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'monto_final' => 100,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $unidad = $this->crearUnidadBase();
        $categoria = Categoria::create(['nombre' => 'Test', 'cantidad' => 0, 'estado' => 1]);
        $marca = Marca::create(['nombre' => 'Test', 'cantidad' => 0, 'estado' => 1]);

        $this->producto = Producto::create([
            'nombre' => 'Paracetamol 500mg',
            'precio_venta' => 10.50,
            'estado' => 1,
            'inventario' => 20,
            'stock' => 20,
            'stock_minimo' => 5,
            'unidad_base_id' => $unidad->id_unidad_medida,
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $this->presentacion = $this->crearPresentacionBase($this->producto, $unidad);
        $this->crearLoteInicial($this->producto, $this->presentacion, 20);

        $this->servicio = Servicio::create([
            'nombre' => 'Inyección intramuscular',
            'precio' => 15.00,
        ]);
    }

    public function test_ticket_de_atencion_muestra_cliente_general_y_detalle_de_productos_y_servicios(): void
    {
        $atencion = $this->crearAtencion();

        $response = $this->get(route('admin.atenciones.ticket', $atencion->id_atencion));

        $response->assertOk();
        $response->assertSee('TICKET DE ATENCIÓN');
        $response->assertSee($atencion->correlativo);
        $response->assertSee('CLIENTE GENERAL');
        $response->assertSee('DNI:');
        $response->assertSee('---');
        $response->assertSee('Atendido por:');
        $response->assertSee('Paracetamol 500mg');
        $response->assertSee('Inyección intramuscular');
        $response->assertSee('Cant.');
        $response->assertSee('P. Unit.');
        $response->assertSee('Importe');
        $response->assertSee('21.00');
        $response->assertSee('15.00');
        $response->assertSee('S/ 36.00');
        $response->assertSee('Productos:');
        $response->assertSee('Servicios:');
        $response->assertSee('window.print()', false);
    }

    public function test_guardar_atencion_redirige_al_ticket(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), $this->payloadAtencion());

        $atencion = Atencion::firstOrFail();

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'redirect' => route('admin.atenciones.ticket', [
                'atencione' => $atencion->id_atencion,
                'nueva' => 1,
            ]),
        ]);
        $this->assertSame('A001-00000001', $atencion->correlativo);
    }

    public function test_ticket_de_atencion_muestra_el_desglose_de_pagos(): void
    {
        $yape = MetodoPago::create(['nombre' => 'Yape', 'estado' => 1]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), $this->payloadAtencion([
                'pagos' => [
                    ['metodo_pago_id' => $yape->id_metodo_pago, 'monto' => 10.00],
                    ['metodo_pago_id' => $this->metodoEfectivo()->id_metodo_pago, 'monto' => 26.00],
                ],
            ]))
            ->assertOk();

        $atencion = Atencion::firstOrFail();

        $this->get(route('admin.atenciones.ticket', $atencion->id_atencion))
            ->assertOk()
            ->assertSee('Yape')
            ->assertSee('Efectivo')
            ->assertSee('10.00')
            ->assertSee('26.00');
    }

    public function test_atencion_confirmada_no_se_edita_ni_elimina(): void
    {
        $atencion = $this->crearAtencion();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.atenciones.update', $atencion), $this->payloadAtencion())
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.atenciones.destroy', $atencion))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('atencion', [
            'id_atencion' => $atencion->id_atencion,
            'estado' => Atencion::ESTADO_CONFIRMADA,
        ]);
    }

    public function test_anular_atencion_marca_el_ticket_y_revierte_stock(): void
    {
        $atencion = $this->crearAtencion();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.anular', $atencion), [
                'motivo_anulacion' => 'Atención duplicada',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $atencion->refresh();

        $this->assertSame(Atencion::ESTADO_ANULADA, $atencion->estado);
        $this->assertSame('20.000', $this->producto->fresh()->stock);

        $this->get(route('admin.atenciones.ticket', $atencion->id_atencion))
            ->assertOk()
            ->assertSee('*** ANULADA ***')
            ->assertSee('Atención duplicada');
    }

    public function test_anular_atencion_con_caja_original_cerrada_registra_el_reembolso_en_la_caja_actual(): void
    {
        $atencion = $this->crearAtencion();

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
            ->postJson(route('admin.atenciones.anular', $atencion), [
                'motivo_anulacion' => 'Devolución al día siguiente',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(Atencion::ESTADO_ANULADA, $atencion->fresh()->estado);
        $this->assertSame('20.000', $this->producto->fresh()->stock);

        $this->assertDatabaseHas('caja_movimiento', [
            'caja_id_caja' => $cajaActual->id_caja,
            'tipo' => 'egreso',
            'monto' => 36,
            'referencia_tipo' => 'atencion',
            'referencia_id' => $atencion->id_atencion,
            'categoria' => 'anulacion_atencion',
            'caja_origen_id' => $cajaOriginal->id_caja,
        ]);

        $this->assertDatabaseHas('caja', [
            'id_caja' => $cajaActual->id_caja,
            'total_egresos' => 36,
            'total_egresos_efectivo' => 36,
        ]);
    }

    public function test_anular_atencion_sin_caja_abierta_se_bloquea(): void
    {
        $atencion = $this->crearAtencion();

        Caja::abierta()->firstOrFail()->update(['estado' => 'cerrada']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.anular', $atencion), [
                'motivo_anulacion' => 'Error de digitación',
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(Atencion::ESTADO_CONFIRMADA, $atencion->fresh()->estado);
        $this->assertSame('18.000', $this->producto->fresh()->stock);
    }

    private function crearAtencion(): Atencion
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.atenciones.store'), $this->payloadAtencion())
            ->assertOk();

        return Atencion::firstOrFail();
    }

    private function payloadAtencion(array $overrides = []): array
    {
        $cliente = Cliente::firstOrCreate(
            ['dni' => '00000001'],
            ['nombre' => 'SIN IDENTIFICAR', 'registro_web' => false]
        );

        return array_merge([
            'cliente_id_cliente' => $cliente->id_cliente,
            'productos' => [
                [
                    'producto_presentacion_id' => $this->presentacion->id_producto_presentacion,
                    'cantidad' => 2,
                ],
            ],
            'servicios' => [
                [
                    'servicio_id_servicio' => $this->servicio->id_servicio,
                    'precio' => 15.00,
                ],
            ],
            ...$this->pagos(36),
        ], $overrides);
    }
}
