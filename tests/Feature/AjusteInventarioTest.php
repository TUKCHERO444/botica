<?php

namespace Tests\Feature;

use App\Models\AjusteInventario;
use App\Models\ProductoLote;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\TestCase;

class AjusteInventarioTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();
    }

    private function crearLote(
        string $codigo = 'LOT-A',
        int $cantidad = 20,
        ?string $vencimiento = null
    ): ProductoLote {
        app(InventarioService::class)->ingresar(
            $this->unidad,
            $cantidad,
            $codigo,
            $vencimiento ?? now()->addYear()->toDateString(),
            1.5,
            'Ajuste',
            0,
            'SETUP',
            'Stock de prueba'
        );

        return ProductoLote::where('codigo_lote', $codigo)->firstOrFail();
    }

    private function payload(string $tipo, ProductoLote $lote, float $cantidad, string $motivo = 'Motivo de prueba'): array
    {
        return [
            'tipo' => $tipo,
            'fecha' => today()->toDateString(),
            'motivo' => $motivo,
            'lineas' => [
                [
                    'producto_lote_id' => $lote->id,
                    'cantidad' => $cantidad,
                ],
            ],
        ];
    }

    public function test_ajuste_positivo_suma_stock_y_crea_capa(): void
    {
        $lote = $this->crearLote();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('ajuste_positivo', $lote, 5))
            ->assertOk();

        $ajuste = AjusteInventario::firstOrFail();

        $this->assertSame('AJU-'.str_pad((string) $ajuste->id_ajuste_inventario, 8, '0', STR_PAD_LEFT), $ajuste->correlativo);
        $this->assertSame('7.50', $ajuste->total);
        $this->assertSame('25.000', $this->producto->fresh()->stock);
        $this->assertSame('25.000', $lote->fresh()->cantidad_disponible_base);
        $this->assertSame(2, $lote->capas()->count());

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'AJUSTE',
            'referencia_tipo' => 'Ajuste',
            'referencia_id' => $ajuste->id_ajuste_inventario,
            'cantidad' => 5,
        ]);
    }

    public function test_ajuste_negativo_descuenta_stock_y_lote(): void
    {
        $lote = $this->crearLote();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('dano', $lote, 5, 'Producto roto'))
            ->assertOk();

        $ajuste = AjusteInventario::firstOrFail();

        $this->assertSame('7.50', $ajuste->total);
        $this->assertSame('15.000', $this->producto->fresh()->stock);
        $this->assertSame('15.000', $lote->fresh()->cantidad_disponible_base);

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'AJUSTE',
            'referencia_tipo' => 'Ajuste',
            'referencia_id' => $ajuste->id_ajuste_inventario,
            'cantidad' => -5,
        ]);

        $this->assertDatabaseHas('lote_consumo', [
            'referencia_tipo' => 'Ajuste',
            'referencia_id' => $ajuste->id_ajuste_inventario,
            'cantidad_base' => 5,
        ]);
    }

    public function test_conteo_fisico_ajusta_hacia_abajo(): void
    {
        $lote = $this->crearLote();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('conteo_fisico', $lote, 18, 'Conteo mensual'))
            ->assertOk();

        $ajuste = AjusteInventario::firstOrFail();

        $this->assertSame('3.00', $ajuste->total);
        $this->assertSame('18.000', $this->producto->fresh()->stock);
        $this->assertSame('18.000', $lote->fresh()->cantidad_disponible_base);

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'AJUSTE',
            'referencia_id' => $ajuste->id_ajuste_inventario,
            'cantidad' => -2,
        ]);
    }

    public function test_conteo_fisico_ajusta_hacia_arriba(): void
    {
        $lote = $this->crearLote();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('conteo_fisico', $lote, 25, 'Conteo mensual'))
            ->assertOk();

        $ajuste = AjusteInventario::firstOrFail();

        $this->assertSame('7.50', $ajuste->total);
        $this->assertSame('25.000', $this->producto->fresh()->stock);
        $this->assertSame('25.000', $lote->fresh()->cantidad_disponible_base);

        $this->assertDatabaseHas('kardex_movimientos', [
            'tipo_movimiento' => 'AJUSTE',
            'referencia_id' => $ajuste->id_ajuste_inventario,
            'cantidad' => 5,
        ]);
    }

    public function test_conteo_sin_diferencia_no_crea_ajuste(): void
    {
        $lote = $this->crearLote();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('conteo_fisico', $lote, 20))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('ajuste_inventario', 0);
        $this->assertSame('20.000', $this->producto->fresh()->stock);
    }

    public function test_ajuste_negativo_no_permite_mas_que_lo_disponible(): void
    {
        $lote = $this->crearLote();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('dano', $lote, 30))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('ajuste_inventario', 0);
        $this->assertSame('20.000', $this->producto->fresh()->stock);
        $this->assertSame('20.000', $lote->fresh()->cantidad_disponible_base);
    }

    public function test_ajuste_positivo_en_lote_vencido_se_bloquea(): void
    {
        $lote = $this->crearLote();
        $lote->update(['estado' => 'vencido']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('ajuste_positivo', $lote, 5))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('ajuste_inventario', 0);
        $this->assertSame('20.000', $this->producto->fresh()->stock);
    }

    public function test_ajuste_positivo_en_lote_dado_de_baja_se_bloquea(): void
    {
        $lote = $this->crearLote();
        $lote->update(['estado' => 'baja']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('ajuste_positivo', $lote, 5))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('ajuste_inventario', 0);
    }

    public function test_ajuste_genera_correlativo_y_total(): void
    {
        $lote = $this->crearLote();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('consumo_interno', $lote, 2))
            ->assertOk();

        $ajuste = AjusteInventario::firstOrFail();

        $this->assertStringStartsWith('AJU-', $ajuste->correlativo);
        $this->assertSame('3.00', $ajuste->total);
        $this->assertSame('18.000', $this->producto->fresh()->stock);
    }

    public function test_listado_y_formulario_de_ajustes_renderizan(): void
    {
        $lote = $this->crearLote();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ajustes.store'), $this->payload('dano', $lote, 1))
            ->assertOk();

        $ajuste = AjusteInventario::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ajustes.index'))
            ->assertOk()
            ->assertSee('Ajustes de inventario')
            ->assertSee($ajuste->correlativo);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ajustes.create'))
            ->assertOk()
            ->assertSee('Nuevo ajuste de inventario')
            ->assertSee('LOT-A');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ajustes.show', $ajuste))
            ->assertOk()
            ->assertSee('Lotes ajustados');
    }
}
