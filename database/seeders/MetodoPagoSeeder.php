<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            ['nombre' => 'Efectivo', 'descripcion' => 'Pago en efectivo', 'estado' => 1, 'afecta_efectivo' => 1],
            ['nombre' => 'Yape', 'descripcion' => 'Billetera digital Yape', 'estado' => 1, 'afecta_efectivo' => 0],
            ['nombre' => 'Plin', 'descripcion' => 'Billetera digital Plin', 'estado' => 1, 'afecta_efectivo' => 0],
            ['nombre' => 'BCP', 'descripcion' => 'Transferencia o depósito BCP', 'estado' => 1, 'afecta_efectivo' => 0],
        ];

        foreach ($metodos as $metodo) {
            DB::table('metodo_pago')->updateOrInsert(
                ['nombre' => $metodo['nombre']],
                $metodo
            );
        }
    }
}
