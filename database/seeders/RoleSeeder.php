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
            'proveedores', 'clientes', 'ventas', 'servicios',
            'atenciones', 'ingresos', 'usuarios', 'roles',
            'caja', 'sliders', 'portada.banners', 'portada.header-text', 'portada.categorias', 'portada.promociones', 'combos',
            'auditoria', 'kardex', 'reportes',
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
