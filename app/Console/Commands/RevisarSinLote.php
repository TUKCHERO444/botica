<?php

namespace App\Console\Commands;

use App\Models\ProductoLote;
use Illuminate\Console\Command;

class RevisarSinLote extends Command
{
    protected $signature = 'inventario:sin-lote {--json : Muestra el resultado en JSON}';

    protected $description = 'Lista los lotes SIN-LOTE heredados que deben regularizarse.';

    public function handle(): int
    {
        $lotes = ProductoLote::with('producto')
            ->where('codigo_lote', 'SIN-LOTE')
            ->orderBy('producto_id')
            ->orderBy('id')
            ->get();

        if ($this->option('json')) {
            $this->line($lotes->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $lotes->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        if ($lotes->isEmpty()) {
            $this->info('No hay lotes SIN-LOTE.');

            return self::SUCCESS;
        }

        $this->warn('Se encontraron '.$lotes->count().' lotes SIN-LOTE heredados.');
        $this->table(
            ['Producto', 'Lote', 'Vencimiento', 'Cantidad', 'Estado', 'Stock producto'],
            $lotes->map(fn (ProductoLote $lote) => [
                $lote->producto?->nombre ?? '—',
                $lote->id,
                $lote->fecha_vencimiento?->format('d/m/Y') ?? '—',
                $lote->cantidad_disponible_base,
                $lote->estado,
                $lote->producto?->stock ?? '—',
            ])->all()
        );
        $this->line('Regularice cada producto con conteo físico, ajuste y inventario inicial por lote.');

        return self::FAILURE;
    }
}
