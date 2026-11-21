<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaProductoConUnidad;
use Tests\TestCase;

class ProductoMultiImagenTest extends TestCase
{
    use CreaProductoConUnidad;
    use RefreshDatabase;

    private Categoria $categoria;

    private Marca $marca;

    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unidad = $this->crearUnidadBase();

        $this->categoria = Categoria::create([
            'nombre' => 'Medicamentos',
            'descripcion' => null,
            'cantidad' => 0,
            'estado' => 1,
        ]);

        $this->marca = Marca::create([
            'nombre' => 'Genomelab',
            'descripcion' => null,
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
            'categoria_id_categoria' => $this->categoria->id_categoria,
            'marca_id_marca' => $this->marca->id_marca,
        ], $overrides));

        $this->crearPresentacionBase($producto, $this->unidad);

        return $producto;
    }

    private function crearImagen(Producto $producto, string $url, int $orden = 0): ProductoImagen
    {
        return ProductoImagen::create([
            'producto_id_producto' => $producto->id_producto,
            'url' => $url,
            'orden' => $orden,
        ]);
    }

    // ==================== MODELO ====================

    public function test_producto_has_imagenes_relationship(): void
    {
        $producto = $this->createProducto();
        $imagen = $this->crearImagen($producto, 'https://ejemplo.com/img1.jpg', 0);

        $this->assertCount(1, $producto->imagenes);
        $this->assertTrue($producto->imagenes->first()->is($imagen));
    }

    public function test_imagenes_ordered_by_orden(): void
    {
        $producto = $this->createProducto();
        $this->crearImagen($producto, 'https://ejemplo.com/tercera.jpg', 2);
        $this->crearImagen($producto, 'https://ejemplo.com/primera.jpg', 0);
        $this->crearImagen($producto, 'https://ejemplo.com/segunda.jpg', 1);

        $this->assertEquals('primera.jpg', basename($producto->imagenes[0]->url));
        $this->assertEquals('segunda.jpg', basename($producto->imagenes[1]->url));
        $this->assertEquals('tercera.jpg', basename($producto->imagenes[2]->url));
    }

    public function test_foto_url_returns_null_when_no_images(): void
    {
        $producto = $this->createProducto();

        $this->assertNull($producto->foto_url);
    }

    public function test_foto_url_returns_first_image_url(): void
    {
        $producto = $this->createProducto();
        $this->crearImagen($producto, 'https://ejemplo.com/primera.jpg', 0);
        $this->crearImagen($producto, 'https://ejemplo.com/segunda.jpg', 1);

        $this->assertEquals('https://ejemplo.com/primera.jpg', $producto->foto_url);
    }

    public function test_foto_url_updates_when_reordered(): void
    {
        $producto = $this->createProducto();
        $this->crearImagen($producto, 'https://ejemplo.com/segunda.jpg', 0);
        $img1 = $this->crearImagen($producto, 'https://ejemplo.com/primera.jpg', 1);

        $img1->update(['orden' => -1]);
        $producto->load('imagenes');

        $this->assertEquals('https://ejemplo.com/primera.jpg', $producto->foto_url);
    }

    public function test_deleting_producto_cascades_imagenes(): void
    {
        $producto = $this->createProducto();
        $this->crearImagen($producto, 'https://ejemplo.com/img.jpg', 0);

        $producto->delete();

        $this->assertDatabaseMissing('producto_imagen', [
            'producto_id_producto' => $producto->id_producto,
        ]);
    }

    public function test_producto_imagen_belongs_to_producto(): void
    {
        $producto = $this->createProducto();
        $imagen = $this->crearImagen($producto, 'https://ejemplo.com/img.jpg', 0);

        $this->assertTrue($imagen->producto->is($producto));
    }

    // ==================== VALIDACIÓN ====================

    public function test_product_can_be_created_without_images(): void
    {
        $producto = $this->createProducto();

        $this->assertDatabaseHas('producto', ['id_producto' => $producto->id_producto]);
        $this->assertCount(0, $producto->imagenes);
    }

    // ==================== PUBLIC SHOW ====================

    public function test_public_show_shows_gallery_when_product_has_images(): void
    {
        $producto = $this->createProducto();
        $this->crearImagen($producto, 'https://ejemplo.com/img1.jpg', 0);
        $this->crearImagen($producto, 'https://ejemplo.com/img2.jpg', 1);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('galeria-principal');
        $response->assertSee('galeria-thumbnails');
    }

    public function test_public_show_hides_gallery_when_product_has_no_images(): void
    {
        $producto = $this->createProducto();

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('Sin imagen disponible');
    }

    public function test_public_show_shows_navigation_buttons_with_multiple_images(): void
    {
        $producto = $this->createProducto();
        $this->crearImagen($producto, 'https://ejemplo.com/img1.jpg', 0);
        $this->crearImagen($producto, 'https://ejemplo.com/img2.jpg', 1);
        $this->crearImagen($producto, 'https://ejemplo.com/img3.jpg', 2);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('galeria-principal');
        $response->assertSee('btn-anterior');
        $response->assertSee('btn-siguiente');
        $response->assertSee('galeria-thumbnails');
    }

    public function test_public_show_hides_navigation_with_single_image(): void
    {
        $producto = $this->createProducto();
        $this->crearImagen($producto, 'https://ejemplo.com/img1.jpg', 0);

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertDontSee('btn-anterior');
        $response->assertDontSee('btn-siguiente');
        $response->assertDontSee('galeria-thumbnails');
        $response->assertSee('imagen-principal');
    }

    public function test_public_show_still_shows_info_without_images(): void
    {
        $producto = $this->createProducto();

        $response = $this->get(route('public.productos.show', $producto));

        $response->assertOk();
        $response->assertSee('Producto Test');
        $response->assertSee('S/ 25.50');
        $response->assertSee('20 unidades');
    }
}
