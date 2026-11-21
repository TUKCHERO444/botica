<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CatalogoUnidadesPresentacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'unidades-medida']);
        Permission::firstOrCreate(['name' => 'presentaciones']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['unidades-medida', 'presentaciones']);
        $this->actingAs($this->user);
    }

    public function test_puede_crear_y_actualizar_una_unidad_de_medida(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.unidades-medida.store'), [
                'codigo' => 'TAB',
                'nombre' => 'Tableta',
                'permite_fraccion' => 1,
                'activo' => 1,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('unidad_medida', [
            'codigo' => 'TAB',
            'nombre' => 'Tableta',
            'permite_fraccion' => true,
        ]);

        $unidad = UnidadMedida::where('codigo', 'TAB')->firstOrFail();

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put(route('admin.unidades-medida.update', $unidad), [
                'codigo' => 'TAB',
                'nombre' => 'Tableta recubierta',
                'permite_fraccion' => 0,
                'activo' => 1,
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('unidad_medida', [
            'id_unidad_medida' => $unidad->id_unidad_medida,
            'nombre' => 'Tableta recubierta',
            'permite_fraccion' => false,
        ]);
    }

    public function test_no_permite_codigos_de_unidad_duplicados(): void
    {
        UnidadMedida::create([
            'codigo' => 'UND',
            'nombre' => 'Unidad',
            'permite_fraccion' => true,
            'activo' => true,
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.unidades-medida.store'), [
                'codigo' => 'UND',
                'nombre' => 'Otra unidad',
                'permite_fraccion' => 1,
                'activo' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('codigo');
    }

    public function test_puede_crear_presentacion_y_no_permite_duplicados(): void
    {
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('admin.presentaciones.store'), [
                'nombre' => 'Caja',
                'abreviatura' => 'CJA',
                'activo' => 1,
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('presentacion', ['nombre' => 'Caja']);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.presentaciones.store'), [
                'nombre' => 'Caja',
                'activo' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
    }

    public function test_no_puede_eliminar_unidad_asignada_a_producto(): void
    {
        $unidad = UnidadMedida::create([
            'codigo' => 'UND',
            'nombre' => 'Unidad',
            'permite_fraccion' => true,
            'activo' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Test', 'cantidad' => 0, 'estado' => 1]);
        $marca = Marca::create(['nombre' => 'Test', 'cantidad' => 0, 'estado' => 1]);

        Producto::create([
            'nombre' => 'Producto Test',
            'precio_venta' => 10,
            'estado' => 1,
            'inventario' => 10,
            'stock' => 10,
            'stock_minimo' => 1,
            'unidad_base_id' => $unidad->id_unidad_medida,
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.unidades-medida.destroy', $unidad));

        $response->assertStatus(422);
        $this->assertDatabaseHas('unidad_medida', ['id_unidad_medida' => $unidad->id_unidad_medida]);
    }

    public function test_no_puede_eliminar_presentacion_asignada(): void
    {
        $presentacion = Presentacion::create(['nombre' => 'Caja', 'activo' => true]);

        $unidad = UnidadMedida::create([
            'codigo' => 'UND',
            'nombre' => 'Unidad',
            'permite_fraccion' => true,
            'presentacion_base_id' => $presentacion->id_presentacion,
            'activo' => true,
        ]);

        $this->assertNotNull($unidad->presentacion_base_id);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.presentaciones.destroy', $presentacion));

        $response->assertStatus(422);
        $this->assertDatabaseHas('presentacion', ['id_presentacion' => $presentacion->id_presentacion]);
    }
}
