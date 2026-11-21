<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El menú del panel debe mostrar solo los módulos que el rol puede abrir.
 * Si no se filtra, el usuario ve enlaces que terminan en 403.
 */
class MenuPorRolTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConPermisos(array $permisos, string $rol = 'Vendedor'): User
    {
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $rol = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        $rol->syncPermissions($permisos);

        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($rol);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_un_vendedor_no_ve_modulos_que_no_puede_usar(): void
    {
        $vendedor = $this->usuarioConPermisos(['ventas', 'atenciones']);

        $respuesta = $this->actingAs($vendedor)->get('/admin/ventas');

        $respuesta->assertOk();
        $respuesta->assertSee('/admin/ventas');
        $respuesta->assertSee('/admin/atenciones');

        foreach (['dashboard', 'productos', 'categorias', 'marcas', 'ingresos', 'clientes', 'servicios', 'caja'] as $modulo) {
            $respuesta->assertDontSee('data-panel="/admin/'.$modulo.'"', false);
        }
    }

    public function test_el_admin_ve_los_modulos_que_tiene_permitidos(): void
    {
        $modulos = ['dashboard', 'productos', 'categorias', 'marcas', 'ingresos', 'clientes', 'ventas', 'atenciones', 'servicios', 'caja'];
        $admin = $this->usuarioConPermisos($modulos, 'Administrador');

        $respuesta = $this->actingAs($admin)->get('/admin/dashboard');

        $respuesta->assertOk();

        foreach ($modulos as $modulo) {
            $respuesta->assertSee('data-panel="/admin/'.$modulo.'"', false);
        }
    }
}
