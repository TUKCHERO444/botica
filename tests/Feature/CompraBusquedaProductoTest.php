<?php

namespace Tests\Feature;

use App\Models\Ingreso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\TestCase;

class CompraBusquedaProductoTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();
    }

    public function test_busca_presentaciones_por_nombre_de_producto(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.ingresos.presentaciones.search', ['q' => 'Ibuprofeno']));

        $response->assertOk();

        $data = $response->json();

        $this->assertCount(2, $data);
        $this->assertEquals($this->unidad->id_producto_presentacion, $data[0]['id']);
        $this->assertEquals($this->caja->id_producto_presentacion, $data[1]['id']);
        $this->assertEquals(100, $data[1]['factor']);
        $this->assertEquals(1, $data[1]['paso']);
        $this->assertEquals(100, $data[1]['precio_venta']);
        $this->assertArrayHasKey('stock_formateado', $data[1]);
    }

    public function test_busca_presentaciones_por_nombre_de_presentacion(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.ingresos.presentaciones.search', ['q' => 'Caja']));

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $this->caja->id_producto_presentacion,
                'presentacion' => 'Caja x 100',
            ]);

        $data = $response->json();
        $this->assertCount(1, $data);
    }

    public function test_busqueda_de_productos_requiere_dos_caracteres(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.ingresos.presentaciones.search', ['q' => 'I']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_formularios_de_compra_usan_el_buscador_de_productos(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.create'))
            ->assertOk()
            ->assertSee('producto-search', false)
            ->assertSee('/admin/ingresos/presentaciones/search', false)
            ->assertDontSee('producto-select', false);

        $this->crearCompraBorrador();
        $ingreso = Ingreso::firstOrFail();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.ingresos.edit', $ingreso))
            ->assertOk()
            ->assertSee('producto-search', false)
            ->assertSee('/admin/ingresos/presentaciones/search', false)
            ->assertSee('data-initial-id', false)
            ->assertDontSee('producto-select', false);
    }
}
