<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreaProductoConUnidad;
use Tests\TestCase;

class AlertasInventarioTest extends TestCase
{
    use CreaProductoConUnidad;
    use RefreshDatabase;

    private UnidadMedida $unidad;

    private Categoria $categoria;

    private Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['dashboard', 'productos', 'lotes'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['dashboard', 'productos', 'lotes']);
        $this->actingAs($user);

        $this->unidad = $this->crearUnidadBase();
        $this->categoria = Categoria::create(['nombre' => 'Medicamentos', 'cantidad' => 0, 'estado' => 1]);
        $this->marca = Marca::create(['nombre' => 'Genérico', 'cantidad' => 0, 'estado' => 1]);
    }

    private function crearProducto(string $nombre, float $stockMinimo = 5, int $estado = 1): Producto
    {
        return Producto::create([
            'nombre' => $nombre,
            'precio_venta' => 10,
            'estado' => $estado,
            'inventario' => 0,
            'stock' => 0,
            'stock_minimo' => $stockMinimo,
            'unidad_base_id' => $this->unidad->id_unidad_medida,
            'categoria_id_categoria' => $this->categoria->id_categoria,
            'marca_id_marca' => $this->marca->id_marca,
        ]);
    }

    private function conLote(Producto $producto, float $cantidad, string $vencimiento, string $codigo): void
    {
        $presentacion = $this->crearPresentacionBase($producto, $this->unidad);
        $this->crearLoteInicial($producto, $presentacion, $cantidad, $codigo, $vencimiento);
    }

    private function crearEscenario(): array
    {
        $bajo = $this->crearProducto('Medicamento Bajo', 5);
        $this->conLote($bajo, 2, now()->addDays(60)->toDateString(), 'BAJO');

        $agotado = $this->crearProducto('Medicamento Agotado', 5);
        $this->conLote($agotado, 10, now()->subDay()->toDateString(), 'VENCIDO');

        $porVencer = $this->crearProducto('Medicamento PorVencer', 5);
        $this->conLote($porVencer, 20, now()->addDays(10)->toDateString(), 'PROX');

        $ok = $this->crearProducto('Medicamento OK', 5);
        $this->conLote($ok, 20, now()->addDays(90)->toDateString(), 'OK');

        $nuevo = $this->crearProducto('Medicamento Nuevo', 5);

        $inactivo = $this->crearProducto('Medicamento Inactivo', 5, 0);
        $this->conLote($inactivo, 1, now()->addDays(60)->toDateString(), 'INACTIVO');

        return compact('bajo', 'agotado', 'porVencer', 'ok', 'nuevo', 'inactivo');
    }

    public function test_dashboard_usa_stock_vendible_y_muestra_vencimientos(): void
    {
        $this->crearEscenario();

        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();

        $this->assertSame(1, $response->viewData('stockBajo'));
        $this->assertSame(1, $response->viewData('stockAgotado'));
        $this->assertSame(1, $response->viewData('lotesPorVencer'));
        $this->assertSame(1, $response->viewData('lotesVencidos'));

        $riesgo = $response->viewData('productosRiesgo')->pluck('nombre');
        $this->assertTrue($riesgo->contains('Medicamento Bajo'));
        $this->assertTrue($riesgo->contains('Medicamento Agotado'));
        $this->assertFalse($riesgo->contains('Medicamento OK'));
        $this->assertFalse($riesgo->contains('Medicamento Nuevo'));
        $this->assertFalse($riesgo->contains('Medicamento Inactivo'));

        $lotesProximos = $response->viewData('lotesProximos')->pluck('codigo_lote');
        $this->assertTrue($lotesProximos->contains('PROX'));
        $this->assertFalse($lotesProximos->contains('VENCIDO'));

        $response->assertSee('Lotes por vencer');
        $response->assertSee('Revisar lotes vencidos');
        $response->assertSee(route('admin.productos.index', ['alerta' => 'inventario']), false);
        $response->assertSee(route('admin.lotes.index', ['estado' => 'por_vencer']), false);
    }

    public function test_listado_de_productos_filtra_por_alerta(): void
    {
        $this->crearEscenario();

        $bajo = $this->get(route('admin.productos.index', ['alerta' => 'bajo']));
        $bajo->assertOk();
        $bajo->assertSee('Medicamento Bajo');
        $bajo->assertDontSee('Medicamento Agotado');
        $bajo->assertDontSee('Medicamento OK');
        $bajo->assertDontSee('Medicamento Nuevo');
        $bajo->assertDontSee('Medicamento Inactivo');

        $agotado = $this->get(route('admin.productos.index', ['alerta' => 'agotado']));
        $agotado->assertOk();
        $agotado->assertSee('Medicamento Agotado');
        $agotado->assertDontSee('Medicamento Bajo');

        $porVencer = $this->get(route('admin.productos.index', ['alerta' => 'por_vencer']));
        $porVencer->assertOk();
        $porVencer->assertSee('Medicamento PorVencer');
        $porVencer->assertDontSee('Medicamento OK');

        $riesgo = $this->get(route('admin.productos.index', ['alerta' => 'inventario']));
        $riesgo->assertOk();
        $riesgo->assertSee('Medicamento Bajo');
        $riesgo->assertSee('Medicamento Agotado');
        $riesgo->assertDontSee('Medicamento OK');
        $riesgo->assertDontSee('Medicamento Nuevo');
        $riesgo->assertDontSee('Medicamento Inactivo');
    }

    public function test_lotes_filtra_proximos_30_dias_y_vencidos(): void
    {
        $producto = $this->crearProducto('Medicamento Lotes', 5);
        $presentacion = $this->crearPresentacionBase($producto, $this->unidad);

        $this->crearLoteInicial($producto, $presentacion, 10, 'PROXIMO-10', now()->addDays(10)->toDateString());
        $this->crearLoteInicial($producto, $presentacion, 10, 'LEJANO-90', now()->addDays(90)->toDateString());
        $this->crearLoteInicial($producto, $presentacion, 10, 'VENCIDO-1', now()->subDay()->toDateString());

        $proximos = $this->get(route('admin.lotes.index', ['estado' => 'por_vencer']));
        $proximos->assertOk();
        $proximos->assertSee('PROXIMO-10');
        $proximos->assertDontSee('LEJANO-90');
        $proximos->assertDontSee('VENCIDO-1');

        $vencidos = $this->get(route('admin.lotes.index', ['estado' => 'vencido']));
        $vencidos->assertOk();
        $vencidos->assertSee('VENCIDO-1');
        $vencidos->assertDontSee('PROXIMO-10');
    }
}
