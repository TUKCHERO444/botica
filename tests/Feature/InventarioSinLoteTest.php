<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Ingreso;
use App\Models\ProductoLote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\Concerns\RegistraPagos;
use Tests\TestCase;

class InventarioSinLoteTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase, RegistraPagos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();
    }

    public function test_venta_se_bloquea_si_el_stock_no_cuadra_con_los_lotes(): void
    {
        $this->producto->update(['stock' => 10, 'inventario' => 10]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ventas.store'), [
                'cliente_id_cliente' => Cliente::create([
                    'dni' => '12345678',
                    'nombre' => 'Cliente',
                    'apellido_paterno' => 'Sin lote',
                ])->id_cliente,
                'productos' => [
                    [
                        'producto_presentacion_id' => $this->unidad->id_producto_presentacion,
                        'cantidad' => 1,
                    ],
                ],
                ...$this->pagos(1.50),
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringContainsString(
            'Inventario inconsistente',
            (string) $response->json('message')
        );
        $this->assertDatabaseCount('venta', 0);
        $this->assertDatabaseCount('kardex_movimientos', 0);
        $this->assertSame('10.000', $this->producto->fresh()->stock);
    }

    public function test_no_se_recibe_compra_si_el_stock_no_cuadra_con_los_lotes(): void
    {
        $this->producto->update(['stock' => 10, 'inventario' => 10]);

        $this->crearCompraBorrador();
        $ingreso = Ingreso::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.recibir', $ingreso), ['pagado_desde_caja' => 0])
            ->assertStatus(422);

        $this->assertSame('10.000', $this->producto->fresh()->stock);
        $this->assertDatabaseCount('lote_ingreso', 0);
    }

    public function test_comando_detecta_lotes_sin_lote_heredados(): void
    {
        $this->producto->update(['stock' => 10, 'inventario' => 10]);

        ProductoLote::create([
            'producto_id' => $this->producto->id_producto,
            'codigo_lote' => 'SIN-LOTE',
            'fecha_vencimiento' => '2099-12-31',
            'estado' => 'disponible',
            'cantidad_inicial_base' => 10,
            'cantidad_disponible_base' => 10,
            'es_inicial' => true,
        ]);

        $this->artisan('inventario:sin-lote')
            ->expectsOutputToContain('lotes SIN-LOTE heredados')
            ->assertExitCode(1);
    }

    public function test_comando_sin_lotes_sale_cero(): void
    {
        $this->artisan('inventario:sin-lote')
            ->expectsOutput('No hay lotes SIN-LOTE.')
            ->assertExitCode(0);
    }
}
