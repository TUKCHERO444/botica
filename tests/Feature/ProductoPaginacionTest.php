<?php

namespace Tests\Feature;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaContextoCompra;
use Tests\TestCase;

class ProductoPaginacionTest extends TestCase
{
    use CreaContextoCompra, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararContextoCompra();

        for ($i = 1; $i <= 14; $i++) {
            Producto::create([
                'nombre' => 'Producto Paginado '.$i,
                'precio_compra' => 1,
                'precio_venta' => 2,
                'estado' => 1,
                'inventario' => 0,
                'stock' => 0,
                'stock_minimo' => 1,
                'unidad_base_id' => $this->producto->unidad_base_id,
                'categoria_id_categoria' => $this->producto->categoria_id_categoria,
                'marca_id_marca' => $this->producto->marca_id_marca,
            ]);
        }
    }

    public function test_productos_pagina_de_10_en_10(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.productos.index'));

        $response->assertOk();
        $this->assertSame(10, substr_count($response->getContent(), 'data-producto-row'));
        $response->assertSee('de 15 registros');
        $response->assertSee('page=2', false);

        $page2 = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.productos.index', ['page' => 2]));

        $page2->assertOk();
        $this->assertSame(5, substr_count($page2->getContent(), 'data-producto-row'));
        $page2->assertSee('Mostrando 11');
    }

    public function test_paginacion_conserva_la_busqueda(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('admin.productos.index', [
                'q' => 'Producto Paginado',
                'page' => 1,
            ]));

        $response->assertOk();
        $response->assertSee('q=Producto%20Paginado', false);
        $response->assertSee('page=2', false);
    }
}
