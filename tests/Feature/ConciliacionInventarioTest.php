<?php

namespace Tests\Feature;

use App\Models\ConciliacionInventario;
use App\Models\DetalleConciliacionInventario;
use App\Models\ProductoLote;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\TestCase;

class ConciliacionInventarioTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();
    }

    private function crearLote(int $cantidad = 20): ProductoLote
    {
        app(InventarioService::class)->ingresar(
            $this->unidad,
            $cantidad,
            'LOT-CONC',
            now()->addYear()->toDateString(),
            1.5,
            'Ajuste',
            0,
            'SETUP',
            'Stock de prueba'
        );

        return ProductoLote::where('codigo_lote', 'LOT-CONC')->firstOrFail();
    }

    public function test_comando_guarda_conciliacion_sin_diferencias(): void
    {
        $this->crearLote();

        $this->artisan('inventario:conciliar')
            ->assertExitCode(0);

        $this->assertDatabaseHas('conciliacion_inventario', [
            'estado' => ConciliacionInventario::ESTADO_OK,
            'total_productos' => 1,
            'productos_con_diferencia' => 0,
            'lotes_inconsistentes' => 0,
        ]);

        $this->assertDatabaseCount('detalle_conciliacion_inventario', 0);
    }

    public function test_comando_detecta_diferencia_en_producto(): void
    {
        $this->crearLote();
        $this->producto->update(['stock' => 25]);

        $this->artisan('inventario:conciliar')
            ->assertExitCode(1);

        $conciliacion = ConciliacionInventario::firstOrFail();

        $this->assertSame(ConciliacionInventario::ESTADO_CON_DIFERENCIAS, $conciliacion->estado);
        $this->assertSame(1, $conciliacion->productos_con_diferencia);
        $this->assertSame('5.000', $conciliacion->diferencia_total);

        $this->assertDatabaseHas('detalle_conciliacion_inventario', [
            'conciliacion_inventario_id' => $conciliacion->id_conciliacion_inventario,
            'producto_id' => $this->producto->id_producto,
            'stock_cache' => 25,
            'stock_lotes' => 20,
            'diferencia' => 5,
            'lotes_inconsistentes' => 0,
        ]);
    }

    public function test_comando_detecta_diferencia_en_lote(): void
    {
        $lote = $this->crearLote();
        $lote->update(['cantidad_disponible_base' => 15]);

        $this->artisan('inventario:conciliar')
            ->assertExitCode(1);

        $conciliacion = ConciliacionInventario::firstOrFail();
        $detalle = DetalleConciliacionInventario::firstOrFail();

        $this->assertSame(1, $conciliacion->productos_con_diferencia);
        $this->assertSame(1, $conciliacion->lotes_inconsistentes);
        $this->assertSame('5.000', $conciliacion->diferencia_total);
        $this->assertSame(1, $detalle->lotes_inconsistentes);

        $this->assertDatabaseHas('detalle_conciliacion_lote', [
            'detalle_conciliacion_inventario_id' => $detalle->id_detalle_conciliacion_inventario,
            'producto_lote_id' => $lote->id,
            'cantidad_lote' => 15,
            'suma_capas' => 20,
            'diferencia' => -5,
        ]);
    }

    public function test_dry_run_no_persiste(): void
    {
        $this->crearLote();
        $this->producto->update(['stock' => 25]);

        $this->artisan('inventario:conciliar', ['--dry-run' => true])
            ->assertExitCode(1);

        $this->assertDatabaseCount('conciliacion_inventario', 0);
    }

    public function test_comando_guarda_usuario_cuando_se_indica(): void
    {
        $this->crearLote();

        $this->artisan('inventario:conciliar', ['--user' => $this->user->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('conciliacion_inventario', [
            'user_id' => $this->user->id,
            'origen' => ConciliacionInventario::ORIGEN_COMANDO,
        ]);
    }

    public function test_ejecutar_desde_panel_guarda_conciliacion_manual(): void
    {
        $this->crearLote();

        $this->post(route('admin.conciliaciones.ejecutar'))
            ->assertRedirect(route('admin.conciliaciones.index'));

        $this->assertDatabaseHas('conciliacion_inventario', [
            'user_id' => $this->user->id,
            'origen' => ConciliacionInventario::ORIGEN_MANUAL,
            'estado' => ConciliacionInventario::ESTADO_OK,
        ]);
    }

    public function test_listado_y_detalle_de_conciliaciones_renderizan(): void
    {
        $this->crearLote();
        $this->producto->update(['stock' => 25]);

        $this->artisan('inventario:conciliar')->assertExitCode(1);
        $conciliacion = ConciliacionInventario::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.conciliaciones.index'))
            ->assertOk()
            ->assertSee('Conciliaciones de inventario')
            ->assertSee('Con diferencias');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.conciliaciones.show', $conciliacion))
            ->assertOk()
            ->assertSee('Diferencias por producto')
            ->assertSee($this->producto->nombre);
    }
}
