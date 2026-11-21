<?php

namespace Database\Seeders;

use App\Models\Presentacion;
use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            ['codigo' => 'UND', 'nombre' => 'Unidad', 'permite_fraccion' => true, 'presentacion' => 'Unidad'],
            ['codigo' => 'TAB', 'nombre' => 'Tableta', 'permite_fraccion' => true, 'presentacion' => 'Tableta'],
            ['codigo' => 'CAP', 'nombre' => 'Cápsula', 'permite_fraccion' => true, 'presentacion' => 'Cápsula'],
            ['codigo' => 'ML', 'nombre' => 'Mililitro', 'permite_fraccion' => true, 'presentacion' => 'Mililitro'],
            ['codigo' => 'G', 'nombre' => 'Gramo', 'permite_fraccion' => true, 'presentacion' => 'Gramo'],
        ];

        foreach ($unidades as $unidad) {
            $presentacion = Presentacion::where('nombre', $unidad['presentacion'])->first();

            UnidadMedida::firstOrCreate(
                ['codigo' => $unidad['codigo']],
                [
                    'nombre' => $unidad['nombre'],
                    'permite_fraccion' => $unidad['permite_fraccion'],
                    'presentacion_base_id' => $presentacion?->id_presentacion,
                    'activo' => true,
                ]
            );
        }
    }
}
