<?php

namespace Tests\Concerns;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

trait CreaContextoCompra
{
    protected User $user;

    protected Proveedor $proveedor;

    protected Producto $producto;

    protected ProductoPresentacion $unidad;

    protected ProductoPresentacion $caja;

    protected function prepararContextoCompra(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['ingresos', 'devoluciones', 'ajustes', 'inventario-inicial', 'conciliaciones', 'productos', 'caja', 'lotes', 'ventas', 'atenciones', 'reportes'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['ingresos', 'devoluciones', 'ajustes', 'inventario-inicial', 'conciliaciones', 'productos', 'caja', 'lotes', 'ventas', 'atenciones', 'reportes']);
        $this->actingAs($this->user);

        Caja::create([
            'monto_inicial' => 500,
            'total_ingresos' => 0,
            'total_egresos' => 0,
            'estado' => 'abierta',
            'apertura_user_id' => $this->user->id,
            'apertura_at' => now(),
        ]);

        $presentacionUnidad = Presentacion::create(['nombre' => 'Tableta', 'activo' => true]);
        $presentacionCaja = Presentacion::create(['nombre' => 'Caja', 'activo' => true]);

        $unidadMedida = UnidadMedida::create([
            'codigo' => 'TAB',
            'nombre' => 'Tableta',
            'permite_fraccion' => true,
            'presentacion_base_id' => $presentacionUnidad->id_presentacion,
            'activo' => true,
        ]);

        $categoria = Categoria::create(['nombre' => 'Medicamentos', 'cantidad' => 0, 'estado' => 1]);
        $marca = Marca::create(['nombre' => 'Genérico', 'cantidad' => 0, 'estado' => 1]);

        $this->producto = Producto::create([
            'nombre' => 'Ibuprofeno 400mg',
            'precio_compra' => 1.00,
            'precio_venta' => 1.50,
            'estado' => 1,
            'inventario' => 0,
            'stock' => 0,
            'stock_minimo' => 20,
            'unidad_base_id' => $unidadMedida->id_unidad_medida,
            'categoria_id_categoria' => $categoria->id_categoria,
            'marca_id_marca' => $marca->id_marca,
        ]);

        $this->unidad = ProductoPresentacion::create([
            'producto_id_producto' => $this->producto->id_producto,
            'presentacion_id' => $presentacionUnidad->id_presentacion,
            'factor' => 1,
            'precio_venta' => 1.50,
            'paso' => 1,
            'es_base' => true,
            'activo' => true,
            'orden' => 0,
        ]);

        $this->caja = ProductoPresentacion::create([
            'producto_id_producto' => $this->producto->id_producto,
            'presentacion_id' => $presentacionCaja->id_presentacion,
            'factor' => 100,
            'precio_venta' => 100.00,
            'paso' => 1,
            'es_base' => false,
            'activo' => true,
            'orden' => 1,
        ]);

        $this->proveedor = Proveedor::create([
            'ruc' => '20100088880',
            'razon_social' => 'Proveedor Test',
            'condicion' => 'Habilitado',
            'estado' => 1,
        ]);
    }

    protected function payloadCompra(array $overrides = []): array
    {
        return array_merge([
            'proveedor_id_proveedor' => $this->proveedor->id_proveedor,
            'fecha' => now()->toDateString(),
            'tipo_documento' => 'factura',
            'numero_documento' => 'F001-000001',
            'productos' => [
                [
                    'producto_presentacion_id' => $this->caja->id_producto_presentacion,
                    'cantidad' => 1,
                    'lote' => 'LOT-A',
                    'fecha_vencimiento' => now()->addYear()->toDateString(),
                    'costo_unitario' => 100,
                ],
            ],
        ], $overrides);
    }

    protected function crearCompraBorrador(array $overrides = [])
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.ingresos.store'), $this->payloadCompra($overrides))
            ->assertOk();
    }
}
