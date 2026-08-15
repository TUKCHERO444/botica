<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modulos = [
            'dashboard',
            'productos',
            'categorias',
            'marcas',
            'proveedores',
            'clientes',
            'ventas',
            'servicios',
            'atenciones',
            'ingresos',
            'kardex',
            'usuarios',
            'roles',
            'caja',
            'sliders',
            'auditoria',
            'reportes',
        ];

        foreach ($modulos as $modulo) {
            Permission::firstOrCreate(['name' => $modulo]);
        }

        Permission::firstOrCreate(['name' => 'portada.banners']);
        Permission::firstOrCreate(['name' => 'portada.header-text']);
        Permission::firstOrCreate(['name' => 'portada.categorias']);
        Permission::firstOrCreate(['name' => 'portada.promociones']);
        Permission::firstOrCreate(['name' => 'combos']);
    }
}
