<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\TestCase;

class CompraDocumentoTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();
    }

    public function test_rechaza_costo_unitario_con_mas_de_dos_decimales(): void
    {
        $payload = $this->payloadCompra();
        $payload['productos'][0]['costo_unitario'] = 100.999;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('productos.0.costo_unitario');
    }

    public function test_crear_compra_en_borrador_no_mueve_stock_ni_kardex(): void
    {
        $this->crearCompraBorrador();

        $ingreso = Ingreso::firstOrFail();

        $this->assertSame(Ingreso::ESTADO_BORRADOR, $ingreso->estado);
        $this->assertNull($ingreso->correlativo);
        $this->assertSame('100.00', $ingreso->total);
        $this->assertSame('100.00', $ingreso->subtotal);
        $this->assertSame('0.000', $this->producto->fresh()->stock);

        $this->assertDatabaseCount('producto_lote', 0);
        $this->assertDatabaseCount('lote_ingreso', 0);
        $this->assertDatabaseCount('kardex_movimientos', 0);
        $this->assertDatabaseCount('caja_movimiento', 0);

        $this->assertDatabaseHas('detalle_ingreso', [
            'ingreso_id_ingreso' => $ingreso->id_ingreso,
            'cantidad_base' => 100,
            'costo_unitario' => 100,
            'costo_total' => 100,
        ]);
    }

    public function test_compra_exige_tipo_y_numero_de_documento(): void
    {
        $payload = $this->payloadCompra();
        unset($payload['tipo_documento'], $payload['numero_documento']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_documento', 'numero_documento']);

        $this->assertDatabaseCount('ingreso', 0);
    }

    public function test_documento_duplicado_del_mismo_proveedor_se_rechaza(): void
    {
        $this->crearCompraBorrador();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.store'), $this->payloadCompra([
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 1,
                        'lote' => 'LOT-B',
                        'fecha_vencimiento' => now()->addYear()->toDateString(),
                        'costo_unitario' => 100,
                    ],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero_documento');

        $this->assertDatabaseCount('ingreso', 1);
    }

    public function test_el_mismo_documento_se_permite_en_otro_proveedor(): void
    {
        $this->crearCompraBorrador();

        $otroProveedor = Proveedor::create([
            'ruc' => '20100099990',
            'razon_social' => 'Otro Proveedor',
            'condicion' => 'Habilitado',
            'estado' => 1,
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.store'), $this->payloadCompra([
                'proveedor_id_proveedor' => $otroProveedor->id_proveedor,
            ]))
            ->assertOk();

        $this->assertDatabaseCount('ingreso', 2);
    }

    public function test_editar_borrador_reemplaza_lineas_y_recalcula_totales(): void
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
        $this->assertSame('200.00', $ingreso->total);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.ingresos.update', $ingreso), $this->payloadCompra([
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                        'cantidad' => 1,
                        'lote' => 'LOT-A',
                        'fecha_vencimiento' => now()->addYear()->toDateString(),
                        'costo_unitario' => 100,
                    ],
                ],
            ]))
            ->assertOk();

        $ingreso->refresh();

        $this->assertSame('100.00', $ingreso->total);
        $this->assertDatabaseCount('detalle_ingreso', 1);
        $this->assertSame('0.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('kardex_movimientos', 0);
    }

    public function test_eliminar_borrador_elimina_detalles_sin_tocar_stock(): void
    {
        $this->crearCompraBorrador();
        $ingreso = Ingreso::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.ingresos.destroy', $ingreso))
            ->assertOk();

        $this->assertDatabaseCount('ingreso', 0);
        $this->assertDatabaseCount('detalle_ingreso', 0);
        $this->assertSame('0.000', $this->producto->fresh()->stock);
    }

    public function test_no_se_puede_editar_una_compra_recibida(): void
    {
        $this->crearCompraBorrador();
        $ingreso = Ingreso::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), ['pagado_desde_caja' => 0])
            ->assertOk();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.ingresos.update', $ingreso), $this->payloadCompra())
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('ingreso', [
            'id_ingreso' => $ingreso->id_ingreso,
            'estado' => Ingreso::ESTADO_RECIBIDA,
        ]);
    }

    public function test_el_listado_muestra_el_modulo_compras_y_sus_estados(): void
    {
        $this->crearCompraBorrador();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.index'))
            ->assertOk()
            ->assertSee('Compras')
            ->assertSee('Borrador')
            ->assertSee('F001-000001');
    }

    public function test_los_formularios_de_compra_renderizan(): void
    {
        $this->crearCompraBorrador();
        $ingreso = Ingreso::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.edit', $ingreso))
            ->assertOk()
            ->assertSee('Editar compra');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.show', $ingreso))
            ->assertOk()
            ->assertSee('F001-000001');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.formRecibir', $ingreso))
            ->assertOk()
            ->assertSee('Recibir compra');
    }

    public function test_costo_mayor_al_precio_de_venta_se_permite(): void
    {
        $this->crearCompraBorrador([
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 1,
                    'lote' => 'LOT-CARO',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 500,
                ],
            ],
        ]);

        $this->assertSame('500.00', Ingreso::firstOrFail()->total);
    }
}
