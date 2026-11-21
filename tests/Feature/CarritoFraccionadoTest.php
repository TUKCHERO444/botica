<?php

namespace Tests\Feature;

use App\Models\Carrito;
use App\Models\CarritoItem;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarritoFraccionadoTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private Producto $producto;

    private ProductoPresentacion $base;

    private ProductoPresentacion $caja;

    protected function setUp(): void
    {
        parent::setUp();

        $presentacionBase = Presentacion::create(['nombre' => 'Tableta', 'activo' => true]);
        $presentacionCaja = Presentacion::create(['nombre' => 'Caja', 'activo' => true]);

        $unidad = UnidadMedida::create([
            'codigo' => 'TAB',
            'nombre' => 'Tableta',
            'permite_fraccion' => true,
            'presentacion_base_id' => $presentacionBase->id_presentacion,
            'activo' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Medicamentos', 'cantidad' => 0, 'estado' => 1]);
        $marca = Marca::create(['nombre' => 'Genérico', 'cantidad' => 0, 'estado' => 1]);

        $this->producto = Producto::create([
            'nombre' => 'Ibuprofeno 400mg',
            'precio_venta' => 0.80,
            'estado' => 1,
            'inventario' => 100,
            'stock' => 100,
            'stock_minimo' => 10,
            'unidad_base_id' => $unidad->id_unidad_medida,
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $this->base = ProductoPresentacion::create([
            'producto_id_producto' => $this->producto->id_producto,
            'presentacion_id' => $presentacionBase->id_presentacion,
            'factor' => 1,
            'precio_venta' => 0.80,
            'paso' => 0.5,
            'es_base' => true,
            'activo' => true,
        ]);

        $this->caja = ProductoPresentacion::create([
            'producto_id_producto' => $this->producto->id_producto,
            'presentacion_id' => $presentacionCaja->id_presentacion,
            'factor' => 10,
            'precio_venta' => 6.00,
            'paso' => 1,
            'es_base' => false,
            'activo' => true,
        ]);

        $this->cliente = Cliente::create([
            'dni' => '12345678',
            'nombre' => 'Cliente',
            'apellido_paterno' => 'Web',
            'email' => 'cliente@example.com',
            'password' => 'secret123',
            'registro_web' => true,
        ]);

        $this->actingAs($this->cliente, 'clientes');
    }

    public function test_agrega_producto_con_presentacion_y_cantidad(): void
    {
        $response = $this->postJson(route('public.carrito.store'), [
            'item_type' => 'producto',
            'item_id' => $this->producto->id_producto,
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
            'cantidad' => 2,
        ]);

        $response->assertOk()->assertJson(['success' => true, 'count' => 2]);

        $this->assertDatabaseHas('carrito_item', [
            'item_type' => 'producto',
            'item_id' => $this->producto->id_producto,
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
            'cantidad' => 2,
            'precio_unitario' => 6,
        ]);

        $item = CarritoItem::firstOrFail();
        $this->assertSame(12.0, $item->subtotal);
        $this->assertStringContainsString('Caja', $item->nombre);
    }

    public function test_repetir_el_producto_suma_la_cantidad(): void
    {
        foreach ([1, 2] as $cantidad) {
            $this->postJson(route('public.carrito.store'), [
                'item_type' => 'producto',
                'item_id' => $this->producto->id_producto,
                'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                'cantidad' => $cantidad,
            ])->assertOk();
        }

        $this->assertDatabaseCount('carrito_item', 1);
        $this->assertSame('3.000', CarritoItem::firstOrFail()->cantidad);
    }

    public function test_permite_media_tableta_en_el_carrito(): void
    {
        $this->postJson(route('public.carrito.store'), [
            'item_type' => 'producto',
            'item_id' => $this->producto->id_producto,
            'producto_presentacion_id' => $this->base->id_producto_presentacion,
            'cantidad' => 0.5,
        ])->assertOk();

        $item = CarritoItem::firstOrFail();
        $this->assertSame('0.500', $item->cantidad);
        $this->assertSame(0.4, $item->subtotal);

        $this->putJson(route('public.carrito.update', $item), ['cantidad' => 1.5])
            ->assertOk()
            ->assertJson(['subtotal' => '1.20', 'total' => '1.20']);
    }

    public function test_rechaza_cantidad_que_no_respeta_el_paso(): void
    {
        $item = CarritoItem::create([
            'carrito_id_carrito' => Carrito::create(['cliente_id_cliente' => $this->cliente->id_cliente])->id_carrito,
            'item_type' => 'producto',
            'item_id' => $this->producto->id_producto,
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
            'cantidad' => 1,
            'precio_unitario' => 6,
        ]);

        $this->putJson(route('public.carrito.update', $item), ['cantidad' => 1.5])
            ->assertStatus(422);
    }

    public function test_carrito_muestra_la_presentacion(): void
    {
        $this->postJson(route('public.carrito.store'), [
            'item_type' => 'producto',
            'item_id' => $this->producto->id_producto,
            'producto_presentacion_id' => $this->caja->id_producto_presentacion,
            'cantidad' => 1,
        ])->assertOk();

        $response = $this->get(route('public.carrito.index'));

        $response->assertOk();
        $response->assertSee('Caja');
        $response->assertSee('S/ 6.00');
    }
}
