<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::firstOrCreate(['name' => 'Administrador']);
        $admin->syncPermissions([
            'dashboard', 'productos', 'categorias', 'marcas',
            'unidades-medida', 'presentaciones',
            'proveedores', 'clientes', 'ventas', 'servicios',
            'atenciones', 'ingresos', 'devoluciones', 'ajustes', 'inventario-inicial', 'conciliaciones', 'usuarios', 'roles',
            'caja', 'metodos-pago', 'sliders', 'portada.banners', 'portada.header-text', 'portada.categorias', 'portada.promociones', 'combos',
            'auditoria', 'kardex', 'lotes', 'reportes', 'datos-sistema',
        ]);

        $vendedor = Role::firstOrCreate(['name' => 'Vendedor']);
        $vendedor->syncPermissions([
            'ventas', 'atenciones',
        ]);

        $userAdmin = User::firstOrCreate(
            ['email' => 'admin@sistema.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('admin123'),
            ]
        );
        $userAdmin->assignRole('Administrador');

        $userVendedor = User::firstOrCreate(
            ['email' => 'vendedor@sistema.com'],
            [
                'name' => 'Vendedor',
                'password' => bcrypt('vendedor123'),
            ]
        );
        $userVendedor->assignRole('Vendedor');
    }
}
