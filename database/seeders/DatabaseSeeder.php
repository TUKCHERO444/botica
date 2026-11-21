<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PresentacionSeeder::class,
            UnidadMedidaSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            MetodoPagoSeeder::class,
            TablasBaseSeeder::class,
        ]);
    }
}
