<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaProductoConUnidad;
use Tests\TestCase;

class CatalogoPublicoTest extends TestCase
{
    use CreaProductoConUnidad;
    use RefreshDatabase;

    private Categoria $catMedicamentos;

    private Categoria $catCuidado;

    private Marca $marcaGenomelab;

    private Marca $marcaNaturals;

    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unidad = $this->crearUnidadBase();

        $this->catMedicamentos = Categoria::create([
            'nombre' => 'Medicamentos',
            'descripcion' => 'Medicamentos en general',
            'cantidad' => 0,
            'estado' => 1,
        ]);

        $this->catCuidado = Categoria::create([
            'nombre' => 'Cuidado Personal',
            'descripcion' => 'Productos de cuidado',
            'cantidad' => 0,
            'estado' => 1,
        ]);

        $this->marcaGenomelab = Marca::create([
            'nombre' => 'Genomelab',
            'descripcion' => 'Laboratorio Genomelab',
            'cantidad' => 0,
            'estado' => 1,
        ]);

        $this->marcaNaturals = Marca::create([
            'nombre' => 'Naturals',
            'descripcion' => 'Productos naturales',
            'cantidad' => 0,
            'estado' => 1,
        ]);
    }

    private function createProducto(array $overrides = []): Producto
    {
        $producto = Producto::create(array_merge([
            'nombre' => 'Producto Test',
            'descripcion' => 'Descripción test',
            'precio_compra' => 10.00,
            'precio_venta' => 25.50,
            'estado' => 1,
            'inventario' => 50,
            'stock' => 20,
            'stock_minimo' => 5,
            'unidad_base_id' => $this->unidad->id_unidad_medida,
            'categoria_id_categoria' => $this->catMedicamentos->id_categoria,
            'marca_id_marca' => $this->marcaGenomelab->id_marca,
        ], $overrides));

        $this->crearPresentacionBase($producto, $this->unidad);

        return $producto;
    }

    // ==================== LISTADO ====================

    public function test_listing_page_returns_ok(): void
    {
        $response = $this->get(route('public.productos.index'));

        $response->assertOk();
        $response->assertSee('Nuestros Productos');
    }

    public function test_listing_shows_categories_in_sidebar(): void
    {
        $response = $this->get(route('public.productos.index'));

        $response->assertOk();
        $response->assertSee('Medicamentos');
        $response->assertSee('Cuidado Personal');
    }

    public function test_listing_shows_visible_products(): void
    {
        $this->createProducto(['nombre' => 'Paracetamol']);
        $this->createProducto(['nombre' => 'Ibuprofeno']);

        $response = $this->get(route('public.productos.index'));

        $response->assertOk();
        $response->assertSee('Paracetamol');
        $response->assertSee('Ibuprofeno');
    }

    public function test_listing_shows_price_formatted(): void
    {
        $this->createProducto(['nombre' => 'Vitamina C', 'precio_venta' => 15.90]);

        $response = $this->get(route('public.productos.index'));

        $response->assertOk();
        $response->assertSee('S/ 15.90');
    }

    // ==================== VISIBILITY (scope visible) ====================

    public function test_shows_product_with_estado_1_and_stock_0(): void
    {
        $this->createProducto(['nombre' => 'Agotado pero activo', 'estado' => 1, 'stock' => 0]);

        $response = $this->get(route('public.productos.index'));

        $response->assertOk();
        $response->assertSee('Agotado pero activo');
    }

    public function test_hides_product_with_estado_0_and_stock_greater_than_0(): void
    {
        $this->createProducto(['nombre' => 'Desactivado con stock', 'estado' => 0, 'stock' => 10]);

        $response = $this->get(route('public.productos.index'));

        $response->assertOk();
        $response->assertDontSee('Desactivado con stock');
    }

    public function test_hides_product_with_estado_0_and_stock_0(): void
    {
        $this->createProducto(['nombre' => 'Invisible total', 'estado' => 0, 'stock' => 0]);

        $response = $this->get(route('public.productos.index'));

        $response->assertOk();
        $response->assertDontSee('Invisible total');
    }

    // ==================== FILTRO POR CATEGORÍA ====================

    public function test_filters_by_single_category(): void
    {
        $this->createProducto(['nombre' => 'Jabón', 'categoria_id_categoria' => $this->catCuidado->id_categoria]);
        $this->createProducto(['nombre' => 'Antibiótico', 'categoria_id_categoria' => $this->catMedicamentos->id_categoria]);

        $response = $this->get(route('public.productos.index', ['categorias' => [$this->catCuidado->id_categoria]]));

        $response->assertOk();
        $response->assertSee('Jabón');
        $response->assertDontSee('Antibiótico');
    }

    public function test_filters_by_multiple_categories(): void
    {
        $this->createProducto(['nombre' => 'Jabón', 'categoria_id_categoria' => $this->catCuidado->id_categoria]);
        $this->createProducto(['nombre' => 'Antibiótico', 'categoria_id_categoria' => $this->catMedicamentos->id_categoria]);

        $response = $this->get(route('public.productos.index', [
            'categorias' => [$this->catCuidado->id_categoria, $this->catMedicamentos->id_categoria],
        ]));

        $response->assertOk();
        $response->assertSee('Jabón');
        $response->assertSee('Antibiótico');
    }

    // ==================== AUTOCOMPLETE (buscar) ====================

    public function test_buscar_returns_json_matching_products(): void
    {
        $this->createProducto(['nombre' => 'Paracetamol 500mg']);
        $this->createProducto(['nombre' => 'Ibuprofeno 400mg']);

        $response = $this->getJson(route('public.productos.buscar', ['q' => 'Paracetamol']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['nombre' => 'Paracetamol 500mg']);
    }

    public function test_buscar_is_case_insensitive(): void
    {
        $this->createProducto(['nombre' => 'Vitamina C efervescente']);

        $response = $this->getJson(route('public.productos.buscar', ['q' => 'vitamina']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['nombre' => 'Vitamina C efervescente']);
    }

    public function test_buscar_returns_empty_array_when_no_match(): void
    {
        $this->createProducto(['nombre' => 'Algo']);

        $response = $this->getJson(route('public.productos.buscar', ['q' => 'zzzzznohay']));

        $response->assertOk();
        $response->assertJson([]);
    }

    public function test_buscar_requires_minimum_2_characters(): void
    {
        $this->createProducto(['nombre' => 'Producto único']);

        $response = $this->getJson(route('public.productos.buscar', ['q' => 'P']));

        $response->assertOk();
        $response->assertJson([]);
    }

    public function test_buscar_limits_to_10_results(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createProducto(['nombre' => "Prod {$i}"]);
        }

        $response = $this->getJson(route('public.productos.buscar', ['q' => 'Prod']));

        $response->assertOk();
        $this->assertCount(10, $response->json());
    }

    public function test_buscar_includes_url_and_precio_in_response(): void
    {
        $producto = $this->createProducto(['nombre' => 'Jarabe']);
        $expectedUrl = route('public.productos.show', $producto);

        $response = $this->getJson(route('public.productos.buscar', ['q' => 'Jarabe']));

        $response->assertOk();
        $response->assertJsonFragment([
            'nombre' => 'Jarabe',
            'precio' => 25.50,
            'url' => $expectedUrl,
        ]);
    }

    public function test_buscar_excludes_invisible_products(): void
    {
        $this->createProducto(['nombre' => 'Visible', 'estado' => 1, 'stock' => 0]);
        $this->createProducto(['nombre' => 'Invisible', 'estado' => 0, 'stock' => 5]);

        $response = $this->getJson(route('public.productos.buscar', ['q' => 'ible']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['nombre' => 'Visible']);
    }

    // ==================== AJAX ====================

    public function test_ajax_returns_only_grid_html(): void
    {
        $this->createProducto(['nombre' => 'Ajax Product']);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('public.productos.index'));

        $response->assertOk();
        $response->assertSee('Ajax Product');
        $response->assertDontSee('Nuestros Productos');
        $response->assertDontSee('Categorías');
    }

    public function test_ajax_filter_by_category(): void
    {
        $this->createProducto(['nombre' => 'Shampoo', 'categoria_id_categoria' => $this->catCuidado->id_categoria]);
        $this->createProducto(['nombre' => 'Analgésico', 'categoria_id_categoria' => $this->catMedicamentos->id_categoria]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('public.productos.index', ['categorias' => [$this->catCuidado->id_categoria]]));

        $response->assertOk();
        $response->assertSee('Shampoo');
        $response->assertDontSee('Analgésico');
    }

    // ==================== DETALLE ====================

    public function test_product_detail_shows_info(): void
    {
        $producto = $this->createProducto([
            'nombre' => 'Omega 3',
            'descripcion' => 'Ácidos grasos esenciales',
            'precio_venta' => 45.00,
            'stock' => 15,
        ]);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('Omega 3');
        $response->assertSee('Ácidos grasos esenciales');
        $response->assertSee('S/ 45.00');
        $response->assertSee('15 unidades');
    }

    public function test_product_detail_shows_marca(): void
    {
        $producto = $this->createProducto([
            'nombre' => 'Colágeno',
            'marca_id_marca' => $this->marcaNaturals->id_marca,
        ]);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('Naturals');
    }

    public function test_product_detail_shows_breadcrumb(): void
    {
        $producto = $this->createProducto(['nombre' => 'Jarabe para la tos']);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('Inicio');
        $response->assertSee('Productos');
        $response->assertSee('Jarabe para la tos');
    }

    public function test_product_detail_returns_404_for_invisible_product(): void
    {
        $producto = $this->createProducto([
            'nombre' => 'Eliminado',
            'estado' => 0,
            'stock' => 5,
        ]);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertNotFound();
    }

    public function test_product_detail_show_sin_stock_message_when_stock_is_zero(): void
    {
        $producto = $this->createProducto([
            'nombre' => 'Sin stock actual',
            'estado' => 1,
            'stock' => 0,
        ]);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('Sin stock');
        $response->assertDontSee('unidades disponibles');
    }

    // ==================== NAVBAR ====================

    public function test_navbar_has_nuestros_productos_link(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Nuestros productos');
    }

    // ==================== PAGINACIÓN ====================

    public function test_pagination_appears_with_many_products(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createProducto(['nombre' => 'Prod '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $response = $this->get(route('public.productos.index'));

        $response->assertOk();
        $response->assertSee('Prod 00');
        $response->assertDontSee('Prod 20');
    }

    public function test_pagination_page_2_shows_next_products(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createProducto(['nombre' => 'Prod '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $response = $this->get(route('public.productos.index', ['page' => 2]));

        $response->assertOk();
        $response->assertDontSee('Prod 00');
        $response->assertSee('Prod 20');
    }
}
