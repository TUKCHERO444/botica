<?php

namespace Database\Seeders;

use App\Models\Presentacion;
use Illuminate\Database\Seeder;

class PresentacionSeeder extends Seeder
{
    public function run(): void
    {
        $presentaciones = [
            ['nombre' => 'Unidad', 'abreviatura' => 'UND'],
            ['nombre' => 'Tableta', 'abreviatura' => 'TAB'],
            ['nombre' => 'Cápsula', 'abreviatura' => 'CAP'],
            ['nombre' => 'Mililitro', 'abreviatura' => 'ML'],
            ['nombre' => 'Gramo', 'abreviatura' => 'G'],
            ['nombre' => 'Caja', 'abreviatura' => 'CJA'],
            ['nombre' => 'Blíster', 'abreviatura' => 'BLI'],
            ['nombre' => 'Frasco', 'abreviatura' => 'FRA'],
            ['nombre' => 'Sobre', 'abreviatura' => 'SOB'],
            ['nombre' => 'Ampolla', 'abreviatura' => 'AMP'],
            ['nombre' => 'Tubo', 'abreviatura' => 'TUB'],
        ];

        foreach ($presentaciones as $presentacion) {
            Presentacion::firstOrCreate(
                ['nombre' => $presentacion['nombre']],
                ['abreviatura' => $presentacion['abreviatura'], 'activo' => true]
            );
        }
    }
}
