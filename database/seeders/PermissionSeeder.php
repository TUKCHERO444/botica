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
            'unidades-medida',
            'presentaciones',
            'proveedores',
            'clientes',
            'ventas',
            'servicios',
            'atenciones',
            'ingresos',
            'devoluciones',
            'ajustes',
            'inventario-inicial',
            'conciliaciones',
            'kardex',
            'lotes',
            'usuarios',
            'roles',
            'caja',
            'metodos-pago',
            'sliders',
            'auditoria',
            'reportes',
            'datos-sistema',
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
